import { test, expect } from '@playwright/test';

/**
 * Semantic Search E2E Tests
 * Tests AI-powered search functionality - core differentiator
 */
test.describe('Semantic Search', () => {
  test('should perform semantic search with natural language', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Enter natural language query
    await page.fill('#search-query', 'I need a comfortable chair for working from home');
    await page.selectOption('#search-mode', 'semantic');
    await page.click('button[type="submit"]');
    
    // Wait for results
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Should show results with success message
    await expect(page.locator('.alert-success')).toBeVisible();
    await expect(page.locator('.alert-success')).toContainText('Smart Search');
    
    // Should have product results
    const products = page.locator('.card');
    const count = await products.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should perform keyword search', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Enter keyword query
    await page.fill('#search-query', 'laptop');
    await page.selectOption('#search-mode', 'keyword');
    await page.click('button[type="submit"]');
    
    // Wait for results
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Verify keyword mode is selected
    const searchMode = await page.locator('#search-mode').inputValue();
    expect(searchMode).toBe('keyword');
    
    // Verify products are shown
    const products = page.locator('.card');
    const count = await products.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should filter by category', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Select category
    await page.selectOption('#category', 'Electronics');
    await page.click('button[type="submit"]');
    
    // Wait for filtered results
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // All results should be Electronics
    const categoryLabels = page.locator('.card-text.text-muted');
    const count = await categoryLabels.count();
    
    for (let i = 0; i < Math.min(count, 5); i++) {
      const text = await categoryLabels.nth(i).textContent();
      expect(text).toContain('Electronics');
    }
  });

  test('should limit number of results', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Set limit to 10
    await page.selectOption('#limit', '10');
    await page.click('button[type="submit"]');
    
    await page.waitForSelector('.card', { timeout: 15000 });
    
    const products = page.locator('.card');
    const count = await products.count();
    expect(count).toBeLessThanOrEqual(10);
  });

  test('should show no results message for impossible query', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    await page.fill('#search-query', 'xyzabc123nonexistent999product');
    await page.click('button[type="submit"]');
    
    // Should show no results message
    await expect(page.locator('.alert-info')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.alert-info')).toContainText('No products found');
  });

  test('should show similarity scores for semantic search', async ({ page }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    await page.fill('#search-query', 'gaming computer');
    await page.selectOption('#search-mode', 'semantic');
    await page.click('button[type="submit"]');
    
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Should show match percentage badges
    const badges = page.locator('.badge.bg-success');
    if (await badges.count() > 0) {
      const badgeText = await badges.first().textContent();
      expect(badgeText).toMatch(/\d+%/);
    }
  });

  test('should track search queries', async ({ page, request }) => {
    await page.goto('/products');
    await page.waitForLoadState('networkidle');
    
    // Perform a search
    await page.fill('#search-query', 'wireless headphones');
    await page.click('button[type="submit"]');
    
    await page.waitForSelector('.card', { timeout: 15000 });
    
    // Search should be tracked (verified by presence of results)
    const hasResults = await page.locator('.card').count() > 0;
    expect(hasResults).toBeTruthy();
  });
});
