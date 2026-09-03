import { PDFDocument, StandardFonts, degrees, rgb } from 'pdf-lib';
import type { EditorOp, EditorRegistry, ExportPlugin, WorkspaceConfig } from '../types';
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
      case 'add_acroform_field': {
        const page = doc.getPage(op.page - 1);
        const form = doc.getForm();
        const field = form.createTextField(op.name);
        const [x0, y0, x1, y1] = op.bbox;
        field.addToPage(page, { x: x0, y: y0, width: x1 - x0, height: y1 - y0 });
        field.setText('');
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
  let bytes = await exportFromEmbedPdf(registry, config.workspaceId, config.pdfUrl);
  if (pending.length > 0) {
    bytes = await applyClientOps(bytes, pending);
  }

  const response = await fetch(config.applyUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/pdf',
      'X-CSRF-TOKEN': config.csrfToken,
    },
    body: bytes,
  });

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { error?: string };
    throw new Error(body.error ?? 'Save failed');
  }
}
