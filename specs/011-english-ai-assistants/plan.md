# Implementation Plan: English AI Assistants with Context Persistence

## Phase 1: Investigation & Documentation (P3 - Foundation)

### Task 1.1: Locate Conversation Storage
- [x] Verify conversations stored in MySQL using Doctrine
- [x] Repository: `DoctrineConversationRepository`
- [x] Entity: `Conversation` and `ConversationMessage`
- [ ] Document schema in implementation notes

### Task 1.2: Locate Context Storage
- [x] Verify context stored in Redis via `ContextStorageInterface`
- [x] Customer context: `CustomerContextManager`
- [x] Admin context: `AdminContextManager`
- [ ] Document Redis keys and TTL configuration

### Task 1.3: Identify System Prompts
- [ ] Locate AI agent configuration (`config/packages/ai.yaml`)
- [ ] Find tool descriptions in `src/Infrastructure/AI/Tool/`
- [ ] Identify Spanish strings in error messages/responses
- [ ] Document all prompt locations

## Phase 2: English Language Conversion (P1 - Customer)

### Task 2.1: Convert Customer Chatbot System Prompts
- [ ] Update AI agent system prompt to English
- [ ] Convert tool descriptions to English
- [ ] Update fallback error messages in `ChatbotController`
- [ ] Test customer chatbot responses

### Task 2.2: Convert Customer-Facing Tool Descriptions
- [ ] `SemanticProductSearchTool` description → English
- [ ] `AddToCartTool` description → English
- [ ] `GetCartTool` description → English  
- [ ] Other customer tools → English

### Task 2.3: Update Customer Response Formatting
- [ ] Currency formatting (USD $99.99)
- [ ] Date formatting (February 7, 2026)
- [ ] Stock status messages ("In stock", "Low stock")
- [ ] Success/error messages

## Phase 3: English Language Conversion (P1 - Admin)

### Task 3.1: Convert Admin Assistant System Prompts
- [ ] Locate admin assistant configuration
- [ ] Update system prompts to professional English
- [ ] Update admin tool descriptions
- [ ] Test admin assistant responses

### Task 3.2: Convert Admin-Facing Tool Descriptions
- [ ] Admin tools in `src/Infrastructure/AI/Tool/Admin/`
- [ ] Update confirmations to English
- [ ] Update error messages to English
- [ ] Update command help text

### Task 3.3: Update Admin Response Formatting
- [ ] Business terminology in English
- [ ] Professional tone
- [ ] Clear command feedback

## Phase 4: Context Persistence Verification (P2)

### Task 4.1: Verify Context Save Logic
- [ ] Check `CustomerContextManager::saveContext()`
- [ ] Verify context saved after each interaction
- [ ] Test context TTL refresh
- [ ] Add error logging if missing

### Task 4.2: Verify Context Load Logic
- [ ] Check `CustomerContextManager::loadContext()`
- [ ] Verify context loaded on return visit
- [ ] Test cross-session persistence
- [ ] Handle expired context gracefully

### Task 4.3: Test Context Persistence
- [ ] Create test conversation
- [ ] Close browser
- [ ] Return and verify context recalled
- [ ] Test context expiry (if feasible)

## Phase 5: Documentation (P3)

### Task 5.1: Create Storage Documentation
- [ ] Document MySQL conversation schema
- [ ] Document Redis context keys
- [ ] Provide example queries
- [ ] Add troubleshooting guide

### Task 5.2: Create Developer Guide
- [ ] How to query conversations
- [ ] How to inspect context
- [ ] How to debug context issues
- [ ] Configuration options (TTL, retention)

## Success Validation

- [ ] SC-001: 100% customer responses in English
- [ ] SC-002: 100% admin responses in professional English
- [ ] SC-003: Context retrieval success rate 95%+
- [ ] SC-005: Zero Spanish in system responses
- [ ] SC-007: Context load < 200ms

## Rollback Plan

If issues arise:
1. Keep English in feature branch
2. Can revert to master quickly
3. Context changes are non-breaking (Redis TTL-based)
