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

interface EmbedRect {
  origin: { x: number; y: number };
  size: { width: number; height: number };
}

interface FormattedSelection {
  pageIndex: number;
  rect: EmbedRect;
  segmentRects: EmbedRect[];
}

interface SelectionCapability {
  getBoundingRects(documentId?: string): { page: number; rect: EmbedRect }[];
  getFormattedSelection(documentId?: string): FormattedSelection[];
  getSelectedText(documentId?: string): { toPromise(): Promise<string[]> };
  forDocument(documentId: string): {
    onSelectionChange?: EventHook<unknown>;
  };
  onSelectionChange?: EventHook<{ documentId: string }>;
}

function plugin<T>(registry: EditorRegistry, id: string): T | null {
  const entry = registry.getPlugin(id) as PluginProvides;
  return (entry?.provides?.() as T | undefined) ?? null;
}

/** PDF user-space rect as returned by embedpdf (origin bottom-left, Y grows upward). */
export interface PdfUserRect {
  x: number;
  y: number;
  width: number;
  height: number;
}

export interface SelectionDeletePage {
  page: number;
  rects: PdfUserRect[];
}

function embedRectToPdfUser(rect: EmbedRect): PdfUserRect {
  return {
    x: rect.origin.x,
    y: rect.origin.y,
    width: rect.size.width,
    height: rect.size.height,
  };
}

function normalizePageNumber(pageIndex: number): number {
  return pageIndex >= 1 ? pageIndex : pageIndex + 1;
}

function readDeletePages(selection: SelectionCapability, documentId: string): SelectionDeletePage[] {
  try {
    const formatted = selection.getFormattedSelection(documentId);
    if (formatted.length > 0) {
      return formatted
        .map((entry) => ({
          page: normalizePageNumber(entry.pageIndex),
          rects: (entry.segmentRects.length > 0 ? entry.segmentRects : [entry.rect]).map(embedRectToPdfUser),
        }))
        .filter((entry) => entry.rects.length > 0);
    }
  } catch {
    /* fall through */
  }

  const bounds = selection.getBoundingRects(documentId);
  if (bounds.length === 0) {
    return [];
  }

  const byPage = new Map<number, PdfUserRect[]>();
  for (const entry of bounds) {
    const page = normalizePageNumber(entry.page);
    const rect = embedRectToPdfUser(entry.rect);
    const list = byPage.get(page) ?? [];
    list.push(rect);
    byPage.set(page, list);
  }

  return [...byPage.entries()].map(([page, rects]) => ({ page, rects }));
}

function unionBoundingRects(bounds: { page: number; rect: EmbedRect }[]): {
  page: number;
  rect: EmbedRect;
} | null {
  if (bounds.length === 0) {
    return null;
  }

  const page = bounds[0].page;
  let x0 = bounds[0].rect.origin.x;
  let y0 = bounds[0].rect.origin.y;
  let x1 = x0 + bounds[0].rect.size.width;
  let y1 = y0 + bounds[0].rect.size.height;

  for (const entry of bounds.slice(1)) {
    if (entry.page !== page) {
      continue;
    }
    const { origin, size } = entry.rect;
    x0 = Math.min(x0, origin.x);
    y0 = Math.min(y0, origin.y);
    x1 = Math.max(x1, origin.x + size.width);
    y1 = Math.max(y1, origin.y + size.height);
  }

  return {
    page,
    rect: {
      origin: { x: x0, y: y0 },
      size: { width: x1 - x0, height: y1 - y0 },
    },
  };
}

export interface LineSelectOnDoubleClickOptions {
  /** Called in capture phase before embedpdf handles dblclick (e.g. enable native text selection). */
  onPrepareLineSelect?: () => void;
}

/**
 * embedpdf selects a word on dblclick and expects a follow-up click (within 500 ms)
 * to expand to the full line. The second click of a native double-click fires
 * before dblclick, so line selection never runs — we synthesize that click.
 *
 * Native text selection is often disabled in Select tool (to allow dragging objects);
 * use `onPrepareLineSelect` to re-enable it for the duration of the gesture.
 */
