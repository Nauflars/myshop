import { test, expect } from '@playwright/test';

/**
 * Chatbot E2E tests - Real implementation
 * Tests AI chatbot functionality
 */
test.describe('AI Chatbot', () => {
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

  test('should send message to chatbot via API', async ({ page }) => {
    const response = await page.request.post('/api/chat', {
      data: {
        message: 'Hello, I need help finding a laptop',
        conversationId: null
      }
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    
    expect(data.response).toBeDefined();
    expect(data.conversationId).toBeDefined();
  });

  test('should handle product search query', async ({ page }) => {
    const response = await page.request.post('/api/chat', {
      data: {
        message: 'Show me gaming laptops under $1000',
        conversationId: null
      }
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    
    expect(data.response).toBeTruthy();
    expect(data.conversationId).toBeTruthy();
  });

  test('should handle order status query', async ({ page }) => {
    const response = await page.request.post('/api/chat', {
      data: {
        message: 'What is the status of my last order?',
        conversationId: null
      }
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    
    expect(data.response).toBeDefined();
  });

  test('should maintain conversation context', async ({ page }) => {
    // First message
    const response1 = await page.request.post('/api/chat', {
      data: {
        message: 'I need a new laptop',
        conversationId: null
      }
    });
    
    const data1 = await response1.json();
    const conversationId = data1.conversationId;
    
    // Follow-up message with same conversation ID
    const response2 = await page.request.post('/api/chat', {
      data: {
        message: 'What about the price range?',
        conversationId: conversationId
      }
    });
    
    expect(response2.ok()).toBeTruthy();
    const data2 = await response2.json();
    expect(data2.conversationId).toBe(conversationId);
  });

  test('should retrieve conversation history', async ({ page }) => {
    // Create a conversation
    const chatResponse = await page.request.post('/api/chat', {
      data: {
        message: 'Hello chatbot',
        conversationId: null
      }
    });
    
    const chatData = await chatResponse.json();
    const conversationId = chatData.conversationId;
    
    // Get history
    const historyResponse = await page.request.get(`/api/chat/history/${conversationId}`);
    expect(historyResponse.ok()).toBeTruthy();
    
    const history = await historyResponse.json();
    expect(Array.isArray(history) || typeof history === 'object').toBeTruthy();
  });

  test('should clear conversation', async ({ page }) => {
    // Create conversation
    const chatResponse = await page.request.post('/api/chat', {
      data: {
        message: 'Test message',
        conversationId: null
      }
    });
    
    const chatData = await chatResponse.json();
    const conversationId = chatData.conversationId;
    
    // Clear conversation
    const clearResponse = await page.request.post('/api/chat/clear', {
      data: {
        conversationId: conversationId
      }
    });
    
    expect(clearResponse.ok()).toBeTruthy();
  });

  test('should reset context', async ({ page }) => {
    const response = await page.request.post('/api/chat/reset-context', {
      data: {
        conversationId: 'test-conv-id'
      }
    });
    
    // Should handle reset even if conversation doesn't exist
    expect(response.status()).toBeLessThan(500);
  });
});
