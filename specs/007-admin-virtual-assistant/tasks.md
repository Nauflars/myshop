# Tasks: Admin Virtual Assistant

**Feature Branch**: `007-admin-virtual-assistant`  
**Input**: Design documents from `/specs/007-admin-virtual-assistant/`  
**Prerequisites**: spec.md (user stories and functional requirements)

**Tech Stack**: Symfony PHP 8.3, Symfony AI Agent framework, Doctrine ORM, Twig templates, MySQL  
**Architecture**: Domain-Driven Design (Domain/Application/Infrastructure layers)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create dedicated admin assistant agent configuration and database schema

- [ ] T001 Create AdminAssistantConversation entity in src/Domain/Entity/AdminAssistantConversation.php
- [ ] T002 [P] Create AdminAssistantMessage entity in src/Domain/Entity/AdminAssistantMessage.php
- [ ] T003 [P] Create AdminAssistantAction entity for audit logging in src/Domain/Entity/AdminAssistantAction.php
- [ ] T004 Create database migration for admin_assistant_conversations, admin_assistant_messages, admin_assistant_actions tables in migrations/
- [ ] T005 Configure new AI agent 'adminAssistant' with Spanish prompt in config/packages/ai.yaml
- [ ] T006 [P] Create AdminAssistantRepository in src/Infrastructure/Repository/AdminAssistantRepository.php
- [ ] T007 [P] Create AdminAssistantActionRepository in src/Infrastructure/Repository/AdminAssistantActionRepository.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core admin assistant infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T008 Create AdminAssistantController base with #[IsGranted('ROLE_ADMIN')] in src/Infrastructure/Controller/AdminAssistantController.php
- [ ] T009 Create AdminConversationManager service for session management in src/Infrastructure/AI/Service/AdminConversationManager.php
- [ ] T010 Create base admin assistant template with chat interface in templates/admin/assistant/index.html.twig
- [ ] T011 Create admin assistant JavaScript module in public/js/admin-assistant.js
- [ ] T012 Add "Asistente Virtual" navigation link to admin menu in templates/admin/base.html.twig
- [ ] T013 Create AdminAssistantLogger service for audit trail in src/Application/Service/AdminAssistantLogger.php

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Basic Admin Chat Interface (Priority: P1) 🎯 MVP

**Goal**: Admin-only chat interface with Spanish communication, access control, and complete isolation from customer chatbot

**Independent Test**: Login as admin@myshop.com, navigate to /admin/assistant, send "Hola", verify Spanish response. Login as customer, attempt to access /admin/assistant, verify 403 error. Verify customer chatbot still works independently.

### Implementation for User Story 1

- [ ] T014 [P] [US1] Implement GET /admin/assistant route in AdminAssistantController.php (renders chat interface)
- [ ] T015 [P] [US1] Implement POST /api/admin/assistant/chat route with ROLE_ADMIN check in AdminAssistantController.php
- [ ] T016 [US1] Configure Spanish-only system prompt for adminAssistant in config/packages/ai.yaml with professional business tone
- [ ] T017 [US1] Implement conversation persistence in AdminConversationManager.php (save/load admin messages)
- [ ] T018 [US1] Add access control test: verify non-admin gets 403 in AdminAssistantController.php
- [ ] T019 [US1] Implement context isolation: ensure admin and customer agents use separate MessageBag instances
- [ ] T020 [US1] Style admin assistant UI distinct from customer chatbot in templates/admin/assistant/index.html.twig

**Checkpoint**: At this point, User Story 1 should be fully functional - admins can chat in Spanish, customers are blocked, isolation verified

---

## Phase 4: User Story 2 - Product Management via Natural Language (Priority: P1)

**Goal**: Create, update, delete products through natural language conversation with validation and confirmation

**Independent Test**: Ask admin assistant "Crea un producto llamado Test Product", provide details through conversation, verify product appears in database. Update product price, delete product (both with confirmation).

### Implementation for User Story 2

