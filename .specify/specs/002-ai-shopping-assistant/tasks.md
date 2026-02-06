# Tasks: AI-Powered Conversational Shopping Assistant

**Feature**: Symfony AI-based conversational shopping assistant with tool-calling agents  
**Input Documents**: plan.md, spec.md  
**Architecture**: DDD-compliant (Domain → Application → Infrastructure)  
**Testing**: Comprehensive unit, integration, and E2E tests for all components

---

## Format: `- [ ] T### [P?] [Story?] Description with file path`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[US#]**: User story number (1-7, maps to spec.md priorities)
- File paths are absolute from project root

---

## Phase 1: Setup (Package Installation & Configuration)

**Purpose**: Install Symfony AI packages and configure AI platforms

- [X] T101 Install symfony/ai-agent package via composer require symfony/ai-agent in composer.json
- [X] T102 Install symfony/ai-bundle package via composer require symfony/ai-bundle in composer.json
- [X] T103 Install symfony/ai-chat package via composer require symfony/ai-chat in composer.json
- [X] T104 Install symfony/ai-platform package via composer require symfony/ai-platform in composer.json
- [X] T105 Install symfony/ai-store package via composer require symfony/ai-store in composer.json
- [ ] T106 Run composer update to install all AI packages and dependencies
- [X] T107 Create AI configuration file with OpenAI and Ollama platforms in config/packages/ai.yaml
- [X] T108 Add OPENAI_API_KEY environment variable to .env and .env.example
- [X] T109 Add OLLAMA_HOST_URL environment variable (Docker HTTP endpoint) to .env and .env.example
- [X] T110 Configure primary agent "local_ollama" with tool-calling, memory, and token tracking in config/packages/ai.yaml
- [X] T111 Create system prompt configuration for shopping assistant agent in config/packages/ai.yaml

**Checkpoint**: Symfony AI packages installed and configured with OpenAI & Ollama platforms

---

## Phase 2: Foundational (Core AI Infrastructure)

**Purpose**: AI Tools directory structure, base services, and shared infrastructure

**⚠️ CRITICAL**: Must complete before implementing any user stories

