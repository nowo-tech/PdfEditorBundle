import { PDFDocument, StandardFonts, degrees, rgb } from 'pdf-lib';
import type { EditorOp, EditorRegistry, ExportPlugin, WorkspaceConfig } from '../types';
import { getDocumentId } from '../embedpdf-bridge';
import { removeDetectedWatermarks, removePageObjects } from './vensas/page-xobjects';

export async function applyClientOps(source: Uint8Array, ops: EditorOp[]): Promise<Uint8Array> {
  const doc = await PDFDocument.load(source, { ignoreEncryption: true });
  const font = await doc.embedFont(StandardFonts.Helvetica);

  for (const op of ops) {
    switch (op.op) {
      case 'insert_text': {
        const page = doc.getPage(op.page - 1);
        page.drawText(op.text, {
          x: op.x,
          y: op.y,
          size: op.fontSize ?? 12,
          font,
          color: rgb(0, 0, 0),
        });
        break;
      }
      case 'add_watermark': {
        const opacity = op.opacity ?? 0.18;
        for (const page of doc.getPages()) {
          const { width, height } = page.getSize();
          page.drawText(op.text, {
            x: width * 0.15,
            y: height * 0.5,
            size: 48,
            font,
            color: rgb(0.5, 0.5, 0.5),
            opacity,
            rotate: degrees(-35),
          });
        }
        break;
      }
      case 'rotate_page': {
        const page = doc.getPage(op.page - 1);
        page.setRotation(degrees((page.getRotation().angle + op.degrees) % 360));
        break;
      }
      case 'remove_detected_watermarks': {
        for (const page of doc.getPages()) {
          removeDetectedWatermarks(page);
        }
        break;
      }
      case 'remove_xobject': {
        const page = doc.getPage(op.page - 1);
        const [x0, y0, x1, y1] = op.rect;
        removePageObjects(page, [{ x: x0, y: y0, width: x1 - x0, height: y1 - y0 }]);
        break;
      }
      case 'delete_text': {
        const page = doc.getPage(op.page - 1);
        const pad = 2;
        for (const rect of op.rects) {
          if (rect.width <= 0 || rect.height <= 0) {
            continue;
          }
          // embedpdf selection rects are already in PDF user space (origin bottom-left).
          page.drawRectangle({
            x: rect.x - pad,
            y: rect.y - pad,
            width: rect.width + pad * 2,
            height: rect.height + pad * 2,
            color: rgb(1, 1, 1),
            borderWidth: 0,
          });
        }
        break;
      }
      case 'redact_text':
        break;
      case 'add_acroform_field': {
        const page = doc.getPage(op.page - 1);
        const form = doc.getForm();
        const field = form.createTextField(op.name);
        const [x0, y0, x1, y1] = op.bbox;
        field.addToPage(page, { x: x0, y: y0, width: x1 - x0, height: y1 - y0 });
        field.setText('');
        break;
      }
      case 'delete_page': {
        if (doc.getPageCount() > 1) {
          doc.removePage(op.page - 1);
        }
        break;
      }
      case 'insert_blank_page': {
        const refIndex = Math.min(Math.max(op.afterPage, 1), doc.getPageCount()) - 1;
        const { width, height } = doc.getPage(refIndex).getSize();
        doc.insertPage(op.afterPage, [width, height]);
        break;
      }
      case 'duplicate_page': {
        const index = op.page - 1;
        const [copied] = await doc.copyPages(doc, [index]);
        doc.insertPage(index + 1, copied);
        break;
      }
      default:
        break;
    }
  }

  return doc.save();
}

export async function exportFromEmbedPdf(
  registry: EditorRegistry | null,
  documentId: string,
  fallbackUrl: string,
): Promise<Uint8Array> {
  if (registry) {
    try {
      const plugin = registry.getPlugin('export')?.provides?.() as ExportPlugin | undefined;
      if (plugin?.forDocument) {
        const bytes = await plugin.forDocument(documentId).saveAsCopy().toPromise();
        if (bytes && bytes.byteLength > 0) {
          return bytes;
        }
      }
    } catch {
      /* fall through */
    }
  }

  const response = await fetch(fallbackUrl, { credentials: 'same-origin' });
  if (!response.ok) {
    throw new Error('Could not load the PDF.');
  }
  return new Uint8Array(await response.arrayBuffer());
}

export async function commitDraft(
  registry: EditorRegistry | null,
  config: WorkspaceConfig,
  pending: EditorOp[],
): Promise<void> {
  const documentId = (registry ? getDocumentId(registry) : null) ?? config.workspaceId;
  let bytes = await exportFromEmbedPdf(registry, documentId, config.pdfUrl);
  if (pending.length > 0) {
    bytes = await applyClientOps(bytes, pending);
  }

  const response = await fetch(config.applyUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/pdf',
      'X-CSRF-TOKEN': config.csrfToken,
    },
    body: new Blob([bytes.slice()], { type: 'application/pdf' }),
  });

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { error?: string };
    throw new Error(body.error ?? 'Save failed');
  }
}
