import type { EditorRegistry } from './types';

type PluginProvides = { provides?: () => unknown } | null | undefined;

interface EmbedRect {
  origin: { x: number; y: number };
  size: { width: number; height: number };
}

interface SelectionCapability {
  getBoundingRects(documentId?: string): { page: number; rect: EmbedRect }[];
  getSelectedText(documentId?: string): { toPromise(): Promise<string[]> };
  forDocument(documentId: string): {
    onSelectionChange?: { on(handler: (selection: unknown) => void): () => void };
  };
  onSelectionChange?: {
    on(handler: (event: { documentId: string }) => void): () => void;
  };
}

function plugin<T>(registry: EditorRegistry, id: string): T | null {
  const entry = registry.getPlugin(id) as PluginProvides;
  return (entry?.provides?.() as T | undefined) ?? null;
}

/** Selected text region mapped to pdf-lib page coordinates (1-based page). */
export interface SelectionSnapshot {
  page: number;
  text: string;
  x: number;
  y: number;
}

export async function readSelectionSnapshot(
  registry: EditorRegistry,
  documentId: string,
): Promise<SelectionSnapshot | null> {
  const selection = plugin<SelectionCapability>(registry, 'selection');
  if (!selection) {
    return null;
  }

  const bounds = selection.getBoundingRects(documentId);
  if (bounds.length === 0) {
    return null;
  }

  const { page, rect } = bounds[0];
  let text = '';
  try {
    const parts = await selection.getSelectedText(documentId).toPromise();
    text = parts.join(' ').trim();
  } catch {
    text = '';
  }

  return {
    page: page >= 1 ? page : page + 1,
    text,
    x: rect.origin.x,
    y: rect.origin.y,
  };
}

export function subscribeSelectionChanges(
  registry: EditorRegistry,
  documentId: string,
  onSelection: (snapshot: SelectionSnapshot | null) => void,
): () => void {
  const selection = plugin<SelectionCapability>(registry, 'selection');
  if (!selection) {
    return () => undefined;
  }

  let cancelled = false;

  const refresh = async () => {
    const snapshot = await readSelectionSnapshot(registry, documentId);
    if (!cancelled) {
      onSelection(snapshot);
    }
  };

  const unsubs: Array<() => void> = [];

  if (selection.onSelectionChange?.on) {
    unsubs.push(
      selection.onSelectionChange.on((event) => {
        if (event.documentId === documentId) {
          void refresh();
        }
      }),
    );
  }

  const scope = selection.forDocument(documentId);
  if (scope.onSelectionChange?.on) {
    unsubs.push(scope.onSelectionChange.on(() => { void refresh(); }));
  }

  void refresh();

  return () => {
    cancelled = true;
    unsubs.forEach((unsub) => unsub());
  };
}
