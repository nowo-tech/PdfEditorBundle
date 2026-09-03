#!/usr/bin/env node
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PDFDocument, StandardFonts, rgb, degrees } from 'pdf-lib';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', 'fixtures');

async function save(name, doc) {
  const path = join(root, name);
  await writeFile(path, await doc.save());
  console.log('wrote', path);
}

async function textParagraphs() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  const bold = await doc.embedFont(StandardFonts.HelveticaBold);
  const p1 = doc.addPage([595, 842]);
  p1.drawText('Editable text scenario', { x: 72, y: 760, size: 22, font: bold });
  p1.drawText('Add FreeText, edit with Select, queue insert_text from selection.', { x: 72, y: 700, size: 12, font, maxWidth: 450 });
  p1.drawText('Second paragraph for experiments.', { x: 72, y: 620, size: 14, font });
  const p2 = doc.addPage([595, 842]);
  p2.drawText('Page two — rotate or insert text.', { x: 72, y: 760, size: 16, font: bold });
  await save('text-paragraphs.pdf', doc);
}

async function acroformMixed() {
  const doc = await PDFDocument.create();
  const page = doc.addPage([595, 842]);
  const form = doc.getForm();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  page.drawText('AcroForm scenario', { x: 72, y: 780, size: 16, font });
  const name = form.createTextField('full_name');
  name.setText('Jane Doe');
  name.addToPage(page, { x: 72, y: 700, width: 280, height: 28, borderWidth: 1 });
  const email = form.createTextField('email');
  email.setText('jane@example.com');
  email.addToPage(page, { x: 72, y: 630, width: 280, height: 28, borderWidth: 1 });
  const agree = form.createCheckBox('agree_terms');
  agree.check();
  agree.addToPage(page, { x: 72, y: 560, width: 18, height: 18, borderWidth: 1 });
  const role = form.createDropdown('role');
  role.addOptions(['Editor', 'Reviewer', 'Admin']);
  role.select('Reviewer');
  role.addToPage(page, { x: 72, y: 480, width: 200, height: 28, borderWidth: 1 });
  form.updateFieldAppearances(font);
  await save('acroform-mixed.pdf', doc);
}

async function watermarkOverlay() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.HelveticaBold);
  const page = doc.addPage([595, 842]);
  page.drawText('Try Remove watermarks or add CONFIDENTIAL stamp.', { x: 72, y: 760, size: 14, font });
  page.drawText('DRAFT', { x: 120, y: 400, size: 72, font, color: rgb(0.75, 0.75, 0.75), opacity: 0.35, rotate: degrees(-35) });
  await save('watermark-overlay.pdf', doc);
}

async function multiPageFive() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  for (let i = 1; i <= 5; i += 1) {
    const page = doc.addPage([595, 842]);
    page.drawText(`Page ${i} of 5 — delete, duplicate, insert blank`, { x: 72, y: 760, size: 18, font });
    page.drawText(`Content unique to page ${i}.`, { x: 72, y: 700, size: 12, font });
  }
  await save('multi-page-5.pdf', doc);
}

async function withImages() {
  const doc = await PDFDocument.create();
  const page = doc.addPage([595, 842]);
  const font = await doc.embedFont(StandardFonts.Helvetica);
  page.drawText('Raster-like block (drawn shape stand-in for embedded image)', { x: 72, y: 780, size: 14, font });
  page.drawRectangle({ x: 72, y: 480, width: 200, height: 120, color: rgb(0.15, 0.4, 0.85) });
  page.drawText('IMG', { x: 160, y: 530, size: 24, font, color: rgb(1, 1, 1) });
  await save('with-images.pdf', doc);
}

async function fullPlayground() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  const bold = await doc.embedFont(StandardFonts.HelveticaBold);
  const form = doc.getForm();
  const cover = doc.addPage([595, 842]);
  cover.drawText('Full playground', { x: 72, y: 760, size: 24, font: bold });
  cover.drawText('CONFIDENTIAL', { x: 100, y: 350, size: 64, font: bold, color: rgb(0.8, 0.8, 0.8), opacity: 0.25, rotate: degrees(-30) });
  const formPage = doc.addPage([595, 842]);
  const field = form.createTextField('playground_notes');
  field.setText('Notes');
  field.addToPage(formPage, { x: 72, y: 650, width: 400, height: 80, borderWidth: 1 });
  doc.addPage([595, 842]).drawText('Annotations & redaction section', { x: 72, y: 760, size: 16, font: bold });
  form.updateFieldAppearances(font);
  await save('full-playground.pdf', doc);
}

async function minimalBlank() {
  const doc = await PDFDocument.create();
  doc.addPage([595, 842]);
  await save('minimal-blank.pdf', doc);
}

await mkdir(root, { recursive: true });
await textParagraphs();
await acroformMixed();
await watermarkOverlay();
await multiPageFive();
await withImages();
await fullPlayground();
await minimalBlank();
#!/usr/bin/env node
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PDFDocument, StandardFonts, rgb, degrees } from 'pdf-lib';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', 'fixtures');

