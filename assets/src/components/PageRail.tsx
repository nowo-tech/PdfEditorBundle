import { useEffect, useRef } from 'react';

interface Props {
  currentPage: number;
  totalPages: number;
  disabled: boolean;
  i18n: Record<string, string>;
  thumbUrls: Record<number, string>;
  onSelectPage: (page: number) => void;
  onPrev: () => void;
  onNext: () => void;
  onRequestThumb: (page: number) => void;
}

export function PageRail({
  currentPage,
  totalPages,
  disabled,
  i18n,
  thumbUrls,
  onSelectPage,
  onPrev,
  onNext,
  onRequestThumb,
}: Props) {
  const t = (key: string, fallback: string) => i18n[key] ?? fallback;
  const listRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const active = listRef.current?.querySelector('.nowo-pdf-editor-page-thumb.is-active');
    active?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }, [currentPage]);

  return (
    <aside className="nowo-pdf-editor-pages" aria-label={t('pages', 'Pages')}>
      <h2 className="nowo-pdf-editor-pages-title">{t('pages', 'Pages')}</h2>
      <div className="nowo-pdf-editor-pages-list" ref={listRef}>
        {Array.from({ length: totalPages }, (_, index) => {
          const page = index + 1;
          return (
            <PageThumb
              key={page}
              page={page}
              active={page === currentPage}
              disabled={disabled}
              thumbUrl={thumbUrls[page]}
              label={t('pages.thumb', 'Page %page%').replace('%page%', String(page))}
              onSelect={() => onSelectPage(page)}
              onVisible={() => onRequestThumb(page)}
            />
          );
        })}
      </div>
      <nav className="nowo-pdf-editor-paginator" aria-label={t('pages.nav', 'Page navigation')}>
        <button
          type="button"
          className="nowo-ui-btn nowo-ui-btn-tiny nowo-pdf-editor-paginator-btn"
          disabled={disabled || currentPage <= 1}
          aria-label={t('pages.prev', 'Previous page')}
          onClick={onPrev}
        >
          ‹
        </button>
        <span className="nowo-pdf-editor-paginator-status">
          {t('pages.of', 'Page %current% of %total%')
            .replace('%current%', String(currentPage))
            .replace('%total%', String(totalPages))}
        </span>
        <button
          type="button"
          className="nowo-ui-btn nowo-ui-btn-tiny nowo-pdf-editor-paginator-btn"
          disabled={disabled || currentPage >= totalPages}
          aria-label={t('pages.next', 'Next page')}
          onClick={onNext}
        >
          ›
        </button>
      </nav>
    </aside>
  );
}

interface ThumbProps {
  page: number;
  active: boolean;
  disabled: boolean;
  thumbUrl?: string;
  label: string;
  onSelect: () => void;
  onVisible: () => void;
}

function PageThumb({ page, active, disabled, thumbUrl, label, onSelect, onVisible }: ThumbProps) {
  const ref = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    const node = ref.current;
    if (!node || thumbUrl) {
      return undefined;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          onVisible();
        }
      },
      { rootMargin: '120px' },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [onVisible, thumbUrl]);

  return (
    <button
      ref={ref}
      type="button"
      className={`nowo-pdf-editor-page-thumb${active ? ' is-active' : ''}`}
      disabled={disabled}
      aria-label={label}
      aria-current={active ? 'page' : undefined}
      onClick={onSelect}
    >
      {thumbUrl ? (
        <img src={thumbUrl} alt="" loading="lazy" />
      ) : (
        <span className="nowo-pdf-editor-page-thumb-placeholder">{page}</span>
      )}
      <span className="nowo-pdf-editor-page-thumb-label">{page}</span>
    </button>
  );
}
