import { useEffect, useState } from 'react';
import type { EditorOp } from '../types';
import type { ObjectSelection } from '../object-selection-bridge';
import { nativeTextReplacePlacement, type SelectionSnapshot } from '../selection-bridge';
import { opLabel } from '../op-labels';

const FONT_FAMILIES = ['Helvetica', 'Times-Roman', 'Courier'] as const;

interface Props {
  tool: string;
  currentPage: number;
  selection: SelectionSnapshot | null;
  objectSelection: ObjectSelection | null;
  pending: EditorOp[];
  i18n: Record<string, string>;
  saveError: string | null;
  onQueueOp: (op: EditorOp) => void;
  onRemovePending: (index: number) => void;
  onApplyObject: (selection: ObjectSelection, patch: {
    contents: string;
    fontSize: number;
    fontColor: string;
    fontFamily: string;
    fieldName?: string;
  }) => void;
  onDeleteObject: () => void;
  onReplaceNativeText: (text: string, fontSize: number) => void;
}

export function PropertiesPanel({
  tool,
  currentPage,
  selection,
  objectSelection,
  pending,
  i18n,
  saveError,
  onQueueOp,
  onRemovePending,
  onApplyObject,
  onDeleteObject,
  onReplaceNativeText,
}: Props) {
  const t = (key: string, fallback: string) => i18n[key] ?? fallback;

  const [insertText, setInsertText] = useState('New text');
  const [fontSize, setFontSize] = useState(12);

  const [objContents, setObjContents] = useState('');
  const [objFieldName, setObjFieldName] = useState('');
  const [objFontSize, setObjFontSize] = useState(12);
  const [objFontColor, setObjFontColor] = useState('#000000');
  const [objFontFamily, setObjFontFamily] = useState<string>('Helvetica');

  const [nativeText, setNativeText] = useState('');
  const [nativeFontSize, setNativeFontSize] = useState(12);

  useEffect(() => {
    if (!selection?.text) {
      return;
    }
    setNativeText(selection.text);
    setNativeFontSize(nativeTextReplacePlacement(selection).fontSize);
    setInsertText(selection.text);
  }, [selection]);

  useEffect(() => {
    if (!objectSelection) {
      return;
    }
    setObjContents(objectSelection.contents);
    setObjFieldName(objectSelection.fieldName ?? '');
    setObjFontSize(objectSelection.fontSize);
    setObjFontColor(objectSelection.fontColor);
    setObjFontFamily(objectSelection.fontFamily);
  }, [objectSelection]);

  const queueInsertText = () => {
    const page = selection?.page ?? currentPage;
    const x = selection?.x ?? 72;
    const y = selection?.y ?? 72;
    const text = insertText.trim();
    if (!text) {
      return;
    }
    onQueueOp({ op: 'insert_text', page, x, y, text, fontSize });
  };

  const applyObject = () => {
    if (!objectSelection) {
      return;
    }
    onApplyObject(objectSelection, {
      contents: objContents,
      fontSize: objFontSize,
      fontColor: objFontColor,
      fontFamily: objFontFamily,
      fieldName: objectSelection.kind === 'widget' ? objFieldName : undefined,
    });
  };

  const applyObjectIfSelected = () => {
    if (objectSelection) {
      applyObject();
    }
  };

  const showDraftInsert = !objectSelection && !selection?.text && (tool === 'text' || selection !== null);

  return (
    <aside className="nowo-pdf-editor-props">
      <details className="nowo-pdf-editor-props-section" open>
        <summary className="nowo-ui-subtitle">{t('properties', 'Properties')}</summary>

        {objectSelection ? (
          <div className="nowo-pdf-editor-props-form">
            <p className="nowo-ui-muted nowo-pdf-editor-props-kind">
              {objectSelection.kind === 'widget'
                ? t('properties.kind.form', 'Form field')
                : objectSelection.kind === 'note'
                  ? t('properties.kind.note', 'Sticky note')
                  : t('properties.kind.textbox', 'Text box')}
              {' · '}
              {objectSelection.label}
            </p>
            {objectSelection.kind === 'widget' ? (
              <>
                <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-field-name">
                  {t('properties.field_name', 'Field name')}
                </label>
                <input
                  id="nowo-pdf-prop-field-name"
                  className="nowo-ui-input"
                  value={objFieldName}
                  onChange={(event) => setObjFieldName(event.target.value)}
                  onBlur={applyObjectIfSelected}
                />
              </>
            ) : null}
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-obj-text">
              {objectSelection.kind === 'widget'
                ? t('properties.value', 'Value')
                : t('properties.text', 'Text')}
            </label>
            <textarea
              id="nowo-pdf-prop-obj-text"
              className="nowo-ui-input"
              rows={3}
              value={objContents}
              onChange={(event) => setObjContents(event.target.value)}
              onBlur={applyObjectIfSelected}
            />
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-obj-font">
              {t('properties.font_size', 'Font size')}
            </label>
            <input
              id="nowo-pdf-prop-obj-font"
              className="nowo-ui-input"
              type="number"
              min={6}
              max={96}
              value={objFontSize}
              onChange={(event) => setObjFontSize(Number(event.target.value) || 12)}
              onBlur={applyObjectIfSelected}
            />
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-obj-color">
              {t('properties.font_color', 'Color')}
            </label>
            <input
              id="nowo-pdf-prop-obj-color"
              className="nowo-ui-input nowo-pdf-editor-color-input"
              type="color"
              value={objFontColor}
              onChange={(event) => {
                setObjFontColor(event.target.value);
              }}
              onInput={(event) => {
                const next = (event.target as HTMLInputElement).value;
                setObjFontColor(next);
                if (objectSelection) {
                  onApplyObject(objectSelection, {
                    contents: objContents,
                    fontSize: objFontSize,
                    fontColor: next,
                    fontFamily: objFontFamily,
                    fieldName: objectSelection.kind === 'widget' ? objFieldName : undefined,
                  });
                }
              }}
            />
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-obj-family">
              {t('properties.font_family', 'Font')}
            </label>
            <select
              id="nowo-pdf-prop-obj-family"
              className="nowo-ui-input"
              value={objFontFamily}
              onChange={(event) => {
                setObjFontFamily(event.target.value);
                if (objectSelection) {
                  onApplyObject(objectSelection, {
                    contents: objContents,
                    fontSize: objFontSize,
                    fontColor: objFontColor,
                    fontFamily: event.target.value,
                    fieldName: objectSelection.kind === 'widget' ? objFieldName : undefined,
                  });
                }
              }}
            >
              {FONT_FAMILIES.map((family) => (
                <option key={family} value={family}>
                  {family}
                </option>
              ))}
            </select>
            <div className="nowo-pdf-editor-props-actions">
              <button type="button" className="nowo-ui-btn nowo-ui-btn-primary" onClick={applyObject}>
                {t('properties.apply_live', 'Apply')}
              </button>
              <button
                type="button"
                className="nowo-ui-btn"
                onClick={() => onDeleteObject()}
              >
                {t('properties.delete', 'Delete')}
              </button>
            </div>
            <p className="nowo-ui-muted nowo-pdf-editor-props-hint">
              {t('properties.live_help', 'Changes apply in the viewer. Save to PDF to persist.')}
            </p>
          </div>
        ) : selection?.text ? (
          <div className="nowo-pdf-editor-props-form">
            <p className="nowo-ui-muted">
              {t(
                'properties.native_text_help',
                'Native PDF text — edit below and replace. The old text is redacted and new text is stamped on save.',
              )}
            </p>
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-native-text">
              {t('properties.text', 'Text')}
            </label>
            <textarea
              id="nowo-pdf-prop-native-text"
              className="nowo-ui-input"
              rows={3}
              value={nativeText}
              onChange={(event) => setNativeText(event.target.value)}
            />
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-native-font">
              {t('properties.font_size', 'Font size')}
            </label>
            <input
              id="nowo-pdf-prop-native-font"
              className="nowo-ui-input"
              type="number"
              min={6}
              max={72}
              value={nativeFontSize}
              onChange={(event) => setNativeFontSize(Number(event.target.value) || 12)}
            />
            <div className="nowo-pdf-editor-props-actions">
              <button
                type="button"
                className="nowo-ui-btn nowo-ui-btn-primary"
                disabled={!nativeText.trim()}
                onClick={() => onReplaceNativeText(nativeText.trim(), nativeFontSize)}
              >
                {t('properties.replace_text', 'Replace text')}
              </button>
              <button type="button" className="nowo-ui-btn" onClick={() => onDeleteObject()}>
                {t('properties.delete_text', 'Delete selected text')}
              </button>
            </div>
            <p className="nowo-ui-muted nowo-pdf-editor-props-hint">
              {t('properties.native_text_replace_hint', 'Hold Shift while dragging to select native PDF text.')}
            </p>
          </div>
        ) : showDraftInsert ? (
          <div className="nowo-pdf-editor-props-form">
            <p className="nowo-ui-muted">{t('properties.draft_insert_help', 'Queue a pdf-lib text stamp on save.')}</p>
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-text">
              {t('properties.text', 'Text')}
            </label>
            <textarea
              id="nowo-pdf-prop-text"
              className="nowo-ui-input"
              rows={3}
              value={insertText}
              onChange={(event) => setInsertText(event.target.value)}
            />
            <label className="nowo-ui-label" htmlFor="nowo-pdf-prop-font">
              {t('properties.font_size', 'Font size')}
            </label>
            <input
              id="nowo-pdf-prop-font"
              className="nowo-ui-input"
              type="number"
              min={6}
              max={72}
              value={fontSize}
              onChange={(event) => setFontSize(Number(event.target.value) || 12)}
            />
            <button type="button" className="nowo-ui-btn nowo-ui-btn-primary" onClick={queueInsertText}>
              {t('properties.apply', 'Add to draft')}
            </button>
          </div>
        ) : (
          <p className="nowo-ui-muted">
            {t(
              'properties.empty_select',
              'Use Select to click a text box or form field. Drag to move; handles resize. Hold Shift to select native PDF text.',
            )}
          </p>
        )}
      </details>

      <details className="nowo-pdf-editor-props-section" open>
        <summary className="nowo-ui-subtitle">
          {t('pending.title', 'Draft')}{' '}
          <span className="nowo-pdf-editor-pending-count">{pending.length}</span>
        </summary>
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
                  aria-label={t('pending.remove', 'Remove from draft')}
                  onClick={() => onRemovePending(index)}
                >
                  ×
                </button>
              </li>
            ))}
          </ul>
        )}
      </details>

      {saveError ? (
        <p className="nowo-pdf-editor-save-error" role="alert">
          {saveError}
        </p>
      ) : null}
    </aside>
  );
}