- [X] T112 Create AI Tools directory structure at src/Infrastructure/AI/Tool/
- [X] T113 Create AI Agent directory structure at src/Infrastructure/AI/Agent/
- [X] T114 Create AI Service directory structure at src/Infrastructure/AI/Service/
- [X] T115 Create Application Use Cases directory for AI at src/Application/UseCase/AI/
- [X] T116 [P] Create ConversationManager service for session-based conversation history in src/Infrastructure/AI/Service/ConversationManager.php
- [X] T117 [P] Create RoleAwareAssistant service for role-based response filtering in src/Infrastructure/AI/Service/RoleAwareAssistant.php
- [X] T118 [P] Create AIAssistantController with POST /api/ai/chat endpoint in src/Infrastructure/Controller/AIAssistantController.php
- [X] T119 Configure AI routes for /api/ai/* in config/routes.yaml
- [X] T120 Register AI services with dependency injection in config/services.yaml

**Checkpoint**: Core AI infrastructure ready - user story implementation can begin in parallel

---

## Phase 3: User Story 1 - Product Discovery via AI (Priority: P1) 🎯 MVP

**Goal**: Enable natural language product search by name or category

**Independent Test**: User asks "Show me all products" and receives complete catalog list

### Unit Tests for User Story 1

- [X] T121 [P] [US1] Create unit test for GetProductsName use case in tests/Application/UseCase/AI/GetProductsNameTest.php
- [X] T122 [P] [US1] Create integration test for GetProductsNameTool with mock agent in tests/Infrastructure/AI/Tool/GetProductsNameToolTest.php

### Implementation for User Story 1

- [X] T123 [P] [US1] Create GetProductsName use case in src/Application/UseCase/AI/GetProductsName.php
- [X] T124 [US1] Create GetProductsNameTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/GetProductsNameTool.php
- [X] T125 [US1] Implement tool parameter schema (optional: category filter, search term) in GetProductsNameTool
- [X] T126 [US1] Register GetProductsNameTool as tagged service in config/services.yaml
- [X] T127 [US1] Add GetProductsNameTool to agent tool registry in config/packages/ai.yaml

**Checkpoint**: User Story 1 complete - users can discover products via natural language

---

## Phase 4: User Story 2 - Price-Based Product Search (Priority: P1) 🎯 MVP

**Goal**: Enable budget-based product filtering through conversational queries

**Independent Test**: User asks "Show me products under $50" and receives only matching items

### Unit Tests for User Story 2

- [X] T128 [P] [US2] Create unit test for GetProductsNameByMaxPrice use case in tests/Application/UseCase/AI/GetProductsNameByMaxPriceTest.php
- [X] T129 [P] [US2] Create integration test for GetProductsNameByMaxPriceTool in tests/Infrastructure/AI/Tool/GetProductsNameByMaxPriceToolTest.php

### Implementation for User Story 2

- [X] T130 [P] [US2] Create GetProductsNameByMaxPrice use case with price filtering logic in src/Application/UseCase/AI/GetProductsNameByMaxPrice.php
- [X] T131 [US2] Create GetProductsNameByMaxPriceTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/GetProductsNameByMaxPriceTool.php
- [X] T132 [US2] Implement tool parameter schema (maxPrice: float, currency: string, optional: category) in GetProductsNameByMaxPriceTool
- [X] T133 [US2] Register GetProductsNameByMaxPriceTool as tagged service in config/services.yaml
- [X] T134 [US2] Add GetProductsNameByMaxPriceTool to agent tool registry in config/packages/ai.yaml

**Checkpoint**: User Stories 1 & 2 complete - full product discovery with price filtering

---

## Phase 5: User Story 3 - Product Details Lookup (Priority: P1) 🎯 MVP

**Goal**: Provide detailed product information (price, images, stock) on request

**Independent Test**: User asks "How much does product X cost?" and receives accurate price + stock status

### Unit Tests for User Story 3

- [X] T135 [P] [US3] Create unit test for GetPriceByProductId use case in tests/Application/UseCase/AI/GetPriceByProductIdTest.php
- [X] T136 [P] [US3] Create unit test for GetProductImagesByProductId use case in tests/Application/UseCase/AI/GetProductImagesByProductIdTest.php
- [X] T137 [P] [US3] Create integration test for GetPriceByProductIdTool in tests/Infrastructure/AI/Tool/GetPriceByProductIdToolTest.php
- [X] T138 [P] [US3] Create integration test for GetProductImagesByProductIdTool in tests/Infrastructure/AI/Tool/GetProductImagesByProductIdToolTest.php

### Implementation for User Story 3

- [X] T139 [P] [US3] Create GetPriceByProductId use case in src/Application/UseCase/AI/GetPriceByProductId.php
- [X] T140 [P] [US3] Create GetProductImagesByProductId use case in src/Application/UseCase/AI/GetProductImagesByProductId.php
- [X] T141 [US3] Create GetPriceByProductIdTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/GetPriceByProductIdTool.php
- [X] T142 [US3] Implement tool parameter schema (productId: string) in GetPriceByProductIdTool
- [X] T143 [US3] Create GetProductImagesByProductIdTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/GetProductImagesByProductIdTool.php
- [X] T144 [US3] Implement tool parameter schema (productId: string) in GetProductImagesByProductIdTool
- [X] T145 [US3] Register both tools as tagged services in config/services.yaml
- [X] T146 [US3] Add both tools to agent tool registry in config/packages/ai.yaml

**Checkpoint**: MVP COMPLETE - Users can discover, filter, and inspect products via AI

---

## Phase 6: User Story 4 - Cart Management via AI (Priority: P2)

**Goal**: Enable adding products to cart and viewing cart status through conversation

**Independent Test**: User says "Add 2 units to cart" then "What's my total?" and receives accurate calculation

### Unit Tests for User Story 4

- [ ] T147 [P] [US4] Create unit test for AddToCartForUser use case in tests/Application/UseCase/AI/AddToCartForUserTest.php
- [ ] T148 [P] [US4] Create unit test for GetCartTotalForUser use case in tests/Application/UseCase/AI/GetCartTotalForUserTest.php
- [ ] T149 [P] [US4] Create integration test for AddToCartTool in tests/Infrastructure/AI/Tool/AddToCartToolTest.php
- [ ] T150 [P] [US4] Create integration test for GetCartTotalTool in tests/Infrastructure/AI/Tool/GetCartTotalToolTest.php

### Implementation for User Story 4

- [ ] T151 [P] [US4] Create AddToCartForUser use case (delegates to existing AddProductToCart) in src/Application/UseCase/AI/AddToCartForUser.php
- [ ] T152 [P] [US4] Create GetCartTotalForUser use case in src/Application/UseCase/AI/GetCartTotalForUser.php
- [ ] T153 [US4] Create AddToCartTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/AddToCartTool.php
- [ ] T154 [US4] Implement tool parameter schema (productId: string, quantity: int) with user context in AddToCartTool
- [ ] T155 [US4] Create GetCartTotalTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/GetCartTotalTool.php
- [ ] T156 [US4] Implement tool with user context from Symfony Security in GetCartTotalTool
- [ ] T157 [US4] Add authentication requirement to cart tools in config/packages/security.yaml
- [ ] T158 [US4] Register both cart tools as tagged services in config/services.yaml
- [ ] T159 [US4] Add both cart tools to agent tool registry in config/packages/ai.yaml

**Checkpoint**: User Stories 1-4 complete - Full product discovery + cart management

---

## Phase 7: User Story 5 - Guided Checkout Process (Priority: P2)

**Goal**: Enable completing orders through conversational checkout with explicit confirmation

**Independent Test**: User says "Checkout to [address]", confirms, and receives order number

### Unit Tests for User Story 5

- [ ] T160 [P] [US5] Create unit test for CheckoutOrderForUser use case in tests/Application/UseCase/AI/CheckoutOrderForUserTest.php
- [ ] T161 [P] [US5] Create integration test for CheckoutOrderTool in tests/Infrastructure/AI/Tool/CheckoutOrderToolTest.php
- [ ] T162 [P] [US5] Create E2E test for complete checkout conversation flow in tests/E2E/AICheckoutConversationTest.php

### Implementation for User Story 5

- [ ] T163 [US5] Create CheckoutOrderForUser use case (delegates to existing Checkout) in src/Application/UseCase/AI/CheckoutOrderForUser.php
- [ ] T164 [US5] Create CheckoutOrderTool with #[AsTool] annotation in src/Infrastructure/AI/Tool/CheckoutOrderTool.php
- [ ] T165 [US5] Implement tool parameter schema (shippingAddress: string, confirmation: bool) in CheckoutOrderTool
- [ ] T166 [US5] Add explicit confirmation validation before executing checkout in CheckoutOrderTool
- [ ] T167 [US5] Add transaction rollback on checkout failure in CheckoutOrderForUser use case
- [ ] T168 [US5] Register CheckoutOrderTool as tagged service in config/services.yaml
- [ ] T169 [US5] Add CheckoutOrderTool to agent tool registry in config/packages/ai.yaml
- [ ] T170 [US5] Update system prompt to require explicit confirmation for checkout in config/packages/ai.yaml

**Checkpoint**: User Stories 1-5 complete - Full shopping flow from discovery to checkout

---

## Phase 8: User Story 6 - Role-Aware Responses (Priority: P3)

**Goal**: Tailor AI responses based on user role (customer/seller/admin)

**Independent Test**: Admin asks for stats and receives data; customer asks same and is denied

### Unit Tests for User Story 6

- [ ] T171 [P] [US6] Create unit test for RoleAwareAssistant service in tests/Infrastructure/AI/Service/RoleAwareAssistantTest.php
- [ ] T172 [P] [US6] Create integration test for role-based tool access control in tests/Infrastructure/AI/RoleBasedAccessTest.php

### Implementation for User Story 6

- [ ] T173 [US6] Update RoleAwareAssistant to inject Symfony Security component in src/Infrastructure/AI/Service/RoleAwareAssistant.php
- [ ] T174 [US6] Implement role detection from authenticated user context in RoleAwareAssistant
- [ ] T175 [US6] Add role validation to each tool's execute method (check ROLE_CUSTOMER, ROLE_SELLER, ROLE_ADMIN)
- [ ] T176 [US6] Update system prompt to include role-aware behavior instructions in config/packages/ai.yaml
- [ ] T177 [US6] Create GetUserRoleTool for agent to query current user role in src/Infrastructure/AI/Tool/GetUserRoleTool.php
- [ ] T178 [US6] Register GetUserRoleTool and update agent configuration in config/packages/ai.yaml

**Checkpoint**: User Stories 1-6 complete - Role-aware conversational commerce

---

## Phase 9: User Story 7 - Conversation Memory & Context (Priority: P3)

**Goal**: Enable multi-turn conversations with context retention

**Independent Test**: User refers to "it" or "that product" from previous message and AI understands

### Unit Tests for User Story 7

- [ ] T179 [P] [US7] Create unit test for ConversationManager service in tests/Infrastructure/AI/Service/ConversationManagerTest.php
- [ ] T180 [P] [US7] Create integration test for multi-turn conversation persistence in tests/Infrastructure/AI/ConversationMemoryTest.php

### Implementation for User Story 7

- [ ] T181 [US7] Implement conversation history storage using Symfony session in ConversationManager
- [ ] T182 [US7] Add addMessage, getHistory, clearHistory methods to ConversationManager
- [ ] T183 [US7] Configure ai-store package for conversation persistence in config/packages/ai.yaml
- [ ] T184 [US7] Update ShoppingAssistantAgent to use ConversationManager for history in src/Infrastructure/AI/Agent/ShoppingAssistantAgent.php
- [ ] T185 [US7] Add conversation context to each agent invocation in AIAssistantController
- [ ] T186 [US7] Implement conversation cleanup on logout event in ConversationManager
- [ ] T187 [US7] Add conversation ID tracking for debugging in ConversationManager

**Checkpoint**: All user stories complete - Full conversational AI shopping assistant

---

## Phase 10: Agent Implementation & Orchestration

**Purpose**: Create main agent and integrate all tools

- [ ] T188 Create ShoppingAssistantAgent class with tool registry in src/Infrastructure/AI/Agent/ShoppingAssistantAgent.php
- [ ] T189 Implement agent initialization with configured LLM platform in ShoppingAssistantAgent
- [ ] T190 Register all 9 tools (7 shopping + 1 role + 1 future) with agent in ShoppingAssistantAgent
- [ ] T191 Implement tool execution error handling and fallback responses in ShoppingAssistantAgent
- [ ] T192 Add token usage tracking and logging in ShoppingAssistantAgent
- [ ] T193 Implement streaming response support (optional) in ShoppingAssistantAgent
- [ ] T194 Create agent factory service for dependency injection in src/Infrastructure/AI/Service/AgentFactory.php

**Checkpoint**: Agent fully functional with all tools registered

---

## Phase 11: Integration Testing

**Purpose**: Validate complete AI shopping assistant workflows

### Conversational Scenario Tests

- [ ] T195 [P] Create E2E test: "Show products under $50" → response validation in tests/E2E/ProductSearchConversationTest.php
- [ ] T196 [P] Create E2E test: "Add product X to cart" → "Show cart" → total verification in tests/E2E/CartManagementConversationTest.php
- [ ] T197 [P] Create E2E test: Complete checkout flow with confirmation in tests/E2E/CheckoutConversationTest.php
- [ ] T198 [P] Create E2E test: Multi-turn conversation with context ("show laptops" → "tell me about the second one") in tests/E2E/ContextualConversationTest.php
- [ ] T199 [P] Create E2E test: Role-based access (admin stats vs customer denial) in tests/E2E/RoleBasedConversationTest.php

### Agent Configuration Tests

- [ ] T200 [P] Create test for agent tool registration and availability in tests/Infrastructure/AI/AgentConfigurationTest.php
- [ ] T201 [P] Create test for OpenAI platform configuration in tests/Infrastructure/AI/PlatformConfigurationTest.php
- [ ] T202 [P] Create test for Ollama platform configuration in tests/Infrastructure/AI/PlatformConfigurationTest.php
- [ ] T203 [P] Create test for conversation memory persistence in tests/Infrastructure/AI/MemoryPersistenceTest.php
- [ ] T204 [P] Create test for token usage tracking in tests/Infrastructure/AI/TokenTrackingTest.php

### Error Handling Tests

- [ ] T205 [P] Create test for tool execution failures and graceful degradation in tests/Infrastructure/AI/ErrorHandlingTest.php
- [ ] T206 [P] Create test for invalid product ID handling in tests/Infrastructure/AI/InvalidInputTest.php
- [ ] T207 [P] Create test for authentication failures in cart/checkout tools in tests/Infrastructure/AI/AuthenticationTest.php
- [ ] T208 [P] Create test for LLM API downtime fallback in tests/Infrastructure/AI/APIDowntimeTest.php

**Checkpoint**: All integration tests pass - system is production-ready

---

## Phase 12: Frontend Integration

**Purpose**: Connect existing chatbot UI to new AI assistant backend

- [ ] T209 Update chatbot.js to call /api/ai/chat endpoint instead of old endpoint in public/js/chatbot.js
- [ ] T210 Add loading indicators during AI thinking/tool execution in public/js/chatbot.js
- [ ] T211 Add tool usage badges (e.g., "Searching products...") in public/js/chatbot.js
- [ ] T212 Implement product image display inline in chat messages in public/js/chatbot.js
- [ ] T213 Add cart update notifications in chat widget in public/js/chatbot.js
- [ ] T214 Update chatbot widget template with AI assistant branding in templates/chatbot/widget.html.twig
- [ ] T215 Add streaming response support for real-time typing effect (optional) in public/js/chatbot.js
- [ ] T216 Add conversation clear button in chatbot UI in public/js/chatbot.js

**Checkpoint**: Frontend fully integrated with AI assistant backend

---

## Phase 13: Security & Performance Optimization

**Purpose**: Harden security and optimize for production load

### Security Hardening

- [ ] T217 [P] Add input sanitization for all tool parameters in each tool's execute method
- [ ] T218 [P] Implement rate limiting (60 req/min per user) on /api/ai/chat endpoint
- [ ] T219 [P] Add audit logging for all checkout operations in CheckoutOrderTool
- [ ] T220 [P] Implement CSRF protection for AI assistant endpoint in config/packages/security.yaml
- [ ] T221 [P] Add UUID validation for productId parameters in all product tools
- [ ] T222 [P] Mask sensitive data in conversation logs in ConversationManager

### Performance Optimization

- [ ] T223 [P] Add caching for product catalog queries (5 min TTL) in GetProductsName use case
- [ ] T224 [P] Implement async tool execution where possible in ShoppingAssistantAgent
- [ ] T225 [P] Add database query optimization indexes for AI queries in migrations
- [ ] T226 [P] Configure connection pooling for LLM API requests in config/packages/ai.yaml
- [ ] T227 [P] Implement conversation history size limit (last 20 messages) in ConversationManager

**Checkpoint**: System secured and optimized for production

---

## Phase 14: Documentation & Monitoring

**Purpose**: Create documentation and set up monitoring

- [ ] T228 [P] Create README for AI assistant feature in .specify/specs/002-ai-shopping-assistant/README.md
- [ ] T229 [P] Document how to add new AI tools (developer guide) in .specify/specs/002-ai-shopping-assistant/ADDING_TOOLS.md
- [ ] T230 [P] Create API documentation for /api/ai/chat endpoint in API.md
- [ ] T231 [P] Add inline code documentation for all use cases and tools
- [ ] T232 [P] Create monitoring dashboard queries for AI metrics (response time, tool usage, errors)
- [ ] T233 [P] Set up alerting for high error rates or API downtime
- [ ] T234 [P] Create runbook for common AI assistant issues

**Checkpoint**: Complete documentation and monitoring in place

---

## Dependencies Between User Stories

```mermaid
graph TD
    Setup[Phase 1: Setup] --> Foundation[Phase 2: Foundation]
    Foundation --> US1[US1: Product Discovery]
    Foundation --> US2[US2: Price Search]
    Foundation --> US3[US3: Product Details]
    US1 --> US4[US4: Cart Management]
    US2 --> US4
    US3 --> US4
    US4 --> US5[US5: Checkout]
    Foundation --> US6[US6: Role-Aware]
    Foundation --> US7[US7: Memory]
    US1 --> Agent[Phase 10: Agent]
    US2 --> Agent
    US3 --> Agent
    US4 --> Agent
    US5 --> Agent
    US6 --> Agent
    US7 --> Agent
    Agent --> Integration[Phase 11: Integration Tests]
    Integration --> Frontend[Phase 12: Frontend]
    Frontend --> Security[Phase 13: Security]
    Security --> Docs[Phase 14: Documentation]
    Docs --> Enhancement[Phase 15: Conversational Enhancement]
    US1 --> Enhancement
    US2 --> Enhancement
    US3 --> Enhancement
    US4 --> Enhancement
    US5 --> Enhancement
    Agent --> Enhancement
```

---

## Parallel Execution Opportunities

### Phase 3 (US1) - Product Discovery
- **Parallel**: T121, T122, T123 (tests and use case - different files)
- **Sequential**: T124 → T125 → T126 → T127 (tool creation depends on use case)

### Phase 4 (US2) - Price Search
- **Parallel**: T128, T129, T130 (tests and use case)
- **Sequential**: T131 → T132 → T133 → T134 (tool chain)

### Phase 5 (US3) - Product Details
- **Parallel**: T135, T136, T137, T138, T139, T140 (2 use cases + 4 tests - all different files)
- **Sequential**: T141 → T142, T143 → T144 (each tool's schema)
- **Parallel**: T145, T146 (registration tasks)

### Phase 6 (US4) - Cart Management
- **Parallel**: T147, T148, T149, T150, T151, T152 (2 use cases + 4 tests)
- **Sequential**: T153 → T154, T155 → T156 (tool implementations)
- **Parallel**: T157, T158, T159 (configuration tasks)

### Phase 11 - Integration Testing
- **Fully Parallel**: T195-T208 (all test files independent)

### Phase 13 - Security & Performance
- **Fully Parallel**: T217-T227 (independent optimization tasks)

### Phase 14 - Documentation
- **Fully Parallel**: T228-T234 (independent documentation files)

### Phase 15 - Conversational Enhancement (Spanish + Name-Based)
- **Sequential Agent Updates**: T235 → T236 → T237 → T238 → T239 → T240 (system prompt configuration)
- **Fully Parallel Tool Refactoring**: T241-T247 (independent tool modifications)
- **Fully Parallel Use Cases**: T248-T263 (all use cases in different files)
- **Fully Parallel New Tools**: T264-T287 (all tool implementations independent)
- **Sequential Registration**: T288 → T289 → T290 → T291 → T292 (service configuration)
- **Fully Parallel Flow Tests**: T293-T301 (all E2E tests independent)
- **Fully Parallel Validation Tests**: T302-T310 (all validation tests independent)
- **Fully Parallel Documentation**: T311-T315 (all documentation files independent)

---

## Implementation Strategy

### MVP Scope (Phases 1-5)
Priority: Complete User Stories 1-3 for immediate value
- **Week 1**: Setup + Foundation (T101-T120)
- **Week 2**: US1 Product Discovery (T121-T127)
- **Week 3**: US2 Price Search + US3 Product Details (T128-T146)
- **Week 4**: Agent integration + basic testing

### Full Feature Scope (Phases 1-14)
- **Weeks 5-6**: US4 Cart + US5 Checkout (T147-T170)
- **Week 7**: US6 Role + US7 Memory (T171-T187)
- **Week 8**: Agent orchestration (T188-T194)
- **Weeks 9-10**: Integration testing (T195-T208)
- **Week 11**: Frontend + Security (T209-T227)
- **Week 12**: Documentation + Launch (T228-T234)

### Enhanced Conversational Scope (Phases 1-15)
- **Weeks 13-14**: Phase 15 Agent Enhancement (T235-T247)
- **Weeks 15-16**: Phase 15 New Use Cases (T248-T263)
- **Weeks 17-18**: Phase 15 New Tools (T264-T287)
- **Week 19**: Phase 15 Configuration & Registration (T288-T292)
- **Week 20**: Phase 15 Testing (T293-T310)
- **Week 21**: Phase 15 Documentation & Launch (T311-T315)

---

## Success Criteria

- ✅ All 215 tasks completed
- ✅ All 7 user stories independently testable
- ✅ Test coverage >80% for Application and Infrastructure/AI layers
- ✅ Zero business logic in Infrastructure/AI/Tool classes
- ✅ All tools properly annotated with #[AsTool]
- ✅ Agent handles 20+ conversational scenarios
- ✅ Response time <3s for tool-heavy conversations
- ✅ No security vulnerabilities in penetration testing
- ✅ Documentation complete for adding new tools
- ✅ **Phase 15**: All agent responses exclusively in Spanish
- ✅ **Phase 15**: Zero internal IDs (UUIDs, database keys) exposed to users
- ✅ **Phase 15**: All tools accept and return human-readable product names
- ✅ **Phase 15**: Agent handles complete shopping lifecycle (browse → checkout → status)
- ✅ **Phase 15**: Explicit confirmation required for all sensitive operations
- ✅ **Phase 15**: Agent maintains friendly, professional, empathetic tone

---

## Task Count Summary

- **Phase 1 (Setup)**: 11 tasks (T101-T111)
- **Phase 2 (Foundation)**: 9 tasks (T112-T120)
- **Phase 3 (US1)**: 7 tasks (T121-T127)
- **Phase 4 (US2)**: 7 tasks (T128-T134)
- **Phase 5 (US3)**: 12 tasks (T135-T146)
- **Phase 6 (US4)**: 13 tasks (T147-T159)
- **Phase 7 (US5)**: 11 tasks (T160-T170)
- **Phase 8 (US6)**: 6 tasks (T171-T178)
- **Phase 9 (US7)**: 7 tasks (T179-T187)
- **Phase 10 (Agent)**: 7 tasks (T188-T194)
- **Phase 11 (Integration)**: 14 tasks (T195-T208)
- **Phase 12 (Frontend)**: 8 tasks (T209-T216)
- **Phase 13 (Security)**: 11 tasks (T217-T227)
- **Phase 14 (Documentation)**: 7 tasks (T228-T234)

**TOTAL: 215 tasks** (updated to include Phase 15)

---

## Phase 15: Conversational Commerce Assistant Enhancement (Spanish + Human-Readable Interactions)

**Purpose**: Evolve the chatbot into a fully conversational Spanish-speaking commerce assistant that operates using human-readable product names and guides users through the complete shopping lifecycle

**Goal**: Enable natural, human-friendly interactions in Spanish without exposing any internal identifiers

**Independent Test**: User completes full shopping flow (browse → select → add to cart → checkout → check order status) entirely in Spanish using only product names

### Agent Behavior Updates

- [X] T235 Update system prompt to require Spanish-only responses in config/packages/ai.yaml
- [X] T236 Add virtual assistant persona in system prompt (friendly, professional, empathetic tone) in config/packages/ai.yaml
- [X] T237 Add instruction to NEVER expose internal IDs (UUIDs, database keys) in system prompt
- [X] T238 Add instruction to use product names only in all interactions in system prompt
- [X] T239 Add instruction to confirm user intent before sensitive actions (checkout, order creation) in system prompt
- [X] T240 Add full lifecycle conversation flow capabilities to system prompt in config/packages/ai.yaml

**Checkpoint**: Agent behavior configured for natural Spanish conversations

### Existing Tools Refactoring (Name-Based Interactions)

- [X] T241 [P] Refactor GetProductsNameTool to include descriptions, prices, and availability in src/Infrastructure/AI/Tool/GetProductsNameTool.php
- [X] T242 [P] Refactor GetProductsNameByMaxPriceTool to accept product names and return enriched data in src/Infrastructure/AI/Tool/GetProductsNameByMaxPriceTool.php
- [X] T243 [P] Update all existing tool descriptions to Spanish in tool annotations
- [X] T244 [P] Add internal name-to-ID resolution logic in each tool that currently uses IDs
- [X] T245 Update GetPriceByProductIdTool to accept product name instead of ID in src/Infrastructure/AI/Tool/GetPriceByProductIdTool.php
- [X] T246 Update GetProductImagesByProductIdTool to accept product name instead of ID in src/Infrastructure/AI/Tool/GetProductImagesByProductIdTool.php
- [X] T247 Update AddToCartTool to accept product name instead of ID in src/Infrastructure/AI/Tool/AddToCartTool.php

**Checkpoint**: All existing tools refactored for name-based interactions

### New Use Cases - Product Management

- [X] T248 [P] Create ListProductsUseCase with filters (category, availability) in src/Application/UseCase/AI/ListProducts.php
- [X] T249 [P] Create GetProductDetailsByNameUseCase in src/Application/UseCase/AI/GetProductDetailsByName.php
- [ ] T250 [P] Create unit test for ListProducts use case in tests/Application/UseCase/AI/ListProductsTest.php
- [ ] T251 [P] Create unit test for GetProductDetailsByName use case in tests/Application/UseCase/AI/GetProductDetailsByNameTest.php

### New Use Cases - Cart Management

- [X] T252 [P] Create RemoveProductFromCartUseCase with name-based removal in src/Application/UseCase/AI/RemoveProductFromCart.php
- [X] T253 [P] Create GetCartSummaryUseCase with detailed line items in src/Application/UseCase/AI/GetCartSummary.php
- [ ] T254 [P] Create unit test for RemoveProductFromCart use case in tests/Application/UseCase/AI/RemoveProductFromCartTest.php
- [ ] T255 [P] Create unit test for GetCartSummary use case in tests/Application/UseCase/AI/GetCartSummaryTest.php

### New Use Cases - Checkout & Orders

- [X] T256 [P] Create CollectCheckoutInformationUseCase for conversational data collection in src/Application/UseCase/AI/CollectCheckoutInformation.php
- [X] T257 [P] Create CreateOrderUseCase with confirmation validation in src/Application/UseCase/AI/CreateOrder.php
- [X] T258 [P] Create ListPreviousOrdersUseCase for authenticated user in src/Application/UseCase/AI/ListPreviousOrders.php
- [X] T259 [P] Create GetOrderStatusUseCase with human-friendly reference in src/Application/UseCase/AI/GetOrderStatus.php
- [ ] T260 [P] Create unit test for CollectCheckoutInformation use case in tests/Application/UseCase/AI/CollectCheckoutInformationTest.php
- [ ] T261 [P] Create unit test for CreateOrder use case in tests/Application/UseCase/AI/CreateOrderTest.php
- [ ] T262 [P] Create unit test for ListPreviousOrders use case in tests/Application/UseCase/AI/ListPreviousOrdersTest.php
- [ ] T263 [P] Create unit test for GetOrderStatus use case in tests/Application/UseCase/AI/GetOrderStatusTest.php

### New AI Tools - Product Management

- [X] T264 [P] Create ListProductsTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/ListProductsTool.php
- [X] T265 [P] Implement ListProductsTool parameter schema (optional: category, availability) in ListProductsTool
- [X] T266 [P] Create GetProductDetailsTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/GetProductDetailsTool.php
- [X] T267 [P] Implement GetProductDetailsTool parameter schema (productName: string) in GetProductDetailsTool
- [ ] T268 [P] Create integration test for ListProductsTool in tests/Infrastructure/AI/Tool/ListProductsToolTest.php
- [ ] T269 [P] Create integration test for GetProductDetailsTool in tests/Infrastructure/AI/Tool/GetProductDetailsToolTest.php

### New AI Tools - Cart Management

- [X] T270 [P] Create RemoveProductFromCartTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/RemoveProductFromCartTool.php
- [X] T271 [P] Implement RemoveProductFromCartTool parameter schema (productName: string) in RemoveProductFromCartTool
- [X] T272 [P] Create GetCartSummaryTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/GetCartSummaryTool.php
- [X] T273 [P] Implement GetCartSummaryTool to return product names, quantities, prices, and total in GetCartSummaryTool
- [ ] T274 [P] Create integration test for RemoveProductFromCartTool in tests/Infrastructure/AI/Tool/RemoveProductFromCartToolTest.php
- [ ] T275 [P] Create integration test for GetCartSummaryTool in tests/Infrastructure/AI/Tool/GetCartSummaryToolTest.php

### New AI Tools - Checkout & Orders

- [X] T276 [P] Create CollectCheckoutInformationTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/CollectCheckoutInformationTool.php
- [X] T277 [P] Implement CollectCheckoutInformationTool parameter schema (address, paymentMethod, contactInfo) in CollectCheckoutInformationTool
- [X] T278 [P] Create CreateOrderTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/CreateOrderTool.php
- [X] T279 [P] Implement CreateOrderTool with explicit user confirmation requirement in CreateOrderTool
- [X] T280 [P] Create ListPreviousOrdersTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/ListPreviousOrdersTool.php
- [X] T281 [P] Implement ListPreviousOrdersTool to return human-friendly order references in ListPreviousOrdersTool
- [X] T282 [P] Create GetOrderStatusTool with #[AsTool] annotation (Spanish description) in src/Infrastructure/AI/Tool/GetOrderStatusTool.php
- [X] T283 [P] Implement GetOrderStatusTool parameter schema (orderReference: string, not ID) in GetOrderStatusTool
- [ ] T284 [P] Create integration test for CollectCheckoutInformationTool in tests/Infrastructure/AI/Tool/CollectCheckoutInformationToolTest.php
- [ ] T285 [P] Create integration test for CreateOrderTool in tests/Infrastructure/AI/Tool/CreateOrderToolTest.php
- [ ] T286 [P] Create integration test for ListPreviousOrdersTool in tests/Infrastructure/AI/Tool/ListPreviousOrdersToolTest.php
- [ ] T287 [P] Create integration test for GetOrderStatusTool in tests/Infrastructure/AI/Tool/GetOrderStatusToolTest.php

### Service Configuration & Registration

- [X] T288 Register all new product management tools as tagged services in config/services.yaml
- [X] T289 Register all new cart management tools as tagged services in config/services.yaml
- [X] T290 Register all new checkout/order tools as tagged services in config/services.yaml
- [X] T291 Add all 9 new tools to agent tool registry in config/packages/ai.yaml
- [X] T292 Update ShoppingAssistantAgent to include new tools in tool registry in src/Infrastructure/AI/Agent/ShoppingAssistantAgent.php

**Checkpoint**: All new tools registered and available to the agent

### Conversational Flow Testing

- [ ] T293 [P] Create E2E test: Complete product discovery flow in Spanish in tests/E2E/SpanishProductDiscoveryTest.php
- [ ] T294 [P] Create E2E test: Product selection and comparison in Spanish in tests/E2E/SpanishProductComparisonTest.php
- [ ] T295 [P] Create E2E test: Add/remove products to/from cart using names only in tests/E2E/NameBasedCartManagementTest.php
- [ ] T296 [P] Create E2E test: Display cart summary with product names in tests/E2E/CartSummaryDisplayTest.php
- [ ] T297 [P] Create E2E test: Conversational checkout data collection in Spanish in tests/E2E/SpanishCheckoutFlowTest.php
- [ ] T298 [P] Create E2E test: Order creation with explicit confirmation in Spanish in tests/E2E/SpanishOrderCreationTest.php
- [ ] T299 [P] Create E2E test: List previous orders with human-friendly references in tests/E2E/OrderListingTest.php
- [ ] T300 [P] Create E2E test: Order status inquiry using order reference in tests/E2E/OrderStatusInquiryTest.php
- [ ] T301 [P] Create E2E test: Full end-to-end shopping lifecycle (browse → checkout → status) in tests/E2E/CompleteShoppingLifecycleTest.php

### ID Exposure Prevention Testing

- [ ] T302 [P] Create test: Verify no UUIDs appear in agent responses in tests/Infrastructure/AI/NoIDExposureTest.php
- [ ] T303 [P] Create test: Verify all tools accept names not IDs in input validation in tests/Infrastructure/AI/NameBasedInputTest.php
- [ ] T304 [P] Create test: Verify all tools output names not IDs in response validation in tests/Infrastructure/AI/NameBasedOutputTest.php
- [ ] T305 [P] Create test: Verify order references are human-friendly (not UUIDs) in tests/Infrastructure/AI/OrderReferenceFormatTest.php

### Agent Behavior Validation Testing

- [ ] T306 [P] Create test: Verify all agent responses are in Spanish in tests/Infrastructure/AI/SpanishResponseTest.php
- [ ] T307 [P] Create test: Verify agent requests confirmation before checkout in tests/Infrastructure/AI/CheckoutConfirmationTest.php
- [ ] T308 [P] Create test: Verify agent requests confirmation before order creation in tests/Infrastructure/AI/OrderConfirmationTest.php
- [ ] T309 [P] Create test: Verify agent tone is friendly, professional, empathetic in tests/Infrastructure/AI/AgentToneTest.php
- [ ] T310 [P] Create test: Verify agent handles full conversational flow without explicit commands in tests/Infrastructure/AI/ConversationalFlowTest.php

**Checkpoint**: All conversational and validation tests complete

### Documentation & Migration

- [ ] T311 [P] Document name-to-ID resolution strategy in .specify/specs/002-ai-shopping-assistant/NAME_RESOLUTION.md
- [ ] T312 [P] Update README with Spanish agent capabilities in .specify/specs/002-ai-shopping-assistant/README.md
- [ ] T313 [P] Create migration guide for existing users (ID-based → name-based) in .specify/specs/002-ai-shopping-assistant/MIGRATION.md
- [ ] T314 [P] Add examples of conversational flows in Spanish to documentation in .specify/specs/002-ai-shopping-assistant/EXAMPLES.md
- [ ] T315 [P] Update API.md with new tool endpoints and Spanish descriptions in API.md

**Checkpoint**: Phase 15 complete - Fully conversational Spanish commerce assistant deployed

---

## Updated Task Count Summary

- **Phase 1 (Setup)**: 11 tasks (T101-T111)
- **Phase 2 (Foundation)**: 9 tasks (T112-T120)
- **Phase 3 (US1)**: 7 tasks (T121-T127)
- **Phase 4 (US2)**: 7 tasks (T128-T134)
- **Phase 5 (US3)**: 12 tasks (T135-T146)
- **Phase 6 (US4)**: 13 tasks (T147-T159)
- **Phase 7 (US5)**: 11 tasks (T160-T170)
- **Phase 8 (US6)**: 6 tasks (T171-T178)
- **Phase 9 (US7)**: 7 tasks (T179-T187)
- **Phase 10 (Agent)**: 7 tasks (T188-T194)
- **Phase 11 (Integration)**: 14 tasks (T195-T208)
- **Phase 12 (Frontend)**: 8 tasks (T209-T216)
- **Phase 13 (Security)**: 11 tasks (T217-T227)
- **Phase 14 (Documentation)**: 7 tasks (T228-T234)
- **Phase 15 (Conversational Enhancement)**: 81 tasks (T235-T315)

**TOTAL: 215 tasks**

---

## Notes

1. **Tests are REQUIRED**: Each use case and tool must have corresponding unit/integration tests
2. **DDD Compliance**: Business logic ONLY in Application layer, tools are pure adapters
3. **Parallel Execution**: Tasks marked [P] can be executed simultaneously
4. **MVP Focus**: Phases 1-5 (US1-US3) deliver immediate value
5. **Security First**: All tools validate authentication and authorization
6. **Documentation**: Each phase includes inline documentation requirements7. **Phase 15 Priority**: Conversational enhancement builds on MVP (Phases 1-5) and can be executed after foundational phases
8. **Spanish Language**: All agent interactions in Phase 15 must be exclusively in Spanish
9. **Name Resolution Strategy**: All tools in Phase 15 must internally resolve product names to IDs while maintaining clean, human-readable interfaces
10. **No ID Exposure**: System must NEVER expose UUIDs, database IDs, or internal identifiers to end users
11. **Confirmation Required**: Agent must explicitly request user confirmation before sensitive operations (checkout, order creation)
12. **Conversational Flow**: Agent must handle full shopping lifecycle naturally without requiring explicit commands