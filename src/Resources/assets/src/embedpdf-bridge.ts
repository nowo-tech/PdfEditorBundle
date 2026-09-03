import type { EditorRegistry } from './types';
import { activateObjectSelectMode, enableObjectEditing } from './object-selection-bridge';

type PluginProvides = { provides?: () => unknown } | null | undefined;

/** embedpdf EventHook: subscribe via hook(listener), not hook.on(listener). */
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

interface CommandsCapability {
  execute(commandId: string, documentId?: string, source?: 'keyboard' | 'ui' | 'api'): void;
}

interface DocumentManagerCapability {
  getActiveDocumentId(): string | null;
  getActiveDocumentIdOrNull?(): string | null;
  onDocumentOpened?: EventHook<{ documentId: string }>;
}

interface ScrollCapability {
  getCurrentPage(): number;
  getTotalPages(): number;
  forDocument(documentId: string): {
    getCurrentPage(): number;
    getTotalPages(): number;
    scrollToPage(options: { pageNumber: number; behavior?: 'instant' | 'smooth' | 'auto' }): void;
    scrollToNextPage(behavior?: 'instant' | 'smooth' | 'auto'): void;
    scrollToPreviousPage(behavior?: 'instant' | 'smooth' | 'auto'): void;
  };
  onPageChange: EventHook<{ documentId: string; pageNumber: number; totalPages: number }>;
  onLayoutReady?: EventHook<{ documentId: string; totalPages: number }>;
}

interface ZoomCapability {
  forDocument(documentId: string): {
    zoomIn(): void;
    zoomOut(): void;
    requestZoom(level: 'fit-page' | 'fit-width' | 'automatic' | number): void;
  };
}

interface ThumbnailCapability {
  forDocument(documentId: string): {
    renderThumb(pageIdx: number, dpr: number): { toPromise(): Promise<Blob> };
  };
}

interface RedactionCapability {
  forDocument(documentId: string): { endRedact(): void; enableRedact(): void };
}

interface UiCapability {
  getSchema(): { toolbars?: Record<string, Record<string, unknown>> };
  mergeSchema(partial: { toolbars?: Record<string, Record<string, unknown>> }): void;
}

function plugin<T>(registry: EditorRegistry, id: string): T | null {
  const entry = registry.getPlugin(id) as PluginProvides;
  return (entry?.provides?.() as T | undefined) ?? null;
}

export function getDocumentId(registry: EditorRegistry): string | null {
  const dm = plugin<DocumentManagerCapability>(registry, 'document-manager');
  if (!dm) {
    return null;
  }
  if (typeof dm.getActiveDocumentIdOrNull === 'function') {
    return dm.getActiveDocumentIdOrNull();
  }
  try {
    return dm.getActiveDocumentId() ?? null;
  } catch {
    return null;
  }
}

export function subscribeActiveDocumentId(
  registry: EditorRegistry,
  onDocumentId: (documentId: string) => void,
): () => void {
  const dm = plugin<DocumentManagerCapability>(registry, 'document-manager');
  if (!dm) {
    return () => undefined;
  }
  const current = getDocumentId(registry);
  if (current) {
    onDocumentId(current);
  }
  const unsub = subscribeHook(dm.onDocumentOpened, (event) => onDocumentId(event.documentId));
  return unsub ?? (() => undefined);
}

export function hideEmbedPdfToolbar(registry: EditorRegistry): void {
  const ui = plugin<UiCapability>(registry, 'ui');
  if (!ui) {
    return;
  }
  const schema = ui.getSchema();
  const toolbar = schema.toolbars?.['main-toolbar'];
  if (!toolbar) {
    return;
  }
  ui.mergeSchema({
    toolbars: {
      'main-toolbar': {
        ...toolbar,
        items: [],
      },
    },
  });
}

export function readTotalPages(registry: EditorRegistry, documentId: string | null): number {
  const scroll = plugin<ScrollCapability>(registry, 'scroll');
  if (!scroll) {
    return 1;
  }
  if (documentId) {
    return scroll.forDocument(documentId).getTotalPages() || 1;
  }
  return scroll.getTotalPages() || 1;
}

export function scrollToPageNumber(
  registry: EditorRegistry,
  documentId: string,
  pageNumber: number,
): void {
  const scroll = plugin<ScrollCapability>(registry, 'scroll');
  if (!scroll) {
    return;
  }
  scroll.forDocument(documentId).scrollToPage({ pageNumber, behavior: 'smooth' });
}

export function scrollToAdjacentPage(
  registry: EditorRegistry,
  documentId: string,
  direction: 'prev' | 'next',
): void {
  const scroll = plugin<ScrollCapability>(registry, 'scroll');
  if (!scroll) {
    return;
  }
  const scope = scroll.forDocument(documentId);
  if (direction === 'prev') {
    scope.scrollToPreviousPage('smooth');
  } else {
    scope.scrollToNextPage('smooth');
  }
}

