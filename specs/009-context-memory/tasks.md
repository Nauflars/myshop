# Tasks: Conversational Context & Memory Management

**Feature Branch**: `009-context-memory`  
**Input**: Design documents from `/specs/009-context-memory/`  
**Prerequisites**: spec.md (user stories and functional requirements), spec-007 (existing conversation infrastructure)

**Tech Stack**: Symfony PHP 8.3, Redis, Symfony AI Agent framework, Doctrine ORM, JavaScript (Vanilla)  
**Architecture**: Domain-Driven Design (Domain/Application/Infrastructure layers)

**Dependencies**: Builds on spec-007 and spec-008 infrastructure (ConversationManager, AdminAssistantController, chatbot.js, admin-floating-assistant.js)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

---

## Phase 0: Infrastructure Setup (Prerequisites for all user stories)

**Purpose**: Set up Redis for context storage and create base context management classes

**Goal**: Redis available and base context abstractions in place

**Independent Test**: Start Redis container, verify connection via Symfony console command, create/retrieve/delete test context, verify TTL expiration works.

### Implementation for Infrastructure

- [X] T001 [P] Add Redis service to docker-compose.yml with configuration (port 6379, persistent volume)
- [X] T002 [P] Add symfony/redis-bundle to composer.json dependencies
- [X] T003 [P] Configure Redis connection in config/packages/redis.yaml
- [X] T004 [P] Create ConversationContext abstract base class in src/Domain/ValueObject/ConversationContext.php
- [X] T005 [P] Create ContextStorageInterface in src/Domain/Repository/ContextStorageInterface.php
- [X] T006 [P] Create RedisContextStorage implementation in src/Infrastructure/Repository/RedisContextStorage.php
- [X] T007 [P] Create ContextSerializer service in src/Application/Service/ContextSerializer.php
- [X] T008 Add context TTL configuration to .env (CUSTOMER_CONTEXT_TTL=1800, ADMIN_CONTEXT_TTL=3600)
- [X] T009 Create Symfony console command to test context storage in src/Command/TestContextCommand.php
- [ ] T010 Write unit tests for ConversationContext and RedisContextStorage in tests/Unit/

**Checkpoint**: Redis running, context storage working, base classes created and tested

---

## Phase 1: Customer Context Implementation (User Story 1 - Priority P1)

**Purpose**: Enable follow-up questions in customer chat by maintaining conversational context

**Goal**: Customer can ask "Show me laptops" then "What's the cheapest one?" and assistant understands context

**Independent Test**: Login as customer, ask "Show me laptops", verify assistant responds with laptops. Ask "What's the cheapest one?", verify assistant identifies cheapest laptop from previous context without asking which product category. Check Redis to confirm context stored with key `chat:customer:{userId}`.

### Implementation for User Story 1

- [X] T011 [P] Create CustomerConversationContext value object in src/Domain/ValueObject/CustomerConversationContext.php
- [X] T012 [P] Define customer context attributes: userId, flow, selectedProducts, cartSnapshot, lastTool, language
- [X] T013 [P] Create CustomerContextManager service in src/Application/Service/CustomerContextManager.php
- [X] T014 Update existing ChatbotController to load context before AI interaction in src/Infrastructure/Controller/ChatbotController.php
- [ ] T015 Implement context injection into AI system prompt in CustomerContextManager::enrichPrompt()
- [X] T016 Update ChatbotController to save context after successful message in src/Infrastructure/Controller/ChatbotController.php
- [ ] T017 [P] Create context update logic after tool executions in CustomerContextManager::updateAfterToolExecution()
- [ ] T018 Add context serialization for customer context in ContextSerializer service
- [ ] T019 Update chatbot.js to send conversationId on every message (already implemented, verify)
- [ ] T020 Write integration tests for customer context persistence in tests/Integration/Context/

**Checkpoint**: Customer conversations remember context, follow-up questions work naturally

---

## Phase 2: Admin Context Implementation (User Story 2 - Priority P1)

**Purpose**: Enable multi-step admin operations with context retention

**Goal**: Admin can initiate stock update, system remembers product across confirmation step

**Independent Test**: Login as admin, ask "Show low stock products", verify list appears. Say "Update stock for Product X to 50", verify assistant asks for confirmation. Say "Confirm", verify database updated and assistant remembers which product. Check Redis context key `chat:admin:{adminId}` contains pendingActions.

### Implementation for User Story 2

- [X] T021 [P] Create AdminConversationContext value object in src/Domain/ValueObject/AdminConversationContext.php
- [X] T022 [P] Define admin context attributes: adminId, flow, activeEntities, timePeriod, pendingActions, lastTool
- [X] T023 [P] Create AdminContextManager service in src/Application/Service/AdminContextManager.php
- [ ] T024 Update AdminAssistantController to load context before AI interaction in src/Infrastructure/Controller/AdminAssistantController.php
- [ ] T025 Implement context injection into admin AI system prompt in AdminContextManager::enrichPrompt()
- [ ] T026 Update AdminAssistantController to save context after successful message
- [ ] T027 [P] Create context update logic for admin operations in AdminContextManager::updateAfterToolExecution()
- [ ] T028 [P] Implement pending action storage and retrieval in AdminConversationContext
- [ ] T029 Add context serialization for admin context in ContextSerializer service
- [ ] T030 Update admin-floating-assistant.js to send conversationId on every message (already implemented, verify)
- [ ] T031 Write integration tests for admin context persistence in tests/Integration/Context/

