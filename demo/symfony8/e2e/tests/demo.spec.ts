import { test, expect } from '@playwright/test';
import { openScenario } from './helpers';

/** REQ-DEMO-013 — PdfEditor demo happy path (scenario hub → editor workspace → draft operation). */
test.describe('PdfEditor demo', () => {
  test('scenario hub lists the fixture scenarios', async ({ page }) => {
    const response = await page.goto('/demo/scenarios');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('.demo-scenario-card').first()).toBeVisible();
    expect(await page.locator('.demo-scenario-card').count()).toBeGreaterThan(2);
  });

  test('upload page renders on the UiKit form scaffold inside the demo layout', async ({ page }) => {
    const response = await page.goto('/pdf-editor');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('.demo-topbar')).toBeVisible();
    await expect(page.locator('[data-nowo-ui-page="form"][data-testid="pdf-editor-upload"]')).toBeVisible();
    await expect(page.locator('input[type="file"]')).toHaveCount(1);
    await expect(page.getByRole('button', { name: 'Open PDF' })).toBeVisible();
    await expect(page.locator('.demo-upload-aside')).toBeVisible();
  });

  test('opening a scenario renders the PDF with tools and page rail', async ({ page }) => {
    await openScenario(page, 'full');
    await expect(page.getByRole('group', { name: 'Tools' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save to PDF' })).toBeVisible();
    await expect(page.locator('.nowo-pdf-editor-pending-count')).toHaveText('0');
  });

  test('rotating a page queues a draft operation', async ({ page }) => {
    await openScenario(page, 'pages');
    await page.getByRole('button', { name: 'Rotate page' }).click();
    await expect(page.locator('.nowo-pdf-editor-pending-count')).toHaveText('1');
    await expect(page.locator('.nowo-pdf-editor-pending li').first()).toContainText(/Rotate page/i);
  });
});
