import { ACTION_ICONS, TOOL_ICONS } from './icons';

const TOOLS = ['select', 'text', 'form', 'watermark', 'redact'] as const;
const ACTIONS = ['remove-watermarks', 'rotate', 'commit', 'discard'] as const;

type ToolId = (typeof TOOLS)[number];
type ActionId = (typeof ACTIONS)[number];

interface Props {
  tool: string;
  currentPage: number;
  pendingCount: number;
  saving: boolean;
  i18n: Record<string, string>;
  downloadUrl: string;
  onToolChange: (tool: string) => void;
  onAction: (action: string) => void;
}

export function EditorToolbar({
  tool,
  currentPage,
  pendingCount,
  saving,
  i18n,
  downloadUrl,
  onToolChange,
  onAction,
}: Props) {
  const t = (key: string, fallback: string) => i18n[key] ?? fallback;

  return (
    <header className="nowo-pdf-editor-toolbar" role="toolbar" aria-busy={saving}>
      <div className="nowo-pdf-editor-toolbar-group" role="group" aria-label={t('toolbar.tools', 'Tools')}>
        {TOOLS.map((id) => {
          const Icon = TOOL_ICONS[id];
          const tipKey = id === 'redact' ? 'tool.redact_tip' : `tool.${id}_tip`;
          return (
            <button
              key={id}
              type="button"
              className={`nowo-ui-btn nowo-ui-btn-icon${tool === id ? ' is-active' : ''}`}
              title={t(tipKey, id)}
              data-tooltip={t(tipKey, id)}
              aria-label={t(`tool.${id}`, id)}
              aria-pressed={tool === id}
              disabled={saving}
              onClick={() => onToolChange(id)}
            >
              <Icon />
            </button>
          );
        })}
      </div>
      <div className="nowo-pdf-editor-toolbar-meta" aria-live="polite">
        <span className="nowo-pdf-editor-page-badge">
          {t('pages.current', 'Page %page%').replace('%page%', String(currentPage))}
        </span>
      </div>
      <div className="nowo-pdf-editor-toolbar-group" role="group" aria-label={t('toolbar.actions', 'Actions')}>
        {ACTIONS.map((id) => {
          const Icon = ACTION_ICONS[id];
          const tipKey = id === 'remove-watermarks' ? 'action.remove_watermarks_tip' : `action.${id}_tip`;
          const isPrimary = id === 'commit';
          return (
            <button
              key={id}
              type="button"
              className={`nowo-ui-btn nowo-ui-btn-icon${isPrimary ? ' nowo-ui-btn-primary' : ''}`}
              title={t(tipKey, id)}
              data-tooltip={t(tipKey, id)}
              aria-label={t(`action.${id === 'remove-watermarks' ? 'remove_watermarks' : id}`, id)}
              disabled={
                saving ||
                (id === 'commit' && pendingCount === 0) ||
                (id === 'discard' && pendingCount === 0)
              }
              onClick={() => onAction(id)}
            >
              {saving && id === 'commit' ? <span className="nowo-pdf-editor-spinner" aria-hidden="true" /> : <Icon />}
              {isPrimary && pendingCount > 0 ? (
                <span className="nowo-pdf-editor-badge">{pendingCount}</span>
              ) : null}
            </button>
          );
        })}
        <a
          className="nowo-ui-btn nowo-ui-btn-icon"
          href={downloadUrl}
          title={t('action.download_tip', 'Download')}
          data-tooltip={t('action.download_tip', 'Download')}
          aria-label={t('action.download', 'Download')}
        >
          <ACTION_ICONS.download />
        </a>
      </div>
    </header>
  );
}

export type { ToolId, ActionId };