- [X] T021 [P] [US2] Create CreateProduct UseCase in src/Application/UseCase/Admin/CreateProduct.php
- [X] T022 [P] [US2] Create UpdateProduct UseCase in src/Application/UseCase/Admin/UpdateProduct.php  
- [X] T023 [P] [US2] Create DeleteProduct UseCase with order check in src/Application/UseCase/Admin/DeleteProduct.php
- [X] T024 [P] [US2] Create AdminCreateProductTool with validation in src/Infrastructure/AI/Tool/Admin/AdminCreateProductTool.php
- [X] T025 [P] [US2] Create AdminUpdateProductTool with disambiguation in src/Infrastructure/AI/Tool/Admin/AdminUpdateProductTool.php
- [X] T026 [P] [US2] Create AdminDeleteProductTool with confirmation in src/Infrastructure/AI/Tool/Admin/AdminDeleteProductTool.php
- [X] T027 [US2] Add #[AsTool] attributes and Spanish descriptions to all 3 admin product tools
- [X] T028 [US2] Update adminAssistant prompt in config/packages/ai.yaml to list product management tools with usage examples
- [X] T029 [US2] Implement proactive clarification logic: if required fields missing, tool returns prompt for admin to ask
- [X] T030 [US2] Implement product name disambiguation: if multiple matches, return numbered list for admin selection
- [X] T031 [US2] Add audit logging for all product operations in AdminAssistantLogger.php

**Checkpoint**: Admin can create/update/delete products through chat, validation works, confirmation required, audit log populated

---

## Phase 5: User Story 3 - Sales Analytics Queries (Priority: P2)

**Goal**: Query sales performance, product stats, top sellers, and customer statistics through natural language

**Independent Test**: Ask "¿Cómo van las ventas?" and verify total revenue/orders returned. Ask "¿Cuál es el producto más vendido?" and see ranked list. Works independently without needing US2 product management.

### Implementation for User Story 3

- [ ] T032 [P] [US3] Create GetSalesOverview UseCase in src/Application/UseCase/Admin/GetSalesOverview.php
- [ ] T033 [P] [US3] Create GetProductSalesStats UseCase in src/Application/UseCase/Admin/GetProductSalesStats.php
- [ ] T034 [P] [US3] Create GetTopSellingProducts UseCase in src/Application/UseCase/Admin/GetTopSellingProducts.php
- [ ] T035 [P] [US3] Create GetUserPurchaseStats UseCase in src/Application/UseCase/Admin/GetUserPurchaseStats.php
- [ ] T036 [P] [US3] Create AdminGetSalesOverviewTool in src/Infrastructure/AI/Tool/Admin/AdminGetSalesOverviewTool.php
- [ ] T037 [P] [US3] Create AdminGetProductSalesStatsTool in src/Infrastructure/AI/Tool/Admin/AdminGetProductSalesStatsTool.php
- [ ] T038 [P] [US3] Create AdminGetTopSellingProductsTool in src/Infrastructure/AI/Tool/Admin/AdminGetTopSellingProductsTool.php
- [ ] T039 [P] [US3] Create AdminGetUserPurchaseStatsTool in src/Infrastructure/AI/Tool/Admin/AdminGetUserPurchaseStatsTool.php
- [ ] T040 [US3] Add #[AsTool] attributes with Spanish descriptions to all 4 analytics tools
- [ ] T041 [US3] Update adminAssistant prompt in config/packages/ai.yaml to list analytics tools with query examples
- [ ] T042 [US3] Implement graceful empty result handling: tools return user-friendly Spanish messages like "No hay ventas registradas"
- [ ] T043 [US3] Add analytics audit logging in AdminAssistantLogger.php (track what stats admins query)

**Checkpoint**: Admin can query all sales analytics through chat, empty results handled gracefully, audit log tracks queries

---

## Phase 6: User Story 4 - Conversational Context and Multi-Turn Analytics (Priority: P3)

**Goal**: Maintain conversational context for natural follow-up questions and implicit references

**Independent Test**: Ask "¿Cómo va el producto Laptop Gaming?", then ask "¿Cuánto stock queda?" without repeating product name. Verify assistant correctly references Laptop Gaming from context.

### Implementation for User Story 4

- [ ] T044 [US4] Enhance AdminConversationManager to track entity references (products, users) in conversation state
- [ ] T045 [US4] Implement context resolution: when tool receives implicit reference ("el producto", "este producto"), resolve from conversation history
- [ ] T046 [US4] Update adminAssistant prompt in config/packages/ai.yaml with instructions for using conversation context
- [ ] T047 [US4] Implement topic switching detection: clear context when conversation shifts to unrelated topic
- [ ] T048 [US4] Add context state to AdminAssistantConversation entity: store current_product, current_user, current_period JSON fields
- [ ] T049 [US4] Update conversation persistence to save/restore context state in AdminConversationManager.php
- [ ] T050 [US4] Enhance all admin tools to accept optional context parameters (e.g., product_name_from_context)

**Checkpoint**: All user stories complete - admin assistant handles multi-turn conversations with correct context resolution

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Improvements affecting multiple user stories, security hardening, documentation

