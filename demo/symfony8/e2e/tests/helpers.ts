import { expect, type Page } from '@playwright/test';

/** Open a demo scenario (copies its fixture into a workspace) and wait until the PDF preview has rendered. */
export async function openScenario(page: Page, scenario: string): Promise<void> {
  await page.goto(`/demo/scenarios/${scenario}/open`);
  await expect(page).toHaveURL(/\/pdf-editor\/[a-f0-9]{32}/);
  await expect(page.locator('.nowo-pdf-editor-toolbar')).toBeVisible({ timeout: 30000 });
  await expect(page.locator('.nowo-pdf-editor-viewer-wrap[aria-busy="false"]')).toBeVisible({ timeout: 60000 });
  await expect(page.locator('.nowo-pdf-editor-skeleton')).toHaveCount(0);
  await expect(page.locator('.nowo-pdf-editor-page-thumb').first()).toBeVisible({ timeout: 30000 });
}
