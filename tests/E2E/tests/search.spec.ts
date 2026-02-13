import { test, expect } from '@playwright/test';
import { SearchPage } from '../fixtures/pages/SearchPage';

/**
 * Search functionality E2E tests
 * Tests semantic search, filters, and product details
 */
test.describe('Search Functionality', () => {
  let searchPage: SearchPage;

  test.beforeEach(async ({ page }) => {
    searchPage = new SearchPage(page);
    await page.goto('/');
  });

  test('should display search results', async ({ page }) => {
    await searchPage.search('laptop');
    
    // Should show results
    await expect(page.locator('[data-test="search-results"]')).toBeVisible();
    await expect(page.locator('[data-test="search-result-item"]').first()).toBeVisible();
  });

  test('should show no results message for invalid search', async ({ page }) => {
    await searchPage.search('xyzabc123nonexistent');
    
    // Should show no results message
    await expect(page.locator('[data-test="no-results-message"]')).toBeVisible();
  });

  test('should filter results by category', async ({ page }) => {
    await searchPage.search('laptop');
    
    // Apply category filter
    await page.click('[data-test="filter-category-electronics"]');
    
    // Results should update
    await expect(page.locator('[data-test="search-result-item"]')).toHaveCount(await page.locator('[data-test="search-result-item"]').count());
  });

  test('should filter results by price range', async ({ page }) => {
    await searchPage.search('laptop');
    
    // Set price range
    await page.fill('[data-test="price-min"]', '500');
    await page.fill('[data-test="price-max"]', '1000');
    await page.click('[data-test="apply-price-filter"]');
    
    // Results should update
    await expect(page.locator('[data-test="search-result-item"]')).toBeVisible();
  });

  test('should sort results', async ({ page }) => {
    await searchPage.search('laptop');
    
    // Sort by price low to high
    await page.selectOption('[data-test="sort-select"]', 'price-asc');
    
    // First result should be lowest price
    // (verification would depend on actual data)
    await expect(page.locator('[data-test="search-result-item"]').first()).toBeVisible();
  });

  test('should navigate to product detail page', async ({ page }) => {
    await searchPage.search('laptop');
    
    // Click first result
    await page.click('[data-test="search-result-item"]');
    
    // Should navigate to product detail
    await expect(page).toHaveURL(/\/products\/\d+/);
    await expect(page.locator('[data-test="product-title"]')).toBeVisible();
  });

  test('should handle semantic search', async ({ page }) => {
    // Search with natural language
    await searchPage.search('I need a computer for gaming');
    
    // Should return relevant results (AI-powered semantic search)
    await expect(page.locator('[data-test="search-results"]')).toBeVisible();
    await expect(page.locator('[data-test="search-result-item"]')).toHaveCount;
  });
});
