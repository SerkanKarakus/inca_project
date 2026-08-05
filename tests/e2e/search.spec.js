const { test, expect } = require('@playwright/test');

test.describe('Product Search System', () => {
  test('should search for products from homepage and display list', async ({ page }) => {
    // 1. Go to homepage
    await page.goto('/');
    
    // 2. Locate the search box in the hero banner
    const searchInput = page.locator('.search-input');
    await expect(searchInput).toBeVisible();
    
    // 3. Fill search query and submit
    await searchInput.fill('menteşe');
    await searchInput.press('Enter');
    
    // 4. Assert URL matches search results page
    await expect(page).toHaveURL(/\/products\?search=mente%C5%9Fe/);
    
    // 5. Assert search results page header is visible
    const resultsTitle = page.locator('.section-title');
    await expect(resultsTitle).toBeVisible();
    
    // 6. Make sure the filter sidebar exists on products page
    await expect(page.locator('aside')).toBeVisible();
  });
});
