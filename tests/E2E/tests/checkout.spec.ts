import { test, expect } from '@playwright/test';
import { CheckoutPage } from '../fixtures/pages/CheckoutPage';
import { CartPage } from '../fixtures/pages/CartPage';

/**
 * Checkout flow E2E tests
 * Tests complete purchase flow from cart to order confirmation
 */
test.describe('Checkout Flow', () => {
  let checkoutPage: CheckoutPage;
  let cartPage: CartPage;

  test.beforeEach(async ({ page }) => {
    checkoutPage = new CheckoutPage(page);
    cartPage = new CartPage(page);
    
    // Login and add item to cart
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
  });

  test('should complete purchase successfully', async ({ page }) => {
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    
    // Fill shipping information
    await checkoutPage.fillShippingInfo({
      address: '123 Test Street',
      city: 'Test City',
      postalCode: '12345',
      country: 'US'
    });
    
    // Select payment method
    await checkoutPage.selectPaymentMethod('credit-card');
    
    // Fill credit card info
    await checkoutPage.fillCardInfo({
      number: '4242424242424242',
      expiry: '12/27',
      cvc: '123'
    });
    
    // Submit order
    await checkoutPage.submitOrder();
    
    // Verify order confirmation
    await expect(page.locator('[data-test="order-confirmation"]')).toBeVisible();
    await expect(page.locator('[data-test="order-number"]')).toContainText(/ORD-\d+/);
  });

  test('should validate required shipping fields', async ({ page }) => {
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    
    // Try to proceed without filling required fields
    await page.click('[data-test="continue-to-payment"]');
    
    // Should show validation errors
    await expect(page.locator('[data-test="error-address"]')).toBeVisible();
    await expect(page.locator('[data-test="error-city"]')).toBeVisible();
  });

  test('should handle payment failure gracefully', async ({ page }) => {
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    
    // Fill shipping info
    await checkoutPage.fillShippingInfo({
      address: '123 Test Street',
      city: 'Test City',
      postalCode: '12345',
      country: 'US'
    });
    
    // Use card number that triggers decline
    await checkoutPage.selectPaymentMethod('credit-card');
    await checkoutPage.fillCardInfo({
      number: '4000000000000002',
      expiry: '12/27',
      cvc: '123'
    });
    
    await checkoutPage.submitOrder();
    
    // Should show error message
    await expect(page.locator('[data-test="payment-error"]')).toContainText('Payment declined');
  });

  test('should calculate shipping costs', async ({ page }) => {
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    
    // Fill shipping info
    await checkoutPage.fillShippingInfo({
      address: '123 Test Street',
      city: 'Test City',
      postalCode: '12345',
      country: 'US'
    });
    
    // Select shipping method
    await page.click('[data-test="shipping-method-standard"]');
    
    // Should update total with shipping
    await expect(page.locator('[data-test="shipping-cost"]')).toBeVisible();
    await expect(page.locator('[data-test="order-total"]')).toBeVisible();
  });

  test('should allow editing cart from checkout', async ({ page }) => {
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    
    // Click edit cart
    await page.click('[data-test="edit-cart"]');
    
    // Should return to cart
    await expect(page).toHaveURL(/\/cart/);
  });

  test('should save order to account history', async ({ page }) => {
    // Complete purchase
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    
    await checkoutPage.fillShippingInfo({
      address: '123 Test Street',
      city: 'Test City',
      postalCode: '12345',
      country: 'US'
    });
    
    await checkoutPage.selectPaymentMethod('credit-card');
    await checkoutPage.fillCardInfo({
      number: '4242424242424242',
      expiry: '12/27',
      cvc: '123'
    });
    
    await checkoutPage.submitOrder();
    
    // Get order number
    const orderNumber = await page.locator('[data-test="order-number"]').textContent();
    
    // Go to account orders
    await page.goto('/account/orders');
    
    // Should see order in history
    await expect(page.locator(`[data-test="order-${orderNumber}"]`)).toBeVisible();
  });
});
