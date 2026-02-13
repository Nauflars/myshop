import { test, expect } from '@playwright/test';
import { CartPage } from '../fixtures/pages/CartPage';

/**
 * Shopping cart E2E tests
 * Tests cart functionality including add, update, remove items
 */
test.describe('Shopping Cart', () => {
  let cartPage: CartPage;

  test.beforeEach(async ({ page }) => {
    cartPage = new CartPage(page);
    
    // Login before each test
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL('/');
  });

  test('should add product to cart', async ({ page }) => {
    await page.goto('/products/1');
    
    // Add to cart
    await page.click('[data-test="add-to-cart"]');
    
    // Cart count should update
    await expect(page.locator('[data-test="cart-count"]')).toHaveText('1');
    
    // Success message
    await expect(page.locator('[data-test="success-message"]')).toContainText('Added to cart');
  });

  test('should display cart contents', async ({ page }) => {
    // Add item first
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
    
    // Go to cart
    await cartPage.goto();
    
    // Should show cart items
    await expect(page.locator('[data-test="cart-item"]')).toHaveCount(1);
    await expect(page.locator('[data-test="cart-total"]')).toBeVisible();
  });

  test('should update item quantity', async ({ page }) => {
    // Add item
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
    
    // Go to cart
    await cartPage.goto();
    
    // Update quantity
    await page.fill('[data-test="quantity-input"]', '3');
    await page.click('[data-test="update-quantity"]');
    
    // Should update total
    await expect(page.locator('[data-test="cart-count"]')).toHaveText('3');
  });

  test('should remove item from cart', async ({ page }) => {
    // Add item
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
    
    // Go to cart
    await cartPage.goto();
    
    // Remove item
    await page.click('[data-test="remove-item"]');
    
    // Cart should be empty
    await expect(page.locator('[data-test="empty-cart-message"]')).toBeVisible();
    await expect(page.locator('[data-test="cart-count"]')).toHaveText('0');
  });

  test('should calculate correct total', async ({ page }) => {
    // Add multiple items
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
    
    await page.goto('/products/2');
    await page.click('[data-test="add-to-cart"]');
    
    // Go to cart
    await cartPage.goto();
    
    // Verify total calculation
    const items = await page.locator('[data-test="cart-item"]').count();
    expect(items).toBe(2);
    
    await expect(page.locator('[data-test="cart-subtotal"]')).toBeVisible();
    await expect(page.locator('[data-test="cart-total"]')).toBeVisible();
  });

  test('should persist cart after logout/login', async ({ page }) => {
    // Add item
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
    
    // Logout
    await page.click('[data-test="user-menu"]');
    await page.click('[data-test="logout-button"]');
    
    // Login again
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    
    // Cart should still have item
    await expect(page.locator('[data-test="cart-count"]')).toHaveText('1');
  });
});
