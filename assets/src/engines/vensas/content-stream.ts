/**
 * Minimal PDF content-stream surgery (MIT — adapted from vensas/pdf-editor).
 * @see https://github.com/vensas/pdf-editor
 */

export type Matrix6 = [number, number, number, number, number, number];

export interface Rect4 {
  x: number;
  y: number;
  width: number;
  height: number;
}

export type XObjectInfo =
  | { type: 'image' }
  | { type: 'form'; bbox: [number, number, number, number]; matrix?: Matrix6 };

export interface RemovalResult {
  content: string;
  removed: number;
}

export interface XObjectDraw {
  name: string;
  type: 'image' | 'form';
  rect: Rect4;
}

const IDENTITY: Matrix6 = [1, 0, 0, 1, 0, 0];

function multiply(m: Matrix6, t: Matrix6): Matrix6 {
  return [
    m[0] * t[0] + m[2] * t[1],
    m[1] * t[0] + m[3] * t[1],
    m[0] * t[2] + m[2] * t[3],
    m[1] * t[2] + m[3] * t[3],
    m[0] * t[4] + m[2] * t[5] + m[4],
    m[1] * t[4] + m[3] * t[5] + m[5],
  ];
}

function apply(m: Matrix6, x: number, y: number): [number, number] {
  return [m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]];
}

export function paintedBBox(ctm: Matrix6, info: XObjectInfo): Rect4 {
  let corners: [number, number][];
  if (info.type === 'image') {
    corners = [
      [0, 0],
      [1, 0],
      [1, 1],
      [0, 1],
    ];
  } else {
    const m = info.matrix ? multiply(ctm, info.matrix) : ctm;
    const [bx0, by0, bx1, by1] = info.bbox;
    const formCorners: [number, number][] = [
      [bx0, by0],
      [bx1, by0],
      [bx1, by1],
      [bx0, by1],
    ];
    return bboxOfPoints(formCorners.map(([x, y]) => apply(m, x, y)));
  }
  return bboxOfPoints(corners.map(([x, y]) => apply(ctm, x, y)));
}

function bboxOfPoints(points: [number, number][]): Rect4 {
  const xs = points.map((p) => p[0]);
  const ys = points.map((p) => p[1]);
  const x = Math.min(...xs);
  const y = Math.min(...ys);
  return { x, y, width: Math.max(...xs) - x, height: Math.max(...ys) - y };
}

function rectsMatch(a: Rect4, b: Rect4, tolerance: number): boolean {
  return (
    Math.abs(a.x - b.x) <= tolerance &&
    Math.abs(a.y - b.y) <= tolerance &&
    Math.abs(a.width - b.width) <= tolerance &&
    Math.abs(a.height - b.height) <= tolerance
  );
}

const WHITESPACE = new Set([' ', '\t', '\r', '\n', '\f', '\0']);
const DELIMITERS = new Set(['(', ')', '<', '>', '[', ']', '{', '}', '/', '%']);

interface Token {
  text: string;
  start: number;
  end: number;
}

interface DrawHit {
  name: string;
  type: 'image' | 'form';
  rect: Rect4;
  nameStart: number;
  doEnd: number;
}

