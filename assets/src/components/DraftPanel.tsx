import type { EditorOp } from '../types';
import { opLabel } from '../op-labels';

interface Props {
  pending: EditorOp[];
  tool: string;
  i18n: Record<string, string>;
  onRemove: (index: number) => void;
}

export function DraftPanel({ pending, tool, i18n, onRemove }: Props) {
  const t = (key: string, fallback: string) => i18n[key] ?? fallback;

  return (
    <aside className="nowo-pdf-editor-props">
      <h2 className="nowo-ui-subtitle">
        {t('pending.title', 'Draft')}{' '}
        <span className="nowo-pdf-editor-pending-count">{pending.length}</span>
      </h2>
      <p className="nowo-ui-muted">{t('pending.help', 'Edits stay in the browser until you save.')}</p>
      {pending.length === 0 ? (
        <p className="nowo-ui-muted">{t('pending.empty', 'No pending changes.')}</p>
      ) : (
        <ul className="nowo-pdf-editor-pending">
          {pending.map((op, index) => (
            <li key={`${op.op}-${index}`}>
              <span>{opLabel(i18n, op)}</span>
              <button
                type="button"
                className="nowo-ui-btn nowo-ui-btn-tiny"
                aria-label={t('pending.remove', 'Remove')}
                onClick={() => onRemove(index)}
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}
      {tool !== 'select' && (
        <p className="nowo-ui-muted nowo-pdf-editor-tool-hint">
          {t(`tool.${tool}_tip`, `Active tool: ${tool}`)}
        </p>
      )}
    </aside>
  );
}
