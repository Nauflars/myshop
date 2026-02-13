import { Page, Locator } from '@playwright/test';

/**
 * Shipping information interface
 */
export interface ShippingInfo {
  address: string;
  city: string;
  postalCode: string;
  country: string;
  state?: string;
  phone?: string;
}

/**
 * Credit card information interface
 */
export interface CardInfo {
  number: string;
  expiry: string;
  cvc: string;
  name?: string;
}

/**
 * Page Object Model for Checkout Page
 */
export class CheckoutPage {
  readonly page: Page;
  readonly shippingAddressInput: Locator;
  readonly shippingCityInput: Locator;
  readonly shippingPostalCodeInput: Locator;
  readonly shippingCountrySelect: Locator;
  readonly continueToPaymentButton: Locator;
  readonly cardNumberInput: Locator;
  readonly cardExpiryInput: Locator;
  readonly cardCvcInput: Locator;
  readonly submitOrderButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.shippingAddressInput = page.locator('[name="shipping_address"]');
    this.shippingCityInput = page.locator('[name="shipping_city"]');
    this.shippingPostalCodeInput = page.locator('[name="shipping_postal_code"]');
    this.shippingCountrySelect = page.locator('[name="shipping_country"]');
    this.continueToPaymentButton = page.locator('[data-test="continue-to-payment"]');
    this.cardNumberInput = page.locator('[name="card_number"]');
    this.cardExpiryInput = page.locator('[name="card_expiry"]');
    this.cardCvcInput = page.locator('[name="card_cvc"]');
    this.submitOrderButton = page.locator('[data-test="submit-order"]');
  }

  async goto() {
    await this.page.goto('/checkout');
  }

  async fillShippingInfo(info: ShippingInfo) {
    await this.shippingAddressInput.fill(info.address);
    await this.shippingCityInput.fill(info.city);
    await this.shippingPostalCodeInput.fill(info.postalCode);
    await this.shippingCountrySelect.selectOption(info.country);
    
    if (info.state) {
      await this.page.locator('[name="shipping_state"]').fill(info.state);
    }
    
    if (info.phone) {
      await this.page.locator('[name="shipping_phone"]').fill(info.phone);
    }
    
    await this.continueToPaymentButton.click();
  }

  async selectPaymentMethod(method: 'credit-card' | 'paypal' | 'bank-transfer') {
    await this.page.locator(`[data-test="payment-method-${method}"]`).click();
  }

  async fillCardInfo(card: CardInfo) {
    await this.cardNumberInput.fill(card.number);
    await this.cardExpiryInput.fill(card.expiry);
    await this.cardCvcInput.fill(card.cvc);
    
    if (card.name) {
      await this.page.locator('[name="card_name"]').fill(card.name);
    }
  }

  async submitOrder() {
    await this.submitOrderButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  async getOrderTotal(): Promise<string> {
    return await this.page.locator('[data-test="order-total"]').textContent() || '0';
  }

  async getOrderNumber(): Promise<string | null> {
    const orderConfirmation = this.page.locator('[data-test="order-confirmation"]');
    await orderConfirmation.waitFor({ state: 'visible' });
    return await this.page.locator('[data-test="order-number"]').textContent();
  }
}
