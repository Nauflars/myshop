## IMPORTANT: AI Chatbot Implementation

The project includes a **stub implementation** of the AI chatbot. To enable full AI capabilities with tool integration:

### Required Steps

1. **Install symfony/ai package** (fictional/future package based on requirements):
   ```bash
   composer require symfony/ai
   ```

2. **Configure AI Provider** in `config/packages/symfony_ai.yaml`:
   ```yaml
   symfony_ai:
       provider: openai  # or anthropic
       api_key: '%env(AI_PROVIDER_API_KEY)%'
       model: gpt-4  # or claude-3
       temperature: 0.7
       max_tokens: 500
   ```

3. **Add API Key** to `.env`:
   ```
   AI_PROVIDER_API_KEY=your-api-key-here
   ```

4. **Implement Chatbot Agent** (`src/Infrastructure/Chatbot/Agent/ChatbotAgent.php`):
   - Initialize LLM with system prompt
   - Register custom tools
   - Handle message routing and context management

5. **Create Chatbot Tools**:
   - `StatsTool` - Sales and product statistics (admin/seller only)
   - `SearchProductTool` - Product search by name/category
   - `StockTool` - Check product stock levels
   - `OrderTool` - Cart and order management

6. **Implement SessionManager** (`src/Infrastructure/Chatbot/SessionManager.php`):
   - Store conversation history in Symfony session
   - Provide context for follow-up questions

7. **Create Prompt Templates** in `config/chatbot/`:
   - `system-prompt.yaml` - Define chatbot personality and behavior
   - `tools-config.yaml` - Tool descriptions for LLM

### Current Implementation

The current `ChatbotController` provides **simple keyword-based responses** as a fallback. Users can still interact with the chatbot widget, but responses are basic pattern matching rather than true AI.

### Testing the Stub

```bash
curl -X POST http://localhost/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "help"}'
```

### Architecture

When fully implemented, the chatbot will:
1. Receive user message via `/api/chat` endpoint
2. ChatbotAgent analyzes intent and user role
3. Select and invoke appropriate tools
4. LLM generates natural language response
5. Return response to frontend
6. Store conversation in session for context

For now, the application is **fully functional** except for advanced AI chatbot features.
