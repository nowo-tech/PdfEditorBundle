import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { PDFViewer, type PDFViewerRef } from '@embedpdf/react-pdf-viewer';
import type { EditorOp, EditorRegistry, WorkspaceConfig } from './types';
import { commitDraft } from './engines/client-engine';
import {
  activateEmbedTool,
  getDocumentId,
  hideEmbedPdfToolbar,
  readCurrentPage,
  renderPageThumbnail,
  scrollToAdjacentPage,
  scrollToPageNumber,
  subscribePageChanges,
  subscribeTotalPages,
} from './embedpdf-bridge';
import { subscribeSelectionChanges, type SelectionSnapshot } from './selection-bridge';
import { EditorShell } from './components/EditorShell';
import { EditorToolbar } from './components/EditorToolbar';
import { PageRail } from './components/PageRail';
import { PropertiesPanel } from './components/PropertiesPanel';
import { ViewerSkeleton } from './components/ViewerSkeleton';
import './styles/editor.css';

interface Props {
  config: WorkspaceConfig;
}

const UI_HIDDEN_CATEGORIES = [
  'tools',
  'zoom',
  'document',
  'history',
  'mode',
  'annotation',
  'redaction',
  'form',
  'pan',
  'pointer',
  'capture',
  'spread',
  'scroll',
  'rotate',
  'page',
  'search',
  'fullscreen',
  'stamp',
  'signature',
  'selection',
];