**Checkpoint**: Admin multi-step workflows work, pending actions persisted across messages

---

## Phase 3: Context Persistence Across Navigation (User Story 3 - Priority P2)

**Purpose**: Maintain context when user navigates between pages

**Goal**: Context survives page reloads and navigation within session

**Independent Test**: Start conversation on Products page, navigate to Cart page, continue conversation and verify previous context maintained. Verify Redis TTL resets on each interaction. Verify context survives hard page refresh (F5).

### Implementation for User Story 3

- [ ] T032 Verify customerconversationId persists in localStorage (already implemented in chatbot.js, needs testing)
- [ ] T033 Verify admin conversationId persists in localStorage (already implemented in admin-floating-assistant.js, needs testing)
- [ ] T034 Implement context TTL refresh on every interaction in CustomerContextManager
- [ ] T035 Implement context TTL refresh on every interaction in AdminContextManager
- [ ] T036 Add logging for context load attempts in ContextStorageInterface implementations
- [ ] T037 Test customer context across navigation: Products → Cart → Checkout flow
- [ ] T038 Test admin context across navigation: Dashboard → Products → Users → Orders flow
- [ ] T039 Add E2E test for context persistence after page navigation in tests/E2E/
- [ ] T040 Verify context key generation is deterministic (same user = same key every time)

**Checkpoint**: Context persists across all page navigations within TTL window, conversationId stored in localStorage

---

## Phase 4: Context Recovery & Error Handling (User Story 4 - Priority P3)

**Purpose**: Handle expired contexts gracefully and provide clear recovery path

**Goal**: When context expires, user gets clear message and can restart naturally

**Independent Test**: Start conversation, wait 31 minutes (customer TTL + 1), send new message, verify system detects expired context, creates fresh context, and informs user gracefully without errors.

### Implementation for User Story 4

- [ ] T041 [P] Implement context expiration detection in CustomerContextManager::loadContext()
- [ ] T042 [P] Implement context expiration detection in AdminContextManager::loadContext()
- [ ] T043 Create friendly expiration message for customer assistant in CustomerContextManager
- [ ] T044 Create friendly expiration message for admin assistant in AdminContextManager
- [ ] T045 Implement automatic fresh context creation on expiration
- [ ] T046 Add "New Conversation" button to both chat interfaces (chatbot.js, admin-floating-assistant.js)
- [ ] T047 Implement manual context reset endpoint POST /api/chat/reset-context for customer
- [ ] T048 Implement manual context reset endpoint POST /admin/assistant/reset-context for admin
- [ ] T049 Add error handling for Redis connection failures (fallback to stateless mode with warning)
- [ ] T050 Write tests for context expiration scenarios in tests/Integration/Context/

**Checkpoint**: Expired contexts handled gracefully, users can reset context manually, system degrades gracefully if Redis unavailable

---

## Phase 5: Security & Privacy (Critical - Parallel with all phases)

**Purpose**: Ensure context data is secure, scoped, and doesn't leak between roles

**Goal**: Zero incidents of cross-role context leakage, no sensitive data in context

**Independent Test**: Login as admin, verify admin context key includes admin ID. Login as customer with same email, verify completely separate customer context key. Attempt to craft malicious conversationId, verify system rejects it and creates new context scoped to actual user. Inspect Redis keys, verify no passwords or payment data stored.

### Implementation for Security

- [ ] T051 [P] Implement role-based context key generation (admin vs customer) in ContextStorageInterface
- [ ] T052 [P] Add user ID validation in context retrieval (verify user owns context) in RedisContextStorage
- [ ] T053 [P] Implement context isolation test: verify admin context never returned to customer
- [ ] T054 [P] Implement context isolation test: verify customer context never returned to admin
- [ ] T055 Add audit logging for context access in src/Application/Service/ContextAuditLogger.php
- [ ] T056 Add audit logging for context mutations (create, update, delete)
- [ ] T057 Implement sensitive data filter in ContextSerializer (strip passwords, card numbers, etc.)
- [ ] T058 Add environment check: refuse to start if Redis not available in production mode
- [ ] T059 Create security test suite for context isolation in tests/Security/ContextSecurityTest.php
- [ ] T060 Review all context attributes to ensure GDPR/privacy compliance

**Checkpoint**: Context system is secure, data is protected, audit trail in place, roles isolated

---

## Phase 6: Performance & Monitoring (Optimization)

**Purpose**: Ensure context operations don't degrade system performance

**Goal**: Context load/save operations <50ms at 95th percentile

