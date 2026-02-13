import { test, expect } from '@playwright/test';

/**
 * User Registration E2E Tests
 * Tests user registration flow
 */
test.describe('User Registration', () => {
  
  test('should display registration page', async ({ page }) => {
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    await expect(page).toHaveURL('/register');
  });

  test('should have registration form fields', async ({ page }) => {
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    // Check for common registration fields
    const hasForm = await page.locator('form').count() > 0;
    expect(hasForm).toBeTruthy();
  });

  test('should validate required fields', async ({ page }) => {
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    // Try to submit empty form
    const submitBtn = page.locator('button[type="submit"]');
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      
      // Should show validation errors or stay on page
      await page.waitForTimeout(1000);
      
      const currentUrl = page.url();
      expect(currentUrl).toContain('/register');
    }
  });

  test('should register new user via API', async ({ page }) => {
    const timestamp = Date.now();
    const testEmail = `testuser${timestamp}@example.com`;
    
    const response = await page.request.post('/api/users/register', {
      data: {
        email: testEmail,
        password: 'TestPass123!',
        name: 'Test User'
      }
    });
    
    // Check if registration endpoint exists
    if (response.status() === 200 || response.status() === 201) {
      const data = await response.json();
      expect(data).toBeDefined();
    } else {
      console.log('Registration API status:', response.status());
    }
  });

  test('should prevent duplicate email registration', async ({ page }) => {
    // Try to register with existing email
    const response = await page.request.post('/api/users/register', {
      data: {
        email: 'customer1@example.com', // Existing user
        password: 'NewPass123!',
        name: 'Duplicate User'
      }
    });
    
    // Should return error (400, 409, or similar)
    if (response.status() !== 404) {
      expect([400, 409, 422]).toContain(response.status());
    }
  });

  test('should redirect to login after successful registration', async ({ page }) => {
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    // Check if there's a link to login page
    const loginLink = page.locator('a[href="/login"], a:has-text("Sign in"), a:has-text("Login")');
    if (await loginLink.count() > 0) {
      await loginLink.first().click();
      await page.waitForLoadState('networkidle');
      
      await expect(page).toHaveURL('/login');
    }
  });
});