- [ ] T051 [P] Add comprehensive Spanish error messages for all edge cases in all admin tools
- [ ] T052 [P] Implement rate limiting for admin assistant endpoint in AdminAssistantController.php (prevent abuse)
- [ ] T053 [P] Add admin assistant access logging (who accessed, when, duration) in AdminAssistantLogger.php
- [ ] T054 Review and test all confirmation flows: ensure destructive actions require explicit "sí"/"confirmar" from admin
- [ ] T055 [P] Add admin assistant usage statistics to admin dashboard in templates/admin/index.html.twig
- [ ] T056 [P] Create IMPLEMENTATION.md documenting architecture, tools, and testing scenarios in specs/007-admin-virtual-assistant/
- [ ] T057 Test concurrent admin sessions: ensure conversations remain isolated per session
- [ ] T058 Security audit: verify all endpoints have #[IsGranted('ROLE_ADMIN')], all tools verify role, no customer data leakage
- [ ] T059 [P] Add sample admin assistant queries to admin dashboard as quick-start guide
- [ ] T060 Performance test: ensure analytics queries complete in <500ms for typical dataset sizes

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup (Phase 1) completion - BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (Phase 2) completion
- **User Story 2 (Phase 4)**: Depends on Foundational (Phase 2) completion and US1 for agent config
- **User Story 3 (Phase 5)**: Depends on Foundational (Phase 2) completion - independent of US1/US2
- **User Story 4 (Phase 6)**: Depends on US1 (Phase 3) for conversation manager, integrates with US2/US3
- **Polish (Phase 7)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) and US1 (needs agent configured) - Creates product tools
- **User Story 3 (P2)**: Can start after Foundational (Phase 2) - Completely independent, uses existing Order/Product entities
- **User Story 4 (P3)**: Requires US1 complete (uses conversation manager) - Enhances US2/US3 with context

### Within Each User Story

**User Story 1**:
- T014, T015, T016 can run in parallel (different aspects)
- T017 requires T009 (conversation manager exists)
- T018, T019, T020 can run after core chat working

**User Story 2**:
- T021, T022, T023 can run in parallel (different use cases)
- T024, T025, T026 can run in parallel after use cases exist
- T027, T028 require tools created
- T029, T030, T031 are enhancements

**User Story 3**:
- All use cases (T032-T035) can run in parallel
- All tools (T036-T039) can run in parallel after use cases
- T040, T041, T042, T043 are configuration/enhancement

**User Story 4**:
- Must happen after US1 complete
- Tasks are sequential enhancements to existing components

### Parallel Opportunities

**Setup Phase (1)**:
- T002, T003 can run in parallel with T001
- T006, T007 can run in parallel after entities exist

**Foundational Phase (2)**:
- All tasks can run in parallel by different developers once Phase 1 complete

**User Story 2**:
```bash
# Parallel batch 1: Use cases
T021: CreateProduct UseCase
T022: UpdateProduct UseCase
T023: DeleteProduct UseCase

# Parallel batch 2: Tools (after use cases)
T024: AdminCreateProductTool
T025: AdminUpdateProductTool
T026: AdminDeleteProductTool
```

**User Story 3**:
```bash
# Parallel batch 1: Use cases
T032: GetSalesOverview UseCase
T033: GetProductSalesStats UseCase
T034: GetTopSellingProducts UseCase
T035: GetUserPurchaseStats UseCase

# Parallel batch 2: Tools (after use cases)
T036: AdminGetSalesOverviewTool
T037: AdminGetProductSalesStatsTool
T038: AdminGetTopSellingProductsTool
T039: AdminGetUserPurchaseStatsTool
```

**Polish Phase (7)**:
- T051, T052, T053, T055, T056, T059 can all run in parallel

---

## Implementation Strategy

### MVP First (User Stories 1 + 2 Only - Both P1)

1. Complete Phase 1: Setup (entities, migration, agent config)
2. Complete Phase 2: Foundational (controller, templates, conversation manager) - **CRITICAL BLOCKER**
3. Complete Phase 3: User Story 1 (basic chat with access control)
4. Complete Phase 4: User Story 2 (product management tools)
5. **STOP and VALIDATE**: Test admin can create/update/delete products via chat, non-admins blocked
6. Deploy/demo MVP with core product management functionality

### Incremental Delivery