async function save(name, doc) {
  const path = join(root, name);
  await writeFile(path, await doc.save());
  console.log('wrote', path);
}

async function textParagraphs() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  const bold = await doc.embedFont(StandardFonts.HelveticaBold);
  const p1 = doc.addPage([595, 842]);
  p1.drawText('Editable text scenario', { x: 72, y: 760, size: 22, font: bold });
  p1.drawText('Add FreeText, edit with Select, queue insert_text from selection.', { x: 72, y: 700, size: 12, font, maxWidth: 450 });
  p1.drawText('Second paragraph for experiments.', { x: 72, y: 620, size: 14, font });
  const p2 = doc.addPage([595, 842]);
  p2.drawText('Page two — rotate or insert text.', { x: 72, y: 760, size: 16, font: bold });
  await save('text-paragraphs.pdf', doc);
}

async function acroformMixed() {
  const doc = await PDFDocument.create();
  const page = doc.addPage([595, 842]);
  const form = doc.getForm();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  page.drawText('AcroForm scenario', { x: 72, y: 780, size: 16, font });
  const name = form.createTextField('full_name');
  name.setText('Jane Doe');
  name.addToPage(page, { x: 72, y: 700, width: 280, height: 28, borderWidth: 1 });
  const email = form.createTextField('email');
  email.setText('jane@example.com');
  email.addToPage(page, { x: 72, y: 630, width: 280, height: 28, borderWidth: 1 });
  const agree = form.createCheckBox('agree_terms');
  agree.check();
  agree.addToPage(page, { x: 72, y: 560, width: 18, height: 18, borderWidth: 1 });
  const role = form.createDropdown('role');
  role.addOptions(['Editor', 'Reviewer', 'Admin']);
  role.select('Reviewer');
  role.addToPage(page, { x: 72, y: 480, width: 200, height: 28, borderWidth: 1 });
  form.updateFieldAppearances(font);
  await save('acroform-mixed.pdf', doc);
}

async function watermarkOverlay() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.HelveticaBold);
  const page = doc.addPage([595, 842]);
  page.drawText('Try Remove watermarks or add CONFIDENTIAL stamp.', { x: 72, y: 760, size: 14, font });
  page.drawText('DRAFT', { x: 120, y: 400, size: 72, font, color: rgb(0.75, 0.75, 0.75), opacity: 0.35, rotate: degrees(-35) });
  await save('watermark-overlay.pdf', doc);
}

async function multiPageFive() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  for (let i = 1; i <= 5; i += 1) {
    const page = doc.addPage([595, 842]);
    page.drawText(`Page ${i} of 5 — delete, duplicate, insert blank`, { x: 72, y: 760, size: 18, font });
    page.drawText(`Content unique to page ${i}.`, { x: 72, y: 700, size: 12, font });
  }
  await save('multi-page-5.pdf', doc);
}

async function withImages() {
  const doc = await PDFDocument.create();
  const page = doc.addPage([595, 842]);
  const font = await doc.embedFont(StandardFonts.Helvetica);
  page.drawText('Raster-like block (drawn shape stand-in for embedded image)', { x: 72, y: 780, size: 14, font });
  page.drawRectangle({ x: 72, y: 480, width: 200, height: 120, color: rgb(0.15, 0.4, 0.85) });
  page.drawText('IMG', { x: 160, y: 530, size: 24, font, color: rgb(1, 1, 1) });
  await save('with-images.pdf', doc);
}

async function fullPlayground() {
  const doc = await PDFDocument.create();
  const font = await doc.embedFont(StandardFonts.Helvetica);
  const bold = await doc.embedFont(StandardFonts.HelveticaBold);
  const form = doc.getForm();
  const cover = doc.addPage([595, 842]);
  cover.drawText('Full playground', { x: 72, y: 760, size: 24, font: bold });
  cover.drawText('CONFIDENTIAL', { x: 100, y: 350, size: 64, font: bold, color: rgb(0.8, 0.8, 0.8), opacity: 0.25, rotate: degrees(-30) });
  const formPage = doc.addPage([595, 842]);
  const field = form.createTextField('playground_notes');
  field.setText('Notes');
  field.addToPage(formPage, { x: 72, y: 650, width: 400, height: 80, borderWidth: 1 });
  doc.addPage([595, 842]).drawText('Annotations & redaction section', { x: 72, y: 760, size: 16, font: bold });
  form.updateFieldAppearances(font);
  await save('full-playground.pdf', doc);
}

async function minimalBlank() {
  const doc = await PDFDocument.create();
  doc.addPage([595, 842]);
  await save('minimal-blank.pdf', doc);
}

await mkdir(root, { recursive: true });
await textParagraphs();
await acroformMixed();
await watermarkOverlay();
await multiPageFive();
await withImages();
await fullPlayground();
await minimalBlank();
