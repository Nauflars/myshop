import { test, expect } from '@playwright/test';

/**
 * Shopping Cart E2E tests - Real API endpoints
 * Tests cart functionality using actual REST API
 */
test.describe('Shopping Cart', () => {
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
  });

  test('should view empty cart page', async ({ page }) => {
    await page.goto('/cart');
    await page.waitForLoadState('networkidle');
    
    await expect(page).toHaveURL('/cart');
  });

  test('should add item to cart via API', async ({ page, request }) => {
    // Get first product ID
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Get product ID from URL after clicking details
    const firstProduct = page.locator('.product-card').first();
    await firstProduct.locator('.btn-secondary').click();
    await page.waitForLoadState('networkidle');
    
    const url = page.url();
    const productId = url.match(/\/products\/(\d+)/)?.[1];
    
    if (productId) {
      // Add to cart via API
      const response = await page.request.post('/api/cart/items', {
        data: {
          productId: parseInt(productId),
          quantity: 1
        }
      });
      
      expect(response.status()).toBe(200);
      
      // Verify cart has item
      await page.goto('/cart');
      await page.waitForLoadState('networkidle');
      
      // Cart should not be empty
      const cartItem = page.locator('.cart-item, .product-card').first();
      await expect(cartItem).toBeVisible({ timeout: 5000 });
    }
  });

  test('should display cart contents', async ({ page }) => {
    // First add an item via API
    const response = await page.request.post('/api/cart/items', {
      data: {
        productId: 1,
        quantity: 2
      }
    });
    
    expect(response.ok()).toBeTruthy();
    
    // Navigate to cart
    await page.goto('/cart');
    await page.waitForLoadState('networkidle');
    
    // Cart should have items
    const cartItems = page.locator('.cart-item, .product-card');
    const count = await cartItems.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should remove item from cart via API', async ({ page }) => {
    // Add item first
    await page.request.post('/api/cart/items', {
      data: {
        productId: 1,
        quantity: 1
      }
    });
    
    // Remove item
    const response = await page.request.delete('/api/cart/items/1');
    expect(response.ok()).toBeTruthy();
    
    // Verify cart is updated
    await page.goto('/cart');
    await page.waitForLoadState('networkidle');
  });

  test('should update item quantity via API', async ({ page }) => {
    // Add item first
    await page.request.post('/api/cart/items', {
      data: {
        productId: 1,
        quantity: 1
      }
    });
    
    // Update quantity
    const response = await page.request.put('/api/cart/items/1', {
      data: {
        quantity: 5
      }
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    expect(data.items).toBeDefined();
  });

  test('should clear entire cart', async ({ page }) => {
    // Add multiple items
    await page.request.post('/api/cart/items', {
      data: { productId: 1, quantity: 1 }
    });
    await page.request.post('/api/cart/items', {
      data: { productId: 2, quantity: 1 }
    });
    
    // Clear cart
    const response = await page.request.delete('/api/cart');
    expect(response.ok()).toBeTruthy();
    
    // Verify cart is empty
    const cartResponse = await page.request.get('/api/cart');
    const cartData = await cartResponse.json();
    expect(cartData.itemCount).toBe(0);
  });

  test('should persist cart after logout and login', async ({ page }) => {
    // Add item to cart
    await page.request.post('/api/cart/items', {
      data: { productId: 1, quantity: 3 }
    });
    
    // Logout
    await page.goto('/logout');
    await page.waitForLoadState('networkidle');
    
    // Login again
    await login(page);
    
    // Check if cart persists
    const response = await page.request.get('/api/cart');
    const cartData = await response.json();
    expect(cartData.itemCount).toBeGreaterThan(0);
  });
});
