import type { EditorOp } from './types';

export function opLabel(i18n: Record<string, string>, op: EditorOp, page?: number): string {
  const t = (key: string, fallback: string) => i18n[key] ?? fallback;

  switch (op.op) {
    case 'insert_text':
      return t('draft.op.insert_text', 'Insert text').replace('%page%', String(op.page));
    case 'add_watermark':
      return t('draft.op.add_watermark', 'Add watermark');
    case 'rotate_page':
      return t('draft.op.rotate_page', 'Rotate page %page% clockwise').replace('%page%', String(op.page));
    case 'remove_detected_watermarks':
      return t('draft.op.remove_watermarks', 'Remove detected watermarks');
    case 'delete_text':
      return t('draft.op.delete_text', 'Delete text on page %page%').replace('%page%', String(op.page));
    case 'redact_text':
      return t('draft.op.redact_text', 'Redact text on page %page%').replace('%page%', String(op.page));
    case 'remove_xobject':
      return t('draft.op.remove_xobject', 'Remove page object on page %page%').replace('%page%', String(op.page));
    case 'add_acroform_field':
      return t('draft.op.add_acroform_field', 'Add form field on page %page%').replace('%page%', String(op.page));
    case 'delete_page':
      return t('draft.op.delete_page', 'Delete page %page%').replace('%page%', String(op.page));
    case 'insert_blank_page':
      return t('draft.op.insert_blank_page', 'Insert blank after page %page%').replace('%page%', String(op.afterPage));
    case 'duplicate_page':
      return t('draft.op.duplicate_page', 'Duplicate page %page%').replace('%page%', String(op.page));
    default: {
      const fallback = (op as { op: string }).op;
      if (page) {
        return `${fallback} · p${page}`;
      }
      return fallback;
    }
  }
}
