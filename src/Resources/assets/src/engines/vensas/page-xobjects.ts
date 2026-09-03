/**
 * pdf-lib bridge for XObject tools (MIT — adapted from vensas/pdf-editor).
 * @see https://github.com/vensas/pdf-editor
 */

import {
  decodePDFRawStream,
  PDFArray,
  PDFDict,
  PDFName,
  PDFNumber,
  PDFRawStream,
  type PDFPage,
} from 'pdf-lib';
import {
  listXObjectDraws,
  removeXObjectDraws,
  type Matrix6,
  type Rect4,
  type XObjectDraw,
  type XObjectInfo,
} from './content-stream';

const LATIN1 = new TextDecoder('latin1');

export function readPageXObjects(page: PDFPage): Record<string, XObjectInfo> {
  const result: Record<string, XObjectInfo> = {};
  const resources = page.node.Resources();
  if (!resources) return result;
  const xobjects = resources.lookupMaybe(PDFName.of('XObject'), PDFDict);
  if (!xobjects) return result;

  for (const [key] of xobjects.entries()) {
    const stream = xobjects.lookup(key);
    const dict =
      stream instanceof PDFRawStream ? stream.dict : stream instanceof PDFDict ? stream : undefined;
    if (!dict) continue;
    const name = key.toString().slice(1);
    const subtype = dict.get(PDFName.of('Subtype'))?.toString();
    if (subtype === '/Image') {
      result[name] = { type: 'image' };
    } else if (subtype === '/Form') {
      const bboxArray = dict.lookupMaybe(PDFName.of('BBox'), PDFArray);
      const bbox = bboxArray
        ? (numbers(bboxArray, 4) as [number, number, number, number])
        : undefined;
      const matrixArray = dict.lookupMaybe(PDFName.of('Matrix'), PDFArray);
      const matrix = matrixArray ? (numbers(matrixArray, 6) as Matrix6) : undefined;
      result[name] = {
        type: 'form',
        bbox: bbox ?? [0, 0, 1, 1],
        ...(matrix ? { matrix } : {}),
      };
    }
  }
  return result;
}

function numbers(array: PDFArray, count: number): number[] {
  return Array.from({ length: count }, (_, i) => {
    const value = array.get(i);
    return value instanceof PDFNumber ? value.asNumber() : Number.NaN;
  });
}

export function readPageContent(page: PDFPage): string {
  const context = page.doc.context;
  const contents = page.node.get(PDFName.of('Contents'));
  if (!contents) return '';
  const resolved = context.lookup(contents);
  const streams =
    resolved instanceof PDFArray
      ? Array.from({ length: resolved.size() }, (_, idx) => context.lookup(resolved.get(idx)))
      : [resolved];
  return streams
    .filter((stream): stream is PDFRawStream => stream instanceof PDFRawStream)
    .map((stream) => LATIN1.decode(decodePDFRawStream(stream).decode()))
    .join('\n');
}

export function pageObjectDraws(page: PDFPage): XObjectDraw[] {
  return listXObjectDraws(readPageContent(page), readPageXObjects(page));
}

export function removePageObjects(page: PDFPage, targets: readonly Rect4[], tolerance = 2): number {
  if (targets.length === 0) return 0;
  const content = readPageContent(page);
  const { content: edited, removed } = removeXObjectDraws(
    content,
    targets,
    readPageXObjects(page),
    tolerance,
  );
  if (removed === 0) return 0;

  writePageContent(page, edited);
  return removed;
}

export function removeDetectedWatermarks(page: PDFPage): number {
  let content = readPageContent(page);
  let removed = 0;

  const textResult = removeTextWatermarkBlocks(content);
  content = textResult.content;
  removed += textResult.removed;

  const formDraws = listXObjectDraws(content, readPageXObjects(page)).filter((d) => d.type === 'form');
  if (formDraws.length > 0) {
    const xobjResult = removeXObjectDraws(
      content,
      formDraws.map((d) => d.rect),
      readPageXObjects(page),
    );
    content = xobjResult.content;
    removed += xobjResult.removed;
  }

  if (removed === 0) {
    return 0;
  }

  writePageContent(page, content);
  return removed;
}

const WATERMARK_TOKENS = [
  'DRAFT',
  'BORRADOR',
  'CONFIDENTIAL',
  'CONFIDENCIAL',
  'COPY',
  'SAMPLE',
  'WATERMARK',
  'PREVIEW',
];

/** pdf-lib uses `/FontName 72 Tf`; older heuristic expected `72 Tf` only. */
function readFontSizeFromBlock(block: string): number {
  const named = block.match(/\/[^\s]+\s+(\d+(?:\.\d+)?)\s+Tf/);
  if (named) {
    return Number.parseFloat(named[1]);
  }
  const plain = block.match(/(\d+(?:\.\d+)?)\s+Tf/);
  return plain ? Number.parseFloat(plain[1]) : 0;
}

function hexToAscii(hex: string): string {
  const clean = hex.replace(/\s/g, '');
  if (clean.length === 0 || clean.length % 2 !== 0) {
    return '';
  }
  let out = '';
  for (let i = 0; i < clean.length; i += 2) {
    out += String.fromCharCode(parseInt(clean.slice(i, i + 2), 16));
  }
  return out;
}

function blockHasWatermarkText(block: string): boolean {
  const fontSize = readFontSizeFromBlock(block);

  for (const token of WATERMARK_TOKENS) {
    if (block.includes(`(${token})`)) {
      return true;
    }
  }

  for (const match of block.matchAll(/<([0-9A-Fa-f\s]+)>\s*Tj/g)) {
    const ascii = hexToAscii(match[1]).trim();
    const upper = ascii.toUpperCase();
    for (const token of WATERMARK_TOKENS) {
      if (upper === token) {
        return true;
      }
      if (fontSize >= 28 && upper.includes(token) && upper.length <= token.length + 6) {
        return true;
      }
    }
  }

  return false;
}

function removeOrphanGraphicsBlocks(content: string): { content: string; removed: number } {
  let removed = 0;
  const result = content.replace(/q\n(?:\/[^\n]+\n)+Q(?:\n|$)/g, (block) => {
    if (block.includes('BT') || block.includes(' Tj') || block.includes(' cm')) {
      return block;
    }
    removed += 1;
    return '';
  });
  return { content: result, removed };
}

/** Strip q…Q / BT…ET blocks that paint watermark labels (DRAFT, CONFIDENTIAL, …). */
export function removeTextWatermarkBlocks(content: string): { content: string; removed: number } {
  let removed = 0;
  const normalized = content.replace(/\r\n/g, '\n');

  let result = normalized.replace(/q\n([\s\S]*?)\nQ(?:\n|$)/g, (full) => {
    if (!blockHasWatermarkText(full)) {
      return full;
    }
    removed += 1;
    return '';
  });

  result = result.replace(/BT\n([\s\S]*?)\nET(?:\n|$)/g, (full) => {
    if (!blockHasWatermarkText(full)) {
      return full;
    }
    const fontSize = readFontSizeFromBlock(full);
    if (fontSize >= 28 || blockHasWatermarkText(full)) {
      removed += 1;
      return '';
    }
    return full;
  });

  const orphans = removeOrphanGraphicsBlocks(result);
  result = orphans.content;
  removed += orphans.removed;

  return { content: result, removed };
}

function writePageContent(page: PDFPage, content: string): void {
  const context = page.doc.context;
  const newStream = context.flateStream(content);
  const ref = context.register(newStream);
  page.node.set(PDFName.of('Contents'), ref);
}