export function subscribeTotalPages(
  registry: EditorRegistry,
  documentId: string,
  onTotalPages: (total: number) => void,
): () => void {
  const scroll = plugin<ScrollCapability>(registry, 'scroll');
  if (!scroll) {
    return () => undefined;
  }

  onTotalPages(readTotalPages(registry, documentId));

  const unsubs: Array<() => void> = [];

  const layoutUnsub = subscribeHook(scroll.onLayoutReady, (event) => {
    if (event.documentId === documentId) {
      onTotalPages(event.totalPages);
    }
  });
  if (layoutUnsub) {
    unsubs.push(layoutUnsub);
  }

  const pageUnsub = subscribeHook(scroll.onPageChange, (event) => {
    if (event.documentId === documentId && event.totalPages > 0) {
      onTotalPages(event.totalPages);
    }
  });
  if (pageUnsub) {
    unsubs.push(pageUnsub);
  }

  return () => unsubs.forEach((unsub) => unsub());
}

export function zoomIn(registry: EditorRegistry, documentId: string): void {
  plugin<ZoomCapability>(registry, 'zoom')?.forDocument(documentId).zoomIn();
}

export function zoomOut(registry: EditorRegistry, documentId: string): void {
  plugin<ZoomCapability>(registry, 'zoom')?.forDocument(documentId).zoomOut();
}

export function zoomFitPage(registry: EditorRegistry, documentId: string): void {
  plugin<ZoomCapability>(registry, 'zoom')?.forDocument(documentId).requestZoom('fit-page');
}

export async function renderPageThumbnail(
  registry: EditorRegistry,
  documentId: string,
  pageIndex: number,
): Promise<string | null> {
  const thumbnail = plugin<ThumbnailCapability>(registry, 'thumbnail');
  if (!thumbnail) {
    return null;
  }
  try {
    const blob = await thumbnail
      .forDocument(documentId)
      .renderThumb(pageIndex, window.devicePixelRatio || 1)
      .toPromise();
    return URL.createObjectURL(blob);
  } catch {
    return null;
  }
}

export function readCurrentPage(registry: EditorRegistry, documentId: string | null): number {
  const scroll = plugin<ScrollCapability>(registry, 'scroll');
  if (!scroll) {
    return 1;
  }
  if (documentId) {
    return scroll.forDocument(documentId).getCurrentPage() || 1;
  }
  return scroll.getCurrentPage() || 1;
}

export function subscribePageChanges(
  registry: EditorRegistry,
  documentId: string,
  onPage: (page: number) => void,
): () => void {
  const scroll = plugin<ScrollCapability>(registry, 'scroll');
  if (!scroll) {
    return () => undefined;
  }
  onPage(readCurrentPage(registry, documentId));
  const unsub = subscribeHook(scroll.onPageChange, (event) => {
    if (event.documentId === documentId) {
      onPage(event.pageNumber);
    }
  });
  return unsub ?? (() => undefined);
}

export { activateObjectSelectMode, enableObjectEditing } from './object-selection-bridge';

export function activateEmbedTool(registry: EditorRegistry, tool: string, documentId: string | null): void {
  const commands = plugin<CommandsCapability>(registry, 'commands');
  const redaction = plugin<RedactionCapability>(registry, 'redaction');
  const doc = documentId ?? undefined;

  if (redaction && documentId) {
    try {
      redaction.forDocument(documentId).endRedact();
    } catch {
      /* mode may not be active */
    }
  }

  if (!commands) {
    return;
  }

  switch (tool) {
    case 'select':
      if (documentId) {
        activateObjectSelectMode(registry, documentId);
      } else {
        commands.execute('mode:annotate', doc, 'api');
      }
      break;
    case 'text':
      commands.execute('mode:annotate', doc, 'api');
      commands.execute('annotation:add-text', doc, 'api');
      break;
    case 'form':
      commands.execute('mode:form', doc, 'api');
      if (documentId) {
        plugin<{ forDocument(id: string): { setActiveTool(id: string | null): void } }>(registry, 'annotation')
          ?.forDocument(documentId)
          .setActiveTool(null);
      }
      break;
    case 'annotate':
      commands.execute('mode:annotate', doc, 'api');
      commands.execute('annotation:add-comment', doc, 'api');
      break;
    case 'redact':
      if (redaction && documentId) {
        redaction.forDocument(documentId).enableRedact();
      } else {
        commands.execute('mode:redact', doc, 'api');
      }
      break;
    case 'watermark':
      commands.execute('mode:view', doc, 'api');
      break;
    default:
      commands.execute('mode:view', doc, 'api');
      break;
  }

  if (documentId) {
    enableObjectEditing(registry, documentId);
  }
}
