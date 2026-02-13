import { test, expect } from '@playwright/test';

/**
 * Authentication E2E tests
 * Tests login, logout, and session management
 */
test.describe('Authentication Flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('should display login page', async ({ page }) => {
    await page.goto('/login');
    
    await expect(page).toHaveTitle(/Login/);
    await expect(page.locator('h1')).toContainText('Login');
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('should login successfully with valid credentials', async ({ page }) => {
    await page.goto('/login');
    
    // Fill login form
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    
    // Should redirect to homepage
    await expect(page).toHaveURL('/');
    
    // Should show user menu
    await expect(page.locator('[data-test="user-menu"]')).toBeVisible();
  });

  test('should show error with invalid credentials', async ({ page }) => {
    await page.goto('/login');
    
    await page.fill('input[name="email"]', 'wrong@example.com');
    await page.fill('input[name="password"]', 'wrongpass');
    await page.click('button[type="submit"]');
    
    // Should show error message
    await expect(page.locator('[data-test="error-message"]')).toBeVisible();
    await expect(page.locator('[data-test="error-message"]')).toContainText('Invalid credentials');
  });

  test('should logout successfully', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    
    // Wait for redirect
    await expect(page).toHaveURL('/');
    
    // Logout
    await page.click('[data-test="user-menu"]');
    await page.click('[data-test="logout-button"]');
    
    // Should redirect to login
    await expect(page).toHaveURL('/login');
  });

  test('should maintain session across page reloads', async ({ page }) => {
    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    
    await expect(page).toHaveURL('/');
    
    // Reload page
    await page.reload();
    
    // Should still be logged in
    await expect(page.locator('[data-test="user-menu"]')).toBeVisible();
  });
});
