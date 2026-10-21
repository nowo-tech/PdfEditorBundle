interface Props {
  currentPage: number;
  totalPages: number;
  disabled: boolean;
  i18n: Record<string, string>;
  onZoomIn: () => void;
  onZoomOut: () => void;
  onZoomFit: () => void;
}

export function ViewportChrome({
  currentPage,
  totalPages,
  disabled,
  i18n,
  onZoomIn,
  onZoomOut,
  onZoomFit,
}: Props) {
  const t = (key: string, fallback: string) => i18n[key] ?? fallback;

  return (
    <div className="nowo-pdf-editor-viewport-chrome">
      <span className="nowo-pdf-editor-viewport-title">
        {t('viewport.page', 'Document page %current% of %total%')
          .replace('%current%', String(currentPage))
          .replace('%total%', String(totalPages))}
      </span>
      <div className="nowo-pdf-editor-viewport-zoom" role="group" aria-label={t('viewport.zoom', 'Zoom')}>
        <button
          type="button"
          className="nowo-ui-btn nowo-ui-btn-tiny nowo-pdf-editor-zoom-btn"
          disabled={disabled}
          aria-label={t('zoom.out', 'Zoom out')}
          onClick={onZoomOut}
        >
          −
        </button>
        <button
          type="button"
          className="nowo-ui-btn nowo-ui-btn-tiny nowo-pdf-editor-zoom-btn"
          disabled={disabled}
          aria-label={t('zoom.fit', 'Fit page')}
          onClick={onZoomFit}
        >
          {t('zoom.fit_short', 'Fit')}
        </button>
        <button
          type="button"
          className="nowo-ui-btn nowo-ui-btn-tiny nowo-pdf-editor-zoom-btn"
          disabled={disabled}
          aria-label={t('zoom.in', 'Zoom in')}
          onClick={onZoomIn}
        >
          +
        </button>
      </div>
    </div>
  );
}
