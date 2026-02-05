# Implementation Plan: AI-Powered Conversational Shopping Assistant

## Overview

Implement a sophisticated AI-powered shopping assistant using Symfony AI that enables natural language interactions for product discovery, cart management, price comparison, and checkout guidance. The assistant will use tool-enabled LLM agents with conversation memory and strict adherence to DDD architecture principles.

## Technical Stack

- **AI Framework**: 
  - symfony/ai-agent (Agent orchestration)
  - symfony/ai-bundle (Symfony integration)
  - symfony/ai-chat (Conversational interface)
  - symfony/ai-platform (Multi-platform support)
  - symfony/ai-store (Conversation memory)

- **AI Platforms**:
  - **OpenAI**: GPT-4o-mini or equivalent (production)
  - **Ollama**: Local models via Docker HTTP endpoint (development/testing)

- **Existing Stack**: Symfony 7, PHP 8.3, MySQL 8.0, Docker, Doctrine, PHPUnit

## Architecture

### AI Layer Structure (DDD-Compliant)

```
src/
├── Application/
│   └── UseCase/              # Business logic for AI tools
│       ├── AI/
│       │   ├── GetProductsName.php
│       │   ├── GetProductsNameByMaxPrice.php
│       │   ├── GetPriceByProductId.php
│       │   ├── GetProductImagesByProductId.php
│       │   ├── AddToCartForUser.php
│       │   ├── GetCartTotalForUser.php
│       │   └── CheckoutOrderForUser.php
├── Infrastructure/
│   ├── AI/
│   │   ├── Tool/              # AI tool adapters (delegate to Use Cases)
│   │   │   ├── GetProductsNameTool.php
│   │   │   ├── GetProductsNameByMaxPriceTool.php
│   │   │   ├── GetPriceByProductIdTool.php
│   │   │   ├── GetProductImagesByProductIdTool.php
│   │   │   ├── AddToCartTool.php
│   │   │   ├── GetCartTotalTool.php
│   │   │   └── CheckoutOrderTool.php
│   │   ├── Agent/
│   │   │   └── ShoppingAssistantAgent.php
│   │   └── Service/
│   │       ├── ConversationManager.php
│   │       └── RoleAwareAssistant.php
│   └── Controller/
│       └── AIAssistantController.php (new endpoint)
config/
└── packages/
    └── ai.yaml                # Symfony AI configuration
```

### Key Design Principles

1. **DDD Compliance**:
   - AI Tools are in Infrastructure layer (technical adapters)
   - Business logic stays in Application/UseCase layer
   - Tools annotated with #[AsTool] delegate to Use Cases
   - No business logic in Tool classes

2. **SOLID Principles**:
   - Single Responsibility: Each tool/use case has one purpose
   - Open/Closed: Tools can be extended without modifying agent
   - Liskov Substitution: Tools implement consistent interfaces
   - Interface Segregation: Tools expose minimal required methods
   - Dependency Inversion: Tools depend on Use Case abstractions

3. **Security & Access Control**:
   - Role-based filtering in tool responses
   - User context passed through all tool invocations
   - Sensitive operations require explicit confirmation
   - Tools validate permissions before delegating

4. **Conversation Flow**:
   - Memory-enabled agent maintains context
   - Token usage tracking for cost management
   - Graceful fallback for tool failures
   - Clear error messages for user guidance

## AI Agent Configuration

### Primary Agent: "local_ollama"

```yaml
agent_name: local_ollama
model: gpt-4o-mini  # or llama3.1:8b for Ollama
capabilities:
  - tool_calling: true
  - conversation_memory: true
  - token_tracking: true
features:
  temperature: 0.7
  max_tokens: 1000
  stream: false
```

### System Prompt

```text
You are a helpful shopping assistant for an e-commerce platform.

Your capabilities:
- Help users discover products by name, category, or price range
- Compare product prices and show product images
- Add items to the user's shopping cart
- Display cart totals and help manage purchases
- Guide users through the checkout process

Critical rules:
1. NEVER invent product data - always use tools to fetch real information
2. ALWAYS ask for user confirmation before checkout or sensitive actions
3. Use the user's role (customer/seller/admin) to tailor responses
4. If a tool returns no results, offer alternative search strategies
5. Be conversational, helpful, and concise

When users ask about products:
- Use GetProductsName for general product searches
- Use GetProductsNameByMaxPrice when price is a constraint
- Use GetPriceByProductId for specific product pricing
- Use GetProductImagesByProductId to show product visuals

When managing shopping:
- Use AddToCart when users want to purchase
- Use GetCartTotal to show current basket value
- Use CheckoutOrder ONLY after explicit user confirmation
```

## Implementation Phases

### Phase 1: Dependencies & Configuration
1. Install Symfony AI packages via Composer
2. Configure AI platforms (OpenAI, Ollama) in config/packages/ai.yaml
3. Set up environment variables (OPENAI_API_KEY, OLLAMA_HOST_URL)
4. Configure agent definition with system prompt and tool settings

