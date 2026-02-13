import { test, expect } from '@playwright/test';

/**
 * Authentication E2E tests - Real selectors
 * Tests login, logout using actual HTML structure
 */
test.describe('Authentication Flow (Real)', () => {
  test('should display login page with form fields', async ({ page }) => {
    await page.goto('/login');
    
    // Page should have correct title
    await expect(page).toHaveTitle(/Login/);
    
    // Form elements should be visible
    await expect(page.locator('input[name="_username"]')).toBeVisible();
    await expect(page.locator('input[name="_password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should login successfully with valid admin credentials', async ({ page }) => {
    await page.goto('/login');
    
    // Fill login form with test admin account
    await page.fill('input[name="_username"]', 'admin@example.com');
    await page.fill('input[name="_password"]', 'admin123');
    await page.click('button[type="submit"]');
    
    // Should redirect to homepage
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL('/');
  });

  test('should login successfully with customer credentials', async ({ page }) => {
    await page.goto('/login');
    
    await page.fill('input[name="_username"]', 'customer1@example.com');
    await page.fill('input[name="_password"]', 'customer123');
    await page.click('button[type="submit"]');
    
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL('/');
  });

  test('should show error with invalid credentials', async ({ page }) => {
    await page.goto('/login');
    
    await page.fill('input[name="_username"]', 'wrong@example.com');
    await page.fill('input[name="_password"]', 'wrongpass');
    await page.click('button[type="submit"]');
    
    // Should show error message
    await page.waitForSelector('.alert-danger', { timeout: 5000 });
    const errorMessage = await page.locator('.alert-danger').textContent();
    expect(errorMessage).toBeTruthy();
  });

  test('should remember me option work correctly', async ({ page }) => {
    await page.goto('/login');
    
    await page.fill('input[name="_username"]', 'customer1@example.com');
    await page.fill('input[name="_password"]', 'customer123');
    await page.check('input[name="_remember_me"]');
    await page.click('button[type="submit"]');
    
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL('/');
  });

  test('should display test account credentials on login page', async ({ page }) => {
    await page.goto('/login');
    
    // Test accounts card should be visible
    const testAccountsCard = page.locator('.card:has-text("Test Accounts")');
    await expect(testAccountsCard).toBeVisible();
    await expect(testAccountsCard).toContainText('admin@example.com');
    await expect(testAccountsCard).toContainText('customer1@example.com');
  });
});
