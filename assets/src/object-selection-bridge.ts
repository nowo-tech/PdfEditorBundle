import type { EditorRegistry } from './types';

type PluginProvides = { provides?: () => unknown } | null | undefined;

type EventHook<T> = (listener: (value: T) => void) => () => void;

function subscribeHook<T>(
  hook: EventHook<T> | undefined | null,
  listener: (value: T) => void,
): (() => void) | undefined {
  if (typeof hook === 'function') {
    return hook(listener);
  }
  return undefined;
}

function plugin<T>(registry: EditorRegistry, id: string): T | null {
  const entry = registry.getPlugin(id) as PluginProvides;
  return (entry?.provides?.() as T | undefined) ?? null;
}

/** PDF annotation subtypes exposed in the properties panel. */
const TEXT = 1;
const FREETEXT = 3;
const WIDGET = 20;

interface TrackedAnnotation {
  object: {
    id: string;
    type: number;
    pageIndex: number;
    contents?: string;
    fontSize?: number;
    fontColor?: string;
    fontFamily?: string;
    field?: {
      name: string;
      value: string;
    };
  };
}

interface AnnotationScope {
  getState(): { pages: Record<number, string[]> };
  getSelectedAnnotation(): TrackedAnnotation | null;
  getSelectedAnnotations(): TrackedAnnotation[];
  getAnnotationById(id: string): TrackedAnnotation | null;
  updateAnnotation(pageIndex: number, annotationId: string, patch: Record<string, unknown>): void;
  deleteAnnotation(pageIndex: number, annotationId: string): void;
  deleteAnnotations(items: Array<{ pageIndex: number; id: string }>): void;
  setActiveTool(toolId: string | null): void;
  setLocked(mode: { type: 'none' }): void;
  onStateChange: EventHook<{ selectedUids?: string[]; selectedUid?: string | null }>;
}

interface AnnotationCapability {
  forDocument(documentId: string): AnnotationScope;
  onStateChange: EventHook<{ documentId: string; state: { selectedUids?: string[] } }>;
}

interface FormScope {
  getSelectedFieldId(): string | null;
  deselectField(): void;
  onStateChange: EventHook<{ selectedFieldId: string | null }>;
}

interface FormCapability {
  forDocument(documentId: string): FormScope;
  getSelectedFieldId(documentId?: string): string | null;
  onStateChange: EventHook<{ documentId: string; state: { selectedFieldId: string | null } }>;
}

interface CommandsCapability {
  execute(commandId: string, documentId?: string, source?: 'keyboard' | 'ui' | 'api'): void;
}

export interface ObjectSelection {
  kind: 'freetext' | 'widget' | 'note';
  pageIndex: number;
  id: string;
  label: string;
  contents: string;
  fontSize: number;
  fontColor: string;
  fontFamily: string;
  fieldName?: string;
}

export function enableObjectEditing(registry: EditorRegistry, documentId: string): void {
  const annotation = plugin<AnnotationCapability>(registry, 'annotation');
  annotation?.forDocument(documentId).setLocked({ type: 'none' });
}

function resolvePageIndex(
  annotation: AnnotationCapability,
  documentId: string,
  tracked: TrackedAnnotation,
): number {
  if (typeof tracked.object.pageIndex === 'number') {
    return tracked.object.pageIndex;
  }
  const pages = annotation.forDocument(documentId).getState().pages ?? {};
  for (const [page, ids] of Object.entries(pages)) {
    if (ids.includes(tracked.object.id)) {
      return Number(page);
    }
  }
  return 0;
}

function mapAnnotationToSelection(
  tracked: TrackedAnnotation,
  pageIndex: number,
): ObjectSelection | null {
  const obj = tracked.object;
  const base = {
    pageIndex,
    id: obj.id,
    fontSize: obj.fontSize ?? 12,
    fontColor: obj.fontColor ?? '#000000',
    fontFamily: obj.fontFamily ?? 'Helvetica',
  };

  if (obj.type === FREETEXT) {
    return {
      kind: 'freetext',
      ...base,
      label: obj.contents?.slice(0, 40) || 'Text box',
      contents: obj.contents ?? '',
    };
  }

  if (obj.type === TEXT) {
    return {
      kind: 'note',
      ...base,
      label: obj.contents?.slice(0, 40) || 'Note',
      contents: obj.contents ?? '',
    };
  }

  if (obj.type === WIDGET) {
    return {
      kind: 'widget',
      ...base,
      label: obj.field?.name || 'Form field',
      contents: obj.field?.value ?? obj.contents ?? '',
      fieldName: obj.field?.name,
    };
  }

  return {
    kind: 'freetext',
    ...base,
    label: `Object (${obj.type})`,
    contents: obj.contents ?? '',
  };
}

function resolveTrackedSelection(
  annotation: AnnotationCapability,
  form: FormCapability | null,
  documentId: string,
): TrackedAnnotation | null {
  const scope = annotation.forDocument(documentId);

  const formFieldId = form?.getSelectedFieldId(documentId) ?? form?.forDocument(documentId).getSelectedFieldId();
  if (formFieldId) {
    const byForm = scope.getAnnotationById(formFieldId);
    if (byForm) {
      return byForm;
    }
  }

  const single = scope.getSelectedAnnotation();
  if (single) {
    return single;
  }

  const multi = scope.getSelectedAnnotations();
  return multi.length > 0 ? multi[0] : null;
}

