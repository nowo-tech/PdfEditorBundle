import type { EditorRegistry } from './types';

type PluginProvides = { provides?: () => unknown } | null | undefined;

type Task<T> = { toPromise(): Promise<T> };

interface RedactionScope {
  queueCurrentSelectionAsPending(): Task<boolean>;
  commitAllPending(): Task<unknown>;
  clearPending(): void;
}

interface RedactionCapability {
  forDocument(documentId: string): RedactionScope;
}

function plugin<T>(registry: EditorRegistry, id: string): T | null {
  const entry = registry.getPlugin(id) as PluginProvides;
  return (entry?.provides?.() as T | undefined) ?? null;
}

/** Queue the current text selection as a pending redaction (visible in the viewer). */
export async function queueSelectionAsRedaction(
  registry: EditorRegistry,
  documentId: string,
): Promise<boolean> {
  const redaction = plugin<RedactionCapability>(registry, 'redaction');
  if (!redaction) {
    return false;
  }

  try {
    return await redaction.forDocument(documentId).queueCurrentSelectionAsPending().toPromise();
  } catch {
    return false;
  }
}

/** Bake pending redactions into the document before export. */
export async function commitPendingRedactions(
  registry: EditorRegistry,
  documentId: string,
): Promise<boolean> {
  const redaction = plugin<RedactionCapability>(registry, 'redaction');
  if (!redaction) {
    return false;
  }

  try {
    await redaction.forDocument(documentId).commitAllPending().toPromise();
    return true;
  } catch {
    return false;
  }
}

export function clearPendingRedactions(registry: EditorRegistry, documentId: string): void {
  const redaction = plugin<RedactionCapability>(registry, 'redaction');
  if (!redaction) {
    return;
  }

  try {
    redaction.forDocument(documentId).clearPending();
  } catch {
    /* pending may not exist */
  }
}
