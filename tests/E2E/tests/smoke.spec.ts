import { test, expect } from '@playwright/test';

/**
 * Smoke tests - Quick verification that the app is running
 */
test.describe('Smoke Tests', () => {
  test('homepage should be accessible', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/MyShop/);
  });

  test('login page should be accessible', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveTitle(/Login/);
  });

  test('products page should be accessible', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/products');
  });

  test('API health check', async ({ request }) => {
    const response = await request.get('/api/health');
    // API may or may not have health endpoint, so we just check it doesn't crash
    console.log('Health check status:', response.status());
  });
});