### Phase 2: Application Use Cases
Create dedicated use cases for AI tool operations:
1. **GetProductsName**: Fetch all product names (optionally filtered)
2. **GetProductsNameByMaxPrice**: Fetch products under price threshold
3. **GetPriceByProductId**: Get specific product price
4. **GetProductImagesByProductId**: Get product image URLs
5. **AddToCartForUser**: Add product to user's cart
6. **GetCartTotalForUser**: Calculate cart total for user
7. **CheckoutOrderForUser**: Finalize order for user

### Phase 3: AI Tool Adapters
Create tool classes with #[AsTool] annotation:
1. Each tool provides clear description for LLM
2. Tools define input schema (parameters)
3. Tools delegate to corresponding Use Case
4. Tools handle errors and return structured responses

### Phase 4: Agent & Services
1. **ShoppingAssistantAgent**: Main agent with tool registration
2. **ConversationManager**: Handle conversation history persistence
3. **RoleAwareAssistant**: Apply role-based response filtering
4. **AIAssistantController**: API endpoint for chat interactions

### Phase 5: Integration & Testing
1. Unit tests for each Use Case
2. Integration tests for Tool → Use Case flow
3. End-to-end tests for conversational scenarios
4. Agent configuration validation tests
5. Load testing for concurrent conversations

### Phase 6: Frontend Integration
1. Enhance existing chatbot widget UI
2. Add streaming response support (if enabled)
3. Show tool usage indicators
4. Display product images inline
5. Cart update notifications

## Use Case Specifications

### 1. GetProductsName
- **Input**: Optional filter (category, search term)
- **Output**: Array of {id, name, category}
- **Business Logic**: Use ProductRepository with filters
- **Authorization**: Public

### 2. GetProductsNameByMaxPrice
- **Input**: maxPrice (Money), optional category
- **Output**: Array of {id, name, price, category}
- **Business Logic**: Query products where price <= maxPrice
- **Authorization**: Public

### 3. GetPriceByProductId
- **Input**: productId (UUID)
- **Output**: {price, currency, inStock}
- **Business Logic**: Fetch Product, return price and stock status
- **Authorization**: Public

### 4. GetProductImagesByProductId
- **Input**: productId (UUID)
- **Output**: Array of image URLs
- **Business Logic**: Fetch Product, return associated images
- **Authorization**: Public

### 5. AddToCartForUser
- **Input**: userId (UUID), productId (UUID), quantity (int)
- **Output**: {success, cartItemCount, message}
- **Business Logic**: Reuse existing AddProductToCart use case
- **Authorization**: Authenticated user only

### 6. GetCartTotalForUser
- **Input**: userId (UUID)
- **Output**: {total, itemCount, items: [{product, quantity, subtotal}]}
- **Business Logic**: Fetch Cart, calculate total
- **Authorization**: Same user or admin

### 7. CheckoutOrderForUser
- **Input**: userId (UUID), shippingAddress (string)
- **Output**: {orderNumber, total, status, message}
- **Business Logic**: Reuse existing Checkout use case
- **Authorization**: Same user only, requires confirmation

## Testing Strategy

### Unit Tests (Use Case Layer)
- Test each use case with mocked repositories
- Verify correct business logic execution
- Test edge cases (empty results, out of stock, etc.)

### Integration Tests (Tool Layer)
- Test tool invocation with real agent
- Verify tool parameter parsing
- Verify tool → use case delegation
- Test error handling and rollback

### End-to-End Tests
- Conversational scenarios:
  1. "Show me products under $50"
  2. "What's the price of product X?"
  3. "Add 2 units to my cart"
  4. "How much is in my cart?"
  5. "Complete my order to [address]"
- Multi-turn conversations with memory
- Role-based access control scenarios

### Load Tests
- Concurrent conversations (10, 50, 100 users)
- Memory usage under load
- Response time for tool-heavy conversations

## Security Considerations

1. **Input Validation**:
   - Sanitize all tool inputs
   - Validate UUIDs, quantities, prices
   - Prevent SQL injection in search terms

2. **Authorization**:
   - Verify user identity in session
   - Check role permissions before tool execution
   - Log all checkout operations

3. **Rate Limiting**:
   - Limit requests per user per minute
   - Prevent abuse of expensive tool calls
   - Monitor token usage per conversation

4. **Data Privacy**:
   - Don't log sensitive user data
   - Mask payment information in responses
   - Clear conversation history on logout

## Rollout Plan

1. **MVP**: Basic product search and cart management
2. **Phase 2**: Role-aware responses and admin tools
3. **Phase 3**: Multi-language support
4. **Phase 4**: Recommendation engine integration
5. **Phase 5**: Voice interface support

## Success Criteria

- ✅ All 7 AI tools implemented and tested
- ✅ Agent successfully handles 20+ conversational scenarios
- ✅ End-to-end tests pass with 100% coverage
- ✅ No business logic in Infrastructure/AI layer
- ✅ Tool execution time < 500ms (excluding LLM latency)
- ✅ Zero security vulnerabilities in penetration testing
- ✅ Documentation for adding new tools

## Dependencies

- Existing e-commerce foundation (001-ecommerce-foundation)
- Doctrine entities: User, Product, Cart, Order
- Existing use cases: AddProductToCart, Checkout
- Symfony Security component for role checking
