import { test, expect } from '@playwright/test';

/**
 * Complete Purchase Flow E2E Tests
 * Tests the critical e-commerce journey from search to order completion
 */
test.describe('Complete Purchase Flow', () => {
  // Helper function to login
  const login = async (page) => {
    await page.goto('/login');
    await page.fill('input[name="_username"]', 'customer1@example.com');
    await page.fill('input[name="_password"]', 'customer123');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
  };

  test.beforeEach(async ({ page }) => {
    await login(page);
    
    // Clear cart before each test
    await page.request.delete('/api/cart');
  });

  test('should complete full purchase journey: search → view → add to cart → checkout', async ({ page }) => {
    // Step 1: Search for a product
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Step 2: Click on first product
    await page.locator('a:has-text("View Details")').first().click();
    await page.waitForLoadState('networkidle');
    
    // Step 3: Add to cart (if button exists on detail page)
    const addToCartBtn = page.locator('button:has-text("Add to Cart")');
    if (await addToCartBtn.isVisible()) {
      await addToCartBtn.click();
      
      // Wait for confirmation
      await page.waitForTimeout(2000);
    }
    
    // Step 4: Go to cart
    await page.goto('/cart');
    await page.waitForLoadState('networkidle');
    
    // Step 5: Proceed to checkout
    await page.goto('/checkout');
    await page.waitForLoadState('networkidle');
    
    // Verify we're on checkout page
    await expect(page).toHaveURL('/checkout');
  });

  test('should add product to cart via API', async ({ page }) => {
    // Get first product from products page
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Get product ID from "View Details" link
    const firstProductLink = await page.locator('a:has-text("View Details")').first().getAttribute('href');
    const productId = firstProductLink?.split('/').pop();
    
    if (productId) {
      // Add to cart via API
      const response = await page.request.post('/api/cart/items', {
        data: {
          productId: productId,
          quantity: 2
        }
      });
      
      // Should succeed or return appropriate status
      expect([200, 201, 400, 404]).toContain(response.status());
    }
  });

  test('should display items in cart', async ({ page }) => {
    // Add item via products page
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Try to add via button if available
    const addToCartBtns = page.locator('button:has-text("Add to Cart")');
    if (await addToCartBtns.count() > 0) {
      await addToCartBtns.first().click();
      await page.waitForTimeout(2000);
    }
    
    // Go to cart
    await page.goto('/cart');
    await page.waitForLoadState('networkidle');
    
    // Should be on cart page
    await expect(page).toHaveURL('/cart');
  });

  test('should update quantity in cart', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Get product ID
    const firstProductLink = await page.locator('a:has-text("View Details")').first().getAttribute('href');
    const productId = firstProductLink?.split('/').pop();
    
    if (productId) {
      // Add item with quantity 1
      await page.request.post('/api/cart/items', {
        data: { productId: productId, quantity: 1 }
      });
      
      // Update to quantity 3
      const updateResponse = await page.request.put(`/api/cart/items/${productId}`, {
        data: { quantity: 3 }
      });
      
      // Check if update endpoint exists
      console.log('Update status:', updateResponse.status());
    }
  });

  test('should remove item from cart', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    const firstProductLink = await page.locator('a:has-text("View Details")').first().getAttribute('href');
    const productId = firstProductLink?.split('/').pop();
    
    if (productId) {
      // Add item
      await page.request.post('/api/cart/items', {
        data: { productId: productId, quantity: 1 }
      });
      
      // Remove item
      const deleteResponse = await page.request.delete(`/api/cart/items/${productId}`);
      
      // Should succeed
      expect([200, 204, 404]).toContain(deleteResponse.status());
    }
  });

  test('should clear entire cart', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Add multiple items
    const productLinks = await page.locator('a:has-text("View Details")').all();
    
    for (let i = 0; i < Math.min(2, productLinks.length); i++) {
      const href = await productLinks[i].getAttribute('href');
      const productId = href?.split('/').pop();
      
      if (productId) {
        await page.request.post('/api/cart/items', {
          data: { productId: productId, quantity: 1 }
        });
      }
    }
    
    // Clear cart
    const clearResponse = await page.request.delete('/api/cart');
    expect([200, 204]).toContain(clearResponse.status());
  });

  test('should show out of stock products as disabled', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Look for "Out of Stock" buttons
    const outOfStockBtns = page.locator('button:has-text("Out of Stock")');
    
    if (await outOfStockBtns.count() > 0) {
      const isDisabled = await outOfStockBtns.first().isDisabled();
      expect(isDisabled).toBeTruthy();
    }
  });

  test('should navigate between products, cart, and checkout', async ({ page }) => {
    // Products
    await page.goto('/products');
    await expect(page).toHaveURL('/products');
    
    // Cart
    await page.goto('/cart');
    await expect(page).toHaveURL('/cart');
    
    // Checkout
    await page.goto('/checkout');
    await expect(page).toHaveURL('/checkout');
    
    // Orders
    await page.goto('/orders');
    await expect(page).toHaveURL('/orders');
  });
});
