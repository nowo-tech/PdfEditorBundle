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
    case 'remove_xobject':
      return t('draft.op.remove_xobject', 'Remove page object on page %page%').replace('%page%', String(op.page));
    case 'add_acroform_field':
      return t('draft.op.add_acroform_field', 'Add form field on page %page%').replace('%page%', String(op.page));
    default:
      if (page) {
        return `${op.op} · p${page}`;
      }
      return op.op;
  }
}
