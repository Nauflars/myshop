import { test, expect } from '@playwright/test';

/**
 * Products E2E tests - Real implementation
 * Tests product browsing and details
 */
test.describe('Product Browsing', () => {
  test('should display home page with welcome message', async ({ page }) => {
    await page.goto('/');
    
    await expect(page).toHaveTitle(/Home - MyShop/);
    await expect(page.locator('h1')).toContainText('Welcome to MyShop');
  });

  test('should display products list page', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Wait for products to load (JavaScript fetch)
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Should have product cards
    const productCards = page.locator('.card');
    const count = await productCards.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should navigate to product detail page', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Wait for products to load
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Click first product "View Details" button
    const viewDetailsBtn = page.locator('a:has-text("View Details")').first();
    await expect(viewDetailsBtn).toBeVisible();
    
    await viewDetailsBtn.click();
    
    // Should be on product detail page
    await expect(page).toHaveURL(/\/products\/\d+/);
  });

  test('should display product details', async ({ page }) => {
    // Go to products page first to get a real product link
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Wait for products to load
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Click first "View Details" link
    const viewDetailsBtn = page.locator('a:has-text("View Details")').first();
    await viewDetailsBtn.click();
    await page.waitForLoadState('networkidle');
    
    // Should be on product detail page
    await expect(page).toHaveURL(/\/products\/.+/);
    
    // Product details should be visible (name, price, etc.)
    const title = await page.locator('h1, h2, h3').first().textContent();
    expect(title).toBeTruthy();
  });

  test('should show personalized recommendations on home page for logged in users', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="_username"]', 'customer1@example.com');
    await page.fill('input[name="_password"]', 'customer123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Go to home page
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Should have recommendations section
    const recommendations = page.locator('.recommendations, .featured-products');
    await expect(recommendations).toBeVisible({ timeout: 10000 });
  });

  test('should display featured products for anonymous users', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Wait for products to load (can be async loaded)
    await page.waitForSelector('.product-card', { timeout: 10000 });
    
    const products = page.locator('.product-card');
    const count = await products.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });
});