export function readObjectSelection(
  registry: EditorRegistry,
  documentId: string,
): ObjectSelection | null {
  const annotation = plugin<AnnotationCapability>(registry, 'annotation');
  const form = plugin<FormCapability>(registry, 'form');
  if (!annotation) {
    return null;
  }

  const tracked = resolveTrackedSelection(annotation, form, documentId);
  if (!tracked) {
    return null;
  }

  const pageIndex = resolvePageIndex(annotation, documentId, tracked);
  return mapAnnotationToSelection(tracked, pageIndex);
}

export function subscribeObjectSelection(
  registry: EditorRegistry,
  documentId: string,
  onSelection: (selection: ObjectSelection | null) => void,
): () => void {
  const annotation = plugin<AnnotationCapability>(registry, 'annotation');
  const form = plugin<FormCapability>(registry, 'form');
  if (!annotation) {
    return () => undefined;
  }

  const refresh = () => onSelection(readObjectSelection(registry, documentId));
  refresh();

  const unsubs: Array<() => void> = [];

  const annScope = annotation.forDocument(documentId);
  const annUnsub = subscribeHook(annScope.onStateChange, refresh);
  if (annUnsub) {
    unsubs.push(annUnsub);
  }

  const annGlobalUnsub = subscribeHook(annotation.onStateChange, (event) => {
    if (event.documentId === documentId) {
      refresh();
    }
  });
  if (annGlobalUnsub) {
    unsubs.push(annGlobalUnsub);
  }

  if (form) {
    const formScope = form.forDocument(documentId);
    const formUnsub = subscribeHook(formScope.onStateChange, refresh);
    if (formUnsub) {
      unsubs.push(formUnsub);
    }
    const formGlobalUnsub = subscribeHook(form.onStateChange, (event) => {
      if (event.documentId === documentId) {
        refresh();
      }
    });
    if (formGlobalUnsub) {
      unsubs.push(formGlobalUnsub);
    }
  }

  return () => unsubs.forEach((u) => u());
}

export interface ObjectSelectionPatch {
  contents?: string;
  fontSize?: number;
  fontColor?: string;
  fontFamily?: string;
  fieldName?: string;
}

export function applyObjectSelectionPatch(
  registry: EditorRegistry,
  documentId: string,
  selection: ObjectSelection,
  patch: ObjectSelectionPatch,
): void {
  const annotation = plugin<AnnotationCapability>(registry, 'annotation');
  if (!annotation) {
    return;
  }
  const scope = annotation.forDocument(documentId);
  scope.setLocked({ type: 'none' });

  if (selection.kind === 'freetext' || selection.kind === 'note') {
    scope.updateAnnotation(selection.pageIndex, selection.id, {
      contents: patch.contents ?? selection.contents,
      fontSize: patch.fontSize ?? selection.fontSize,
      fontColor: patch.fontColor ?? selection.fontColor,
      fontFamily: patch.fontFamily ?? selection.fontFamily,
    });
    return;
  }

  const tracked = scope.getAnnotationById(selection.id);
  if (!tracked?.object.field) {
    scope.updateAnnotation(selection.pageIndex, selection.id, {
      contents: patch.contents ?? selection.contents,
      fontSize: patch.fontSize ?? selection.fontSize,
      fontColor: patch.fontColor ?? selection.fontColor,
      fontFamily: patch.fontFamily ?? selection.fontFamily,
    });
    return;
  }

  scope.updateAnnotation(selection.pageIndex, selection.id, {
    fontSize: patch.fontSize ?? selection.fontSize,
    fontColor: patch.fontColor ?? selection.fontColor,
    fontFamily: patch.fontFamily ?? selection.fontFamily,
    field: {
      ...tracked.object.field,
      name: patch.fieldName ?? selection.fieldName ?? tracked.object.field.name,
      value: patch.contents ?? selection.contents,
    },
  });
}

/** Delete the current embedpdf selection (FreeText, widget, note, …). */
export function deleteObjectSelection(
  registry: EditorRegistry,
  documentId: string,
  fallback?: ObjectSelection | null,
): boolean {
  const annotation = plugin<AnnotationCapability>(registry, 'annotation');
  const commands = plugin<CommandsCapability>(registry, 'commands');
  const form = plugin<FormCapability>(registry, 'form');
  if (!annotation) {
    return false;
  }

  const scope = annotation.forDocument(documentId);
  scope.setLocked({ type: 'none' });

  const selected = scope.getSelectedAnnotations();
  const tracked =
    scope.getSelectedAnnotation() ??
    selected[0] ??
    (fallback?.id ? scope.getAnnotationById(fallback.id) : null);

  if (commands && selected.length > 1) {
    commands.execute('annotation:delete-all-selected', documentId, 'api');
    form?.forDocument(documentId).deselectField();
    return true;
  }

  if (commands && (selected.length === 1 || tracked)) {
    commands.execute('annotation:delete-selected', documentId, 'api');
    form?.forDocument(documentId).deselectField();
    return true;
  }

  if (tracked) {
    scope.deleteAnnotation(tracked.object.pageIndex, tracked.object.id);
    form?.forDocument(documentId).deselectField();
    return true;
  }

  if (fallback) {
    scope.deleteAnnotation(fallback.pageIndex, fallback.id);
    form?.forDocument(documentId).deselectField();
    return true;
  }

  return false;
}

export function hasDeletableSelection(registry: EditorRegistry, documentId: string): boolean {
  return readObjectSelection(registry, documentId) !== null;
}