export function PdfEditorApp({ config }: Props) {
  const viewerRef = useRef<PDFViewerRef>(null);
  const registryRef = useRef<EditorRegistry | null>(null);
  const documentIdRef = useRef<string | null>(null);
  const thumbUrlsRef = useRef<Record<number, string>>({});
  const [tool, setTool] = useState('select');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [thumbUrls, setThumbUrls] = useState<Record<number, string>>({});
  const [pending, setPending] = useState<EditorOp[]>([]);
  const [selection, setSelection] = useState<SelectionSnapshot | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const t = useCallback(
    (key: string, fallback: string) => config.i18n[key] ?? fallback,
    [config.i18n],
  );

  const revokeThumbs = useCallback(() => {
    Object.values(thumbUrlsRef.current).forEach((url) => URL.revokeObjectURL(url));
    thumbUrlsRef.current = {};
    setThumbUrls({});
  }, []);

  const flash = (message: string) => {
    setToast(message);
    window.setTimeout(() => setToast(null), 2400);
  };

  const queueOp = useCallback(
    (op: EditorOp) => {
      setPending((current) => [...current, op]);
      flash(t('pending.queued', 'Added to draft'));
    },
    [t],
  );

  const handleReady = useCallback(
    (registry: EditorRegistry) => {
      registryRef.current = registry;
      hideEmbedPdfToolbar(registry);
      const docId = getDocumentId(registry) ?? config.workspaceId;
      documentIdRef.current = docId;
      setCurrentPage(readCurrentPage(registry, docId));
      activateEmbedTool(registry, 'select', docId);
      setLoading(false);
    },
    [config.workspaceId],
  );

  useEffect(() => {
    setLoading(true);
    setSelection(null);
    revokeThumbs();
  }, [reloadKey, revokeThumbs]);

  useEffect(() => {
    const registry = registryRef.current;
    const docId = documentIdRef.current;
    if (!registry || !docId) {
      return undefined;
    }
    return subscribePageChanges(registry, docId, setCurrentPage);
  }, [reloadKey, loading]);

  useEffect(() => {
    const registry = registryRef.current;
    const docId = documentIdRef.current;
    if (!registry || !docId) {
      return undefined;
    }
    return subscribeTotalPages(registry, docId, setTotalPages);
  }, [reloadKey, loading]);

  useEffect(() => {
    const registry = registryRef.current;
    const docId = documentIdRef.current;
    if (!registry || !docId || loading) {
      return undefined;
    }
    return subscribeSelectionChanges(registry, docId, setSelection);
  }, [reloadKey, loading]);

  useEffect(() => {
    const registry = registryRef.current;
    const docId = documentIdRef.current;
    if (!registry || !docId) {
      return;
    }
    activateEmbedTool(registry, tool, docId);
  }, [tool]);

  useEffect(() => () => revokeThumbs(), [revokeThumbs]);

  const requestThumb = useCallback(
    async (page: number) => {
      if (thumbUrlsRef.current[page]) {
        return;
      }
      const registry = registryRef.current;
      const docId = documentIdRef.current;
      if (!registry || !docId) {
        return;
      }
      const url = await renderPageThumbnail(registry, docId, page - 1);
      if (!url || thumbUrlsRef.current[page]) {
        if (url) {
          URL.revokeObjectURL(url);
        }
        return;
      }
      thumbUrlsRef.current[page] = url;
      setThumbUrls({ ...thumbUrlsRef.current });
    },
    [],
  );

  const goToPage = useCallback((page: number) => {
    const registry = registryRef.current;
    const docId = documentIdRef.current;
    if (!registry || !docId) {
      return;
    }
    scrollToPageNumber(registry, docId, page);
  }, []);

  const handleToolChange = (next: string) => {
    setTool(next);
    if (next === 'watermark') {
      queueOp({ op: 'add_watermark', text: 'CONFIDENTIAL', opacity: 0.18 });
    }
  };

  const handleAction = async (action: string) => {
    if (action === 'remove-watermarks') {
      queueOp({ op: 'remove_detected_watermarks' });
      return;
    }
    if (action === 'rotate') {
      queueOp({ op: 'rotate_page', page: currentPage, degrees: 90 });
      return;
    }
    if (action === 'discard') {
      setPending([]);
      setSaveError(null);
      setTool('select');
      return;
    }
    if (action === 'commit') {
      setSaving(true);
      setSaveError(null);
      try {
        await commitDraft(registryRef.current, config, pending);
        setPending([]);
        flash(t('pending.saved', 'Saved to PDF'));
        setReloadKey((k) => k + 1);
        setTool('select');
      } catch (error) {
        const message = error instanceof Error ? error.message : t('save.error', 'Save failed');
        setSaveError(message);
        flash(message);
      } finally {
        setSaving(false);
      }
    }
  };

  const viewerConfig = useMemo(
    () => ({
      src: `${config.pdfUrl}${config.pdfUrl.includes('?') ? '&' : '?'}v=${reloadKey}`,
      theme: { preference: 'light' as const },
      tabBar: 'never' as const,
      ui: { disabledCategories: UI_HIDDEN_CATEGORIES },
      commands: { disabledCategories: [] as string[] },
    }),
    [config.pdfUrl, reloadKey],
  );

  const busy = loading || saving;
  const shellHeight = config.layout?.height ?? '100%';

  return (
    <EditorShell
      height={shellHeight}
      toolbar={(
        <EditorToolbar
          tool={tool}
          pendingCount={pending.length}
          saving={saving}
          loading={loading}
          i18n={config.i18n}
          downloadUrl={config.downloadUrl}
          onToolChange={handleToolChange}
          onAction={handleAction}
        />
      )}
      pageRail={(
        <PageRail
          currentPage={currentPage}
          totalPages={totalPages}
          disabled={busy}
          i18n={config.i18n}
          thumbUrls={thumbUrls}
          onSelectPage={goToPage}
          onPrev={() => {
            const registry = registryRef.current;
            const docId = documentIdRef.current;
            if (registry && docId) {
              scrollToAdjacentPage(registry, docId, 'prev');
            }
          }}
          onNext={() => {
            const registry = registryRef.current;
            const docId = documentIdRef.current;
            if (registry && docId) {
              scrollToAdjacentPage(registry, docId, 'next');
            }
          }}
          onRequestThumb={requestThumb}
        />
      )}
      viewport={(
        <section className="nowo-pdf-editor-viewer-wrap" aria-live="polite" aria-busy={loading}>
          {loading ? (
            <ViewerSkeleton message={t('loading', 'Loading PDF preview…')} />
          ) : null}
          <PDFViewer
            ref={viewerRef}
            key={reloadKey}
            config={viewerConfig}
            style={{
              height: '100%',
              width: '100%',
              visibility: loading ? 'hidden' : 'visible',
            }}
            onReady={handleReady}
          />
        </section>
      )}
      sidebar={(
        <PropertiesPanel
          tool={tool}
          currentPage={currentPage}
          selection={selection}
          pending={pending}
          i18n={config.i18n}
          saveError={saveError}
          onQueueOp={queueOp}
          onRemovePending={(index) => setPending((current) => current.filter((_, i) => i !== index))}
        />
      )}
      toast={toast ? <p className="nowo-ui-toast" role="status">{toast}</p> : null}
    />
  );
}
