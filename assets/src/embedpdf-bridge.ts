import type { EditorRegistry } from './types';

type PluginProvides = { provides?: () => unknown } | null | undefined;

interface CommandsCapability {
  execute(commandId: string, documentId?: string, source?: 'keyboard' | 'ui' | 'api'): void;
}

interface DocumentManagerCapability {
  getActiveDocumentId(): string | null;
}

interface ScrollCapability {
  getCurrentPage(): number;
  forDocument(documentId: string): { getCurrentPage(): number };
  onPageChange: { on(handler: (event: { documentId: string; pageNumber: number }) => void): () => void };
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
  return plugin<DocumentManagerCapability>(registry, 'document-manager')?.getActiveDocumentId() ?? null;
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
  if (!scroll?.onPageChange?.on) {
    return () => undefined;
  }
  onPage(readCurrentPage(registry, documentId));
  return scroll.onPageChange.on((event) => {
    if (event.documentId === documentId) {
      onPage(event.pageNumber);
    }
  });
}

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
      commands.execute('mode:view', doc, 'api');
      commands.execute('pointer:toggle', doc, 'api');
      break;
    case 'text':
      commands.execute('mode:annotate', doc, 'api');
      commands.execute('annotation:add-text', doc, 'api');
      break;
    case 'form':
      commands.execute('mode:form', doc, 'api');
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
}
