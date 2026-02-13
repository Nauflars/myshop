import { Page, Locator } from '@playwright/test';

/**
 * Page Object Model for Cart Page
 */
export class CartPage {
  readonly page: Page;
  readonly cartItems: Locator;
  readonly cartTotal: Locator;
  readonly checkoutButton: Locator;
  readonly emptyCartMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.cartItems = page.locator('[data-test="cart-item"]');
    this.cartTotal = page.locator('[data-test="cart-total"]');
    this.checkoutButton = page.locator('[data-test="checkout-button"]');
    this.emptyCartMessage = page.locator('[data-test="empty-cart-message"]');
  }

  async goto() {
    await this.page.goto('/cart');
  }

  async proceedToCheckout() {
    await this.checkoutButton.click();
    await this.page.waitForURL(/\/checkout/);
  }

  async removeItem(index: number = 0) {
    await this.page.locator('[data-test="remove-item"]').nth(index).click();
  }

  async updateQuantity(index: number, quantity: number) {
    await this.page.locator('[data-test="quantity-input"]').nth(index).fill(quantity.toString());
    await this.page.locator('[data-test="update-quantity"]').nth(index).click();
  }

  async getItemCount(): Promise<number> {
    return await this.cartItems.count();
  }

  async getTotalAmount(): Promise<string> {
    return await this.cartTotal.textContent() || '0';
  }
}