function walkXObjectDraws(
  content: string,
  xobjects: Readonly<Record<string, XObjectInfo>>,
): DrawHit[] {
  const hits: DrawHit[] = [];
  let ctm: Matrix6 = IDENTITY;
  const stack: Matrix6[] = [];
  const operands: Token[] = [];
  let i = 0;
  const n = content.length;
  const numeric = (token: Token | undefined): number => (token ? Number(token.text) : NaN);

  while (i < n) {
    const ch = content[i]!;

    if (WHITESPACE.has(ch)) {
      i += 1;
      continue;
    }
    if (ch === '%') {
      while (i < n && content[i] !== '\n' && content[i] !== '\r') i += 1;
      continue;
    }
    if (ch === '(') {
      let depth = 0;
      while (i < n) {
        const c = content[i]!;
        if (c === '\\') {
          i += 2;
          continue;
        }
        if (c === '(') depth += 1;
        else if (c === ')') {
          depth -= 1;
          if (depth === 0) {
            i += 1;
            break;
          }
        }
        i += 1;
      }
      operands.push({ text: '()', start: i, end: i });
      continue;
    }
    if (ch === '<') {
      if (content[i + 1] === '<') {
        operands.push({ text: '<<', start: i, end: i + 2 });
        i += 2;
        continue;
      }
      while (i < n && content[i] !== '>') i += 1;
      i += 1;
      operands.push({ text: '<>', start: i, end: i });
      continue;
    }
    if (ch === '>' && content[i + 1] === '>') {
      operands.push({ text: '>>', start: i, end: i + 2 });
      i += 2;
      continue;
    }
    if (ch === '/') {
      const start = i;
      i += 1;
      while (i < n && !WHITESPACE.has(content[i]!) && !DELIMITERS.has(content[i]!)) i += 1;
      operands.push({ text: content.slice(start, i), start, end: i });
      continue;
    }
    if (DELIMITERS.has(ch)) {
      operands.push({ text: ch, start: i, end: i + 1 });
      i += 1;
      continue;
    }

    const start = i;
    while (i < n && !WHITESPACE.has(content[i]!) && !DELIMITERS.has(content[i]!)) i += 1;
    const token: Token = { text: content.slice(start, i), start, end: i };
    if (!isOperator(token.text)) {
      operands.push(token);
      continue;
    }

    switch (token.text) {
      case 'q':
        stack.push(ctm);
        break;
      case 'Q':
        ctm = stack.pop() ?? IDENTITY;
        break;
      case 'cm': {
        const m: Matrix6 = [
          numeric(operands[operands.length - 6]),
          numeric(operands[operands.length - 5]),
          numeric(operands[operands.length - 4]),
          numeric(operands[operands.length - 3]),
          numeric(operands[operands.length - 2]),
          numeric(operands[operands.length - 1]),
        ];
        if (m.every((v) => Number.isFinite(v))) ctm = multiply(ctm, m);
        break;
      }
      case 'Do': {
        const nameToken = operands[operands.length - 1];
        const name = nameToken?.text.startsWith('/') ? nameToken.text.slice(1) : undefined;
        const info = name ? xobjects[name] : undefined;
        if (nameToken && name && info) {
          hits.push({
            name,
            type: info.type,
            rect: paintedBBox(ctm, info),
            nameStart: nameToken.start,
            doEnd: token.end,
          });
        }
        break;
      }
      case 'BI':
        i = skipInlineImage(content, i);
        break;
      default:
        break;
    }
    operands.length = 0;
  }

  return hits;
}

export function listXObjectDraws(
  content: string,
  xobjects: Readonly<Record<string, XObjectInfo>>,
): XObjectDraw[] {
  return walkXObjectDraws(content, xobjects).map(({ name, type, rect }) => ({ name, type, rect }));
}

export function removeXObjectDraws(
  content: string,
  targets: readonly Rect4[],
  xobjects: Readonly<Record<string, XObjectInfo>>,
  tolerance = 2,
): RemovalResult {
  const blanks: [number, number][] = [];
  for (const hit of walkXObjectDraws(content, xobjects)) {
    if (targets.some((target) => rectsMatch(hit.rect, target, tolerance))) {
      blanks.push([hit.nameStart, hit.doEnd]);
    }
  }
  if (blanks.length === 0) return { content, removed: 0 };

  const chars = content.split('');
  for (const [from, to] of blanks) {
    for (let k = from; k < to; k += 1) chars[k] = ' ';
  }
  return { content: chars.join(''), removed: blanks.length };
}

function isOperator(text: string): boolean {
  return !/^[+-]?(\d+\.?\d*|\.\d+)$/.test(text);
}

function skipInlineImage(content: string, afterBI: number): number {
  const idIndex = content.indexOf('ID', afterBI);
  if (idIndex === -1) return content.length;
  let j = idIndex + 3;
  while (j < content.length) {
    if (
      content[j] === 'E' &&
      content[j + 1] === 'I' &&
      (j + 2 >= content.length || WHITESPACE.has(content[j + 2]!)) &&
      WHITESPACE.has(content[j - 1]!)
    ) {
      return j + 2;
    }
    j += 1;
  }
  return content.length;
}