**Independent Test**: Run load test with 100 concurrent users, each having active context. Measure context load/save times via application metrics. Verify 95th percentile <50ms. Monitor Redis memory usage under load.

### Implementation for Performance

- [ ] T061 [P] Add performance monitoring for context operations using Symfony Stopwatch
- [ ] T062 [P] Create ContextMetricsCollector service in src/Application/Service/ContextMetricsCollector.php
- [ ] T063 Implement context size limit (max 5KB per context) to prevent memory bloat
- [ ] T064 Add context summary truncation for very long conversations (>50 messages)
- [ ] T065 Implement efficient context serialization (use MessagePack or optimized JSON)
- [ ] T066 Add Redis connection pooling configuration in config/packages/redis.yaml
- [ ] T067 Create dashboard for context metrics (active contexts, hit rate, avg size)
- [ ] T068 Add alerting for context storage errors or performance degradation
- [ ] T069 Write performance tests for context operations in tests/Performance/
- [ ] T070 Document Redis memory requirements and scaling recommendations

**Checkpoint**: Context system performs efficiently, metrics collected, production-ready

---

## Phase 7: Testing & Documentation (Final validation)

**Purpose**: Comprehensive testing and documentation for production deployment

**Goal**: All acceptance criteria met, tests passing, documentation complete

**Independent Test**: Run full test suite (unit, integration, E2E, security, performance). All tests pass. Documentation covers setup, configuration, troubleshooting. Team can deploy to production with confidence.

### Implementation for Testing & Docs

- [ ] T071 Write E2E test for US1: Follow-up questions in customer chat (3-message conversation)
- [ ] T072 Write E2E test for US2: Admin multi-step workflow (query → action → confirm)
- [ ] T073 Write E2E test for US3: Context persistence across navigation
- [ ] T074 Write E2E test for US4: Context expiration and recovery
- [ ] T075 Create README.md in specs/009-context-memory/ with architecture overview
- [ ] T076 Document context schema for customer and admin in docs/context-schema.md
- [ ] T077 Create troubleshooting guide for common context issues
- [ ] T078 Add deployment checklist (Redis setup, environment variables, migrations)
- [ ] T079 Run full test suite and verify all success criteria met
- [ ] T080 Conduct code review and security audit before merge to master

**Checkpoint**: Feature complete, tested, documented, ready for production

---

## Success Criteria Validation

### After Phase 7 completion, verify:

- [ ] **Follow-up Accuracy**: 95%+ of follow-up questions answered correctly using context
- [ ] **Multi-step Completion**: 3-step workflows (query → action → confirm) work without repeating info
- [ ] **Context Persistence**: 100% survival rate across page navigation within TTL window
- [ ] **Recovery Time**: System recovers from expired context in <1 second
- [ ] **Zero Context Leakage**: 0 security incidents in testing (admin/customer isolation verified)
- [ ] **Performance**: Context operations <50ms at 95th percentile under load
- [ ] **User Satisfaction**: Conversational coherence improved by 40%+ (measure via A/B test or user feedback)

---

## Post-Deployment

- [ ] Monitor context storage growth and Redis memory usage
- [ ] Collect metrics on context hit rate and average conversation length
- [ ] Gather user feedback on conversational quality improvement
- [ ] Plan Phase 8: Context summarization for very long conversations (future spec)
- [ ] Plan Phase 9: Cross-session memory and personalization (future spec)

---

## Dependencies Matrix

| Phase | Depends On | Blocks |
|-------|------------|--------|
| Phase 0 | None | All other phases |
| Phase 1 | Phase 0 | Phase 3, Phase 7 |
| Phase 2 | Phase 0 | Phase 3, Phase 7 |
| Phase 3 | Phase 0, 1, 2 | Phase 7 |
| Phase 4 | Phase 0, 1, 2 | Phase 7 |
| Phase 5 | Phase 0 | None (parallel with all) |
| Phase 6 | Phase 0, 1, 2 | Phase 7 |
| Phase 7 | All phases | Deployment |

---

## Estimated Effort

- **Phase 0**: 2-3 days (Redis setup + base classes)
- **Phase 1**: 3-4 days (Customer context implementation)
- **Phase 2**: 3-4 days (Admin context implementation)
- **Phase 3**: 1-2 days (Navigation testing)
- **Phase 4**: 2-3 days (Expiration handling)
- **Phase 5**: 2-3 days (Security hardening) - parallel
- **Phase 6**: 2-3 days (Performance optimization) - parallel
- **Phase 7**: 3-4 days (Testing & documentation)

**Total**: ~18-26 days (can be reduced with Phase 5 & 6 parallelization)

---

## Notes

- Phase 5 (Security) tasks can be worked on in parallel with other phases
- Phase 6 (Performance) can start once Phase 0-2 are stabilized
- Redis must be added to docker-compose.yml and configured before any context work begins
- Consider using Redis Cluster or Redis Sentinel for production high availability
- Context data should be treated as ephemeral - don't use Redis as primary data store
- All sensitive data must be filtered before context serialization
- TTL values should be configurable per environment (dev: 5min for testing, prod: 30/60min)
