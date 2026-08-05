const { test, expect } = require('@playwright/test');

test.describe('Theme Toggle Integration', () => {
  test('should toggle light and dark themes and persist choice', async ({ page }) => {
    // 1. Navigate to homepage
    await page.goto('/');
    
    // 2. Assert page has loaded
    await expect(page).toHaveTitle(/INCA/i);
    
    // 3. Get initial theme
    const htmlTag = page.locator('html');
    const initialTheme = await htmlTag.getAttribute('data-theme') || 'dark';
    
    // 4. Locate and click the theme toggle button
    const toggleBtn = page.locator('#theme-toggle-btn');
    await expect(toggleBtn).toBeVisible();
    await toggleBtn.click();
    
    // 5. Assert that theme has changed in the HTML node
    const toggledTheme = await htmlTag.getAttribute('data-theme');
    expect(toggledTheme).not.toBe(initialTheme);
    
    // 6. Reload the page and assert that the choice is persisted in storage/cookies
    await page.reload();
    const persistedTheme = await htmlTag.getAttribute('data-theme');
    expect(persistedTheme).toBe(toggledTheme);
  });
});
