const { test, expect } = require('@playwright/test');

test.describe('Mobile Viewport Navigation & Search', () => {
  // Configure tests in this file to run on a standard mobile viewport (iPhone 12 dimensions)
  test.use({ viewport: { width: 390, height: 844 } });

  test('should display mobile drawer menu and run search from within it', async ({ page }) => {
    // 1. Go to homepage
    await page.goto('/');
    
    // 2. Assert that normal desktop header search input is hidden on mobile
    const desktopSearch = page.locator('.header-search');
    await expect(desktopSearch).not.toBeVisible();
    
    // 3. Assert hamburger toggle button is visible in the top header
    const hamburger = page.locator('#menu-toggle');
    await expect(hamburger).toBeVisible();
    
    // 4. Assert mobile drawer links are currently hidden
    const navLinks = page.locator('#nav-links');
    await expect(navLinks).not.toBeVisible();
    
    // 5. Click the hamburger toggle button
    await hamburger.click();
    
    // 6. Assert mobile drawer menu is now active and visible
    await expect(navLinks).toBeVisible();
    
    // 7. Locate the search bar inside the mobile drawer menu
    const mobileSearchForm = navLinks.locator('.search-form-header');
    await expect(mobileSearchForm).toBeVisible();
    
    const mobileSearchInput = mobileSearchForm.locator('.search-input-header');
    await expect(mobileSearchInput).toBeVisible();
    
    // 8. Type search term in mobile menu and submit
    await mobileSearchInput.fill('ray');
    await mobileSearchInput.press('Enter');
    
    // 9. Assert URL points to search result page
    await expect(page).toHaveURL(/\/products\?search=ray/);
  });
});
