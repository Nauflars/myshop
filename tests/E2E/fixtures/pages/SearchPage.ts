import { Page, Locator } from '@playwright/test';

/**
 * Page Object Model for Search Page
 */
export class SearchPage {
  readonly page: Page;
  readonly searchInput: Locator;
  readonly searchButton: Locator;
  readonly searchResults: Locator;
  readonly noResultsMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.searchInput = page.locator('[data-test="search-input"]');
    this.searchButton = page.locator('[data-test="search-button"]');
    this.searchResults = page.locator('[data-test="search-results"]');
    this.noResultsMessage = page.locator('[data-test="no-results-message"]');
  }

  async goto() {
    await this.page.goto('/search');
  }

  async search(query: string) {
    await this.searchInput.fill(query);
    await this.searchButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  async getResultCount(): Promise<number> {
    return await this.page.locator('[data-test="search-result-item"]').count();
  }

  async clickResult(index: number = 0) {
    await this.page.locator('[data-test="search-result-item"]').nth(index).click();
  }

  async applyFilter(filterName: string, filterValue: string) {
    await this.page.locator(`[data-test="filter-${filterName}-${filterValue}"]`).click();
    await this.page.waitForLoadState('networkidle');
  }

  async sortBy(sortOption: string) {
    await this.page.selectOption('[data-test="sort-select"]', sortOption);
    await this.page.waitForLoadState('networkidle');
  }
}