export function installLineSelectOnDoubleClick(
  viewerRoot: HTMLElement,
  options?: LineSelectOnDoubleClickOptions,
): () => void {
  const onDoubleClick = (event: MouseEvent) => {
    const target = event.target as Element | null;
    if (target?.closest('input, textarea, select, [contenteditable="true"]')) {
      return;
    }

    options?.onPrepareLineSelect?.();

    const { clientX, clientY } = event;
    window.setTimeout(() => {
      const clickTarget = document.elementFromPoint(clientX, clientY) ?? viewerRoot;
      clickTarget.dispatchEvent(
        new MouseEvent('click', {
          bubbles: true,
          cancelable: true,
          view: window,
          clientX,
          clientY,
          button: 0,
        }),
      );
    }, 0);
  };

  // Capture: enable selection before embedpdf's dblclick handler runs.
  viewerRoot.addEventListener('dblclick', onDoubleClick, true);
  return () => viewerRoot.removeEventListener('dblclick', onDoubleClick, true);
}

/** Selected text region in embedpdf PDF coordinates (1-based page). */
export interface SelectionSnapshot {
  page: number;
  text: string;
  x: number;
  y: number;
  bbox: [number, number, number, number];
  deletePages: SelectionDeletePage[];
}

function buildSnapshot(
  page: number,
  rect: EmbedRect,
  text: string,
  deletePages: SelectionDeletePage[],
): SelectionSnapshot {
  const x0 = rect.origin.x;
  const y0 = rect.origin.y;
  const x1 = x0 + rect.size.width;
  const y1 = y0 + rect.size.height;

  return {
    page: normalizePageNumber(page),
    text,
    x: rect.origin.x,
    y: rect.origin.y,
    bbox: [x0, y0, x1, y1],
    deletePages,
  };
}

export async function readSelectionSnapshot(
  registry: EditorRegistry,
  documentId: string,
): Promise<SelectionSnapshot | null> {
  const selection = plugin<SelectionCapability>(registry, 'selection');
  if (!selection) {
    return null;
  }

  const deletePages = readDeletePages(selection, documentId);
  const bounds = selection.getBoundingRects(documentId);
  const merged = unionBoundingRects(bounds);
  if (!merged && deletePages.length === 0) {
    return null;
  }

  let text = '';
  try {
    const parts = await selection.getSelectedText(documentId).toPromise();
    text = parts.join(' ').trim();
  } catch {
    text = '';
  }

  const page = merged?.page ?? deletePages[0]?.page ?? 0;
  const rect = merged?.rect ?? {
    origin: { x: deletePages[0]?.rects[0]?.x ?? 0, y: deletePages[0]?.rects[0]?.y ?? 0 },
    size: {
      width: deletePages[0]?.rects[0]?.width ?? 0,
      height: deletePages[0]?.rects[0]?.height ?? 0,
    },
  };

  return buildSnapshot(page, rect, text, deletePages);
}

export function readSelectionBounds(
  registry: EditorRegistry,
  documentId: string,
): SelectionSnapshot | null {
  const selection = plugin<SelectionCapability>(registry, 'selection');
  if (!selection) {
    return null;
  }

  const deletePages = readDeletePages(selection, documentId);
  const bounds = selection.getBoundingRects(documentId);
  const merged = unionBoundingRects(bounds);
  if (!merged && deletePages.length === 0) {
    return null;
  }

  const page = merged?.page ?? deletePages[0]?.page ?? 0;
  const rect = merged?.rect ?? {
    origin: { x: deletePages[0]?.rects[0]?.x ?? 0, y: deletePages[0]?.rects[0]?.y ?? 0 },
    size: {
      width: deletePages[0]?.rects[0]?.width ?? 0,
      height: deletePages[0]?.rects[0]?.height ?? 0,
    },
  };

  return buildSnapshot(page, rect, '', deletePages);
}

/** Baseline insert point and estimated font size for replacing native text. */
export function nativeTextReplacePlacement(snapshot: SelectionSnapshot): {
  page: number;
  x: number;
  y: number;
  fontSize: number;
} {
  const [x0, y0, x1, y1] = snapshot.bbox;
  const height = Math.abs(y1 - y0);
  return {
    page: snapshot.page,
    x: x0,
    y: y0,
    fontSize: Math.round(Math.min(72, Math.max(8, height * 0.85))),
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

  const globalUnsub = subscribeHook(selection.onSelectionChange, (event) => {
    if (event.documentId === documentId) {
      void refresh();
    }
  });
  if (globalUnsub) {
    unsubs.push(globalUnsub);
  }

  const scope = selection.forDocument(documentId);
  const scopeUnsub = subscribeHook(scope.onSelectionChange, () => { void refresh(); });
  if (scopeUnsub) {
    unsubs.push(scopeUnsub);
  }

  void refresh();

  return () => {
    cancelled = true;
    unsubs.forEach((unsub) => unsub());
  };
}
