import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { openScenario, waitForWebProfiler } from './helpers';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('PdfEditor screenshots', () => {
  test.use({ viewport: { width: 1440, height: 900 } });
  test.setTimeout(60000);

  test('editor — PDF workspace with tools, page rail and properties', async ({ page }) => {
    await openScenario(page, 'full');
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'editor.png') });
  });

  test('upload — UiKit form scaffold inside the host layout', async ({ page }) => {
    await page.goto('/pdf-editor');
    await expect(page.locator('[data-testid="pdf-editor-upload"]')).toBeVisible();
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'upload.png') });
  });

  test('draft — queued page operations before saving to PDF', async ({ page }) => {
    await openScenario(page, 'pages');
    await page.getByRole('button', { name: 'Rotate page' }).click();
    await expect(page.locator('.nowo-pdf-editor-pending-count')).toHaveText('1');
    // Drop the button tooltip and let the "Added to draft" toast fade out.
    await page.mouse.move(700, 450);
    await expect(page.locator('.nowo-pdf-editor-toast')).toHaveCount(0, { timeout: 15000 }).catch(() => undefined);
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'draft.png') });
  });
});
