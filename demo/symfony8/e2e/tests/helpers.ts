import { expect, type Page } from '@playwright/test';

/** Wait until the Symfony Web Debug Toolbar has finished loading (optional outside dev). */
export async function waitForWebProfiler(page: Page): Promise<void> {
  await page.waitForSelector('.sf-toolbar', { state: 'visible', timeout: 20000 }).catch(() => undefined);
  const mini = page.locator('.sf-minitoolbar a').first();
  if (await mini.isVisible().catch(() => false)) {
    await mini.click();
  }
  await page
    .waitForFunction(
      () => {
        const bar = document.querySelector('.sf-toolbar') as HTMLElement | null;
        if (!bar || bar.offsetParent === null) {
          return true;
        }
        return !!document.querySelector('.sf-toolbar-block');
      },
      { timeout: 25000, polling: 250 },
    )
    .catch(() => undefined);
  await new Promise((r) => setTimeout(r, 400));
}

/** Open a demo scenario (copies its fixture into a workspace) and wait until the PDF preview has rendered. */
export async function openScenario(page: Page, scenario: string): Promise<void> {
  await page.goto(`/demo/scenarios/${scenario}/open`);
  await expect(page).toHaveURL(/\/pdf-editor\/[a-f0-9]{32}/);
  await expect(page.locator('.nowo-pdf-editor-toolbar')).toBeVisible({ timeout: 30000 });
  await expect(page.locator('.nowo-pdf-editor-viewer-wrap[aria-busy="false"]')).toBeVisible({ timeout: 60000 });
  await expect(page.locator('.nowo-pdf-editor-skeleton')).toHaveCount(0);
  await expect(page.locator('.nowo-pdf-editor-page-thumb').first()).toBeVisible({ timeout: 30000 });
}