1. **Foundation**: Setup + Foundational → Admin chat infrastructure ready
2. **MVP**: Add US1 + US2 → Admin can manage products via chat (P1 features complete)
3. **Analytics**: Add US3 → Admin can query sales stats (P2 feature)
4. **Enhanced UX**: Add US4 → Conversational context improves experience (P3 feature)
5. **Production Ready**: Add Phase 7 → Security hardened, documented, monitored

Each increment is independently testable and deployable.

### Parallel Team Strategy

With 3+ developers after Foundational phase complete:

- **Developer A**: User Story 1 (chat interface + access control)
- **Developer B**: User Story 2 (product management tools)
- **Developer C**: User Story 3 (analytics tools)

US1 and US3 are completely independent. US2 depends on US1 for agent config but minimal coupling.

---

## Testing Scenarios

### User Story 1 Testing
```bash
# Access Control
1. Login as admin@myshop.com / admin123
2. Navigate to /admin/assistant
3. Verify page loads with chat interface

# Non-Admin Block
1. Login as juan@example.com / customer123
2. Navigate to /admin/assistant
3. Verify 403 Forbidden response

# Spanish Communication
1. As admin, send: "Hola, ¿qué puedes hacer?"
2. Verify response in Spanish with tool list

# Isolation
1. Open customer chatbot in one browser tab
2. Open admin assistant in another tab (as admin)
3. Send messages in both
4. Verify conversations remain separate
```

### User Story 2 Testing
```bash
# Create Product
1. As admin, send: "Crea un producto llamado Test Laptop"
2. Assistant asks for price: respond "500"
3. Assistant asks for description: respond "Laptop de prueba"
4. Assistant asks for stock: respond "10"
5. Assistant asks for category: respond "Electronics"
6. Confirm creation: respond "sí"
7. Verify product in database/product list

# Update Product
1. Send: "Actualiza el precio de Test Laptop a 450"
2. Assistant shows current price, asks confirmation
3. Respond: "confirmar"
4. Verify price updated in database

# Delete Product
1. Send: "Elimina el producto Test Laptop"
2. Assistant asks: "¿Estás seguro?"
3. Respond: "sí"
4. Verify product deleted

# Validation
1. Send: "Crea un producto con precio -100"
2. Verify assistant rejects: "El precio debe ser positivo"

# Ambiguous Name
1. Create "Laptop HP", "Laptop Dell", "Laptop Asus"
2. Send: "Actualiza el precio de laptop"
3. Verify assistant lists 3 options for selection
```

### User Story 3 Testing
```bash
# Sales Overview
1. Send: "¿Cómo van las ventas?"
2. Verify response includes: total revenue, order count, average order value

# Product Stats
1. Send: "¿Cuánto ha vendido Laptop HP?"
2. Verify response includes: units sold, revenue, percentage of total

# Top Sellers
1. Send: "¿Cuál es el producto más vendido?"
2. Verify ranked list with units and revenue

# Customer Stats
1. Send: "¿Quiénes son los mejores clientes?"
2. Verify list with order count and total spend

# Empty Results
1. Send: "¿Cómo van las ventas de ProductoInexistente?"
2. Verify graceful message: "No hay ventas registradas para ese producto"
```

### User Story 4 Testing
```bash
# Context Maintenance
1. Send: "¿Cómo va el producto Laptop HP?"
2. Assistant shows stats for Laptop HP
3. Send: "¿Cuánto stock queda?"
4. Verify assistant correctly references Laptop HP from context

# Topic Switching
1. Ask about Laptop HP sales
2. Ask about customer statistics
3. Return to product question
4. Verify context retrieved appropriately

# Implicit References
1. Send: "Muéstrame las ventas de Laptop HP"
2. Send: "Compáralo con Laptop Dell"
3. Verify assistant resolves "lo" to Laptop HP correctly
```

---

## Notes

- **Access Control**: Every admin tool and endpoint MUST verify ROLE_ADMIN - security critical
- **Isolation**: Admin and customer agents use different agent instances, separate conversation storage, no shared context
- **Spanish Only**: All admin assistant responses, tool descriptions, error messages in Spanish
- **Confirmation**: Product delete, price changes, stock adjustments require explicit admin confirmation
- **Audit Trail**: AdminAssistantAction entity logs all operations with admin user, timestamp, action type, affected entities
- **Performance**: Analytics queries should complete in <500ms for datasets up to 10k orders
- **No Tests**: User did not request tests - focus on implementation only
- **Commit Frequency**: Commit after completing each phase or logical group of tasks
- **MVP Scope**: US1 + US2 deliver core product management value - sufficient for initial release

