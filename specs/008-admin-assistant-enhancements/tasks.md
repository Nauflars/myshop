# Tasks: Admin AI Assistant Enhancements

**Feature Branch**: `008-admin-assistant-enhancements`  
**Input**: Design documents from `/specs/008-admin-assistant-enhancements/`  
**Prerequisites**: spec.md (user stories and functional requirements), spec-007 (existing admin assistant infrastructure)

**Tech Stack**: Symfony PHP 8.3, Symfony AI Agent framework, Doctrine ORM, Twig templates, JavaScript (Vanilla), CSS3  
**Architecture**: Domain-Driven Design (Domain/Application/Infrastructure layers)

**Dependencies**: Builds on spec-007 infrastructure (AdminAssistantConversation, AdminAssistantMessage, AdminAssistantAction, AdminAssistantController)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4, US5, US6)
- Include exact file paths in descriptions

---

## Phase 1: Floating UI Infrastructure (User Story 1 - Priority P1)

**Purpose**: Create floating action button (FAB) and panel UI matching customer chatbot pattern but distinct for admin use

**Goal**: Accessible chat interface across all admin pages without losing context

**Independent Test**: Login as admin, verify floating ball appears on any admin page, click to open panel, send message, close panel, navigate to different admin page, reopen and verify history persisted. Login as customer, verify NO admin floating button appears.

### Implementation for User Story 1

- [X] T001 [P] [US1] Create admin-floating-assistant.js module with FAB component in public/js/admin-floating-assistant.js
- [X] T002 [P] [US1] Create admin-floating-assistant.css for floating button and panel styles in public/css/admin-floating-assistant.css
- [X] T003 [US1] Add floating assistant HTML structure to admin base template in templates/admin/base.html.twig
- [ ] T004 [US1] Implement FAB open/close toggle with animation in admin-floating-assistant.js
- [ ] T005 [US1] Implement panel state persistence in sessionStorage (open/closed status)
- [ ] T006 [US1] Connect floating panel to existing AdminAssistantController POST /admin/assistant/chat endpoint
- [ ] T007 [US1] Style panel to be visually distinct from customer chatbot (different color scheme, admin badge)
- [ ] T008 [US1] Add role-based rendering: only show FAB if user has ROLE_ADMIN in base.html.twig
- [ ] T009 [US1] Test navigation: verify panel closes on page change but conversation persists on reopen
- [ ] T010 [US1] Add accessibility: keyboard navigation (Esc to close, Tab through elements)

**Checkpoint**: Floating assistant UI complete - admin can access chat from any admin page, state persists, non-admins cannot see it

---

## Phase 2: Inventory Management Tools (User Story 2 - Priority P1)

**Purpose**: Enable stock management via natural language (check stock, update quantities, identify low-stock products)

**Independent Test**: Open assistant, ask "¿Qué productos tienen stock bajo?", receive list. Say "Aumenta el stock de [producto] en 50", confirm change, verify database updated.

### Implementation for User Story 2

- [ ] T011 [P] [US2] Create GetLowStockProducts UseCase in src/Application/UseCase/Admin/GetLowStockProducts.php
- [ ] T012 [P] [US2] Create UpdateProductStock UseCase with validation in src/Application/UseCase/Admin/UpdateProductStock.php
- [ ] T013 [P] [US2] Create GetProductStock UseCase in src/Application/UseCase/Admin/GetProductStock.php
- [ ] T014 [P] [US2] Create AdminGetLowStockProductsTool with #[AsTool] in src/Infrastructure/AI/Tool/Admin/AdminGetLowStockProductsTool.php
- [ ] T015 [P] [US2] Create AdminUpdateProductStockTool with confirmation flow in src/Infrastructure/AI/Tool/Admin/AdminUpdateProductStockTool.php
- [ ] T016 [P] [US2] Create AdminGetProductStockTool in src/Infrastructure/AI/Tool/Admin/AdminGetProductStockTool.php
- [ ] T017 [US2] Update config/packages/ai.yaml adminAssistant prompt with inventory tool descriptions and examples
- [ ] T018 [US2] Add stock_threshold configuration parameter (default: 10) to config/services.yaml
- [ ] T019 [US2] Implement stock delta logic (increase/decrease vs absolute value) in UpdateProductStock UseCase
- [ ] T020 [US2] Add audit logging: logStockUpdate() in AdminAssistantLogger.php
- [ ] T021 [US2] Test: verify stock cannot go negative, reasonable limits enforced

**Checkpoint**: Inventory management functional - admin can check and update stock via conversation with validation and audit trail

---

## Phase 3: Pricing Tools (part of US2 expanded)

**Purpose**: Manage product pricing conversationally with price history tracking

**Independent Test**: Ask "¿Cuál es el precio de [producto]?", then "Cambia el precio de [producto] a 99.99", confirm, then "Muestra el historial de precios de [producto]"

### Implementation for Pricing

- [ ] T022 [P] Create UpdateProductPrice UseCase in src/Application/UseCase/Admin/UpdateProductPrice.php
- [ ] T023 [P] Create GetPriceHistory UseCase in src/Application/UseCase/Admin/GetPriceHistory.php
- [ ] T024 [P] Create ProductPriceHistory entity in src/Domain/Entity/ProductPriceHistory.php
- [ ] T025 Create migration for product_price_history table in migrations/
- [ ] T026 [P] Create AdminUpdateProductPriceTool with confirmation in src/Infrastructure/AI/Tool/Admin/AdminUpdateProductPriceTool.php
- [ ] T027 [P] Create AdminGetPriceHistoryTool in src/Infrastructure/AI/Tool/Admin/AdminGetPriceHistoryTool.php
- [ ] T028 Update UpdateProductPrice to log price changes to product_price_history table
- [ ] T029 Add audit logging: logPriceUpdate() in AdminAssistantLogger.php
- [ ] T030 Update ai.yaml prompt with pricing tool documentation

**Checkpoint**: Pricing management complete - admin can view and update prices with historical tracking

---

## Phase 4: Sales Analytics Tools (User Story 3 - Priority P2)

**Purpose**: Query sales performance, product rankings, and revenue insights through conversation

**Independent Test**: Ask "¿Cómo van las ventas hoy?", receive revenue/orders/average. Ask "¿Cuáles son los productos más vendidos?", get ranked list. Ask "¿Cuál es el rendimiento de [producto]?", see detailed stats.

### Implementation for User Story 3

- [ ] T031 [P] [US3] Create GetSalesSummary UseCase in src/Application/UseCase/Admin/GetSalesSummary.php
- [ ] T032 [P] [US3] Create GetProductPerformance UseCase in src/Application/UseCase/Admin/GetProductPerformance.php
- [ ] T033 [P] [US3] Create GetTopProducts UseCase in src/Application/UseCase/Admin/GetTopProducts.php
- [ ] T034 [P] [US3] Create GetBottomProducts UseCase in src/Application/UseCase/Admin/GetBottomProducts.php
- [ ] T035 [P] [US3] Create AdminGetSalesSummaryTool with #[AsTool] in src/Infrastructure/AI/Tool/Admin/AdminGetSalesSummaryTool.php
- [ ] T036 [P] [US3] Create AdminGetProductPerformanceTool in src/Infrastructure/AI/Tool/Admin/AdminGetProductPerformanceTool.php
- [ ] T037 [P] [US3] Create AdminGetTopProductsTool in src/Infrastructure/AI/Tool/Admin/AdminGetTopProductsTool.php
- [ ] T038 [P] [US3] Create AdminGetBottomProductsTool in src/Infrastructure/AI/Tool/Admin/AdminGetBottomProductsTool.php
- [ ] T039 [US3] Add time period parsing (today, esta semana, este mes) in GetSalesSummary UseCase
- [ ] T040 [US3] Handle empty dataset gracefully: "No hay ventas registradas en este período" in all analytics tools
- [ ] T041 [US3] Update ai.yaml prompt with analytics tool descriptions and query examples
- [ ] T042 [US3] Add audit logging: logAnalyticsQuery() in AdminAssistantLogger.php

**Checkpoint**: Sales analytics operational - admin can query revenue, top/bottom products, performance metrics

---

## Phase 5: Order Management Tools (User Story 4 - Priority P2)

**Purpose**: Review orders, view details, and update order status conversationally

**Independent Test**: Ask "¿Cuáles son los últimos 10 pedidos?", see list. Say "Muestra detalles del pedido ORD-XXX", get full info. Say "Marca el pedido ORD-XXX como enviado", confirm, verify status updated.

### Implementation for User Story 4

- [ ] T043 [P] [US4] Create ListRecentOrders UseCase in src/Application/UseCase/Admin/ListRecentOrders.php
- [ ] T044 [P] [US4] Create GetOrderDetails UseCase in src/Application/UseCase/Admin/GetOrderDetails.php
- [ ] T045 [P] [US4] Create UpdateOrderStatus UseCase with validation in src/Application/UseCase/Admin/UpdateOrderStatus.php
- [ ] T046 [P] [US4] Create AdminListRecentOrdersTool with #[AsTool] in src/Infrastructure/AI/Tool/Admin/AdminListRecentOrdersTool.php
- [ ] T047 [P] [US4] Create AdminGetOrderDetailsTool in src/Infrastructure/AI/Tool/Admin/AdminGetOrderDetailsTool.php
- [ ] T048 [P] [US4] Create AdminUpdateOrderStatusTool with confirmation in src/Infrastructure/AI/Tool/Admin/AdminUpdateOrderStatusTool.php
- [ ] T049 [US4] Implement status transition validation (cannot ship cancelled order) in UpdateOrderStatus UseCase
- [ ] T050 [US4] Add GetOrderDetails privacy filter: exclude sensitive payment data (card numbers, CVV)
- [ ] T051 [US4] Update ai.yaml prompt with order management tool descriptions
- [ ] T052 [US4] Add audit logging: logOrderStatusUpdate() in AdminAssistantLogger.php

**Checkpoint**: Order management functional - admin can list, review, and update orders with validation

---

## Phase 6: Customer Insights Tools (User Story 5 - Priority P3)

**Purpose**: Query customer metrics, identify top customers, analyze purchase patterns

**Independent Test**: Ask "¿Cuántos clientes tenemos?", get total with breakdown. Ask "¿Quiénes son nuestros mejores clientes?", see ranked list by lifetime value.

### Implementation for User Story 5

- [ ] T053 [P] [US5] Create GetCustomerOverview UseCase in src/Application/UseCase/Admin/GetCustomerOverview.php
- [ ] T054 [P] [US5] Create GetTopCustomers UseCase in src/Application/UseCase/Admin/GetTopCustomers.php
- [ ] T055 [P] [US5] Create GetRepeatCustomerRate UseCase in src/Application/UseCase/Admin/GetRepeatCustomerRate.php
- [ ] T056 [P] [US5] Create AdminGetCustomerOverviewTool with #[AsTool] in src/Infrastructure/AI/Tool/Admin/AdminGetCustomerOverviewTool.php
- [ ] T057 [P] [US5] Create AdminGetTopCustomersTool in src/Infrastructure/AI/Tool/Admin/AdminGetTopCustomersTool.php
- [ ] T058 [US5] Implement privacy filters: exclude passwords, full payment details in all customer tools
- [ ] T059 [US5] Update ai.yaml prompt with customer insights tool descriptions
- [ ] T060 [US5] Add audit logging: logCustomerQuery() in AdminAssistantLogger.php

**Checkpoint**: Customer insights available - admin can analyze customer base and identify high-value customers

---

## Phase 7: Unanswered Questions Integration (User Story 6 - Priority P3)

**Purpose**: Review customer assistant knowledge gaps and unanswered questions

**Independent Test**: Ask "¿Hay preguntas sin respuesta?", get list from spec-006 unanswered_questions table. Ask "Muestra preguntas de esta semana", get filtered results.

### Implementation for User Story 6

- [ ] T061 [P] [US6] Create ListUnansweredQuestions UseCase in src/Application/UseCase/Admin/ListUnansweredQuestions.php
- [ ] T062 [P] [US6] Create AdminListUnansweredQuestionsTool with #[AsTool] in src/Infrastructure/AI/Tool/Admin/AdminListUnansweredQuestionsTool.php
- [ ] T063 [US6] Add date range filtering (esta semana, este mes, hoy) in ListUnansweredQuestions UseCase
- [ ] T064 [US6] Add status filtering (pending, resolved, ignored) in ListUnansweredQuestions UseCase
- [ ] T065 [US6] Update ai.yaml prompt with unanswered questions tool description
- [ ] T066 [US6] Add audit logging: logUnansweredQuestionsQuery() in AdminAssistantLogger.php

**Checkpoint**: Unanswered questions review functional - admin can track customer knowledge gaps

---

## Phase 8: Polish and Production Readiness

**Purpose**: Security hardening, performance optimization, comprehensive testing, documentation

### Cross-Cutting Enhancements

- [ ] T067 Add rate limiting to floating assistant chat endpoint (prevent abuse)
- [ ] T068 Implement input sanitization for all AI tool parameters (prevent injection)
- [ ] T069 Add graceful OpenAI API failure handling with user-friendly errors
- [ ] T070 Optimize analytics queries with database indexes on orders.created_at, order_items.product_id
- [ ] T071 Add comprehensive error messages in Spanish for all edge cases
- [ ] T072 Test concurrent admin sessions: verify conversation isolation
- [ ] T073 Performance test: ensure analytics queries < 500ms for datasets up to 10K orders
- [ ] T074 Security test: verify non-admin cannot access ANY admin assistant endpoint
- [ ] T075 Create IMPLEMENTATION.md documentation with architecture diagram and usage examples
- [ ] T076 Add unit tests for all new use cases (minimum 80% coverage)

**Final Checkpoint**: Production-ready - all user stories functional, security validated, performance optimized, documentation complete

---

## Dependencies and Execution Flow

### Sequential Dependencies

1. **Phase 1 (Floating UI) MUST complete** before any meaningful user testing - it's the entry point
2. **Phase 2 (Inventory)** and **Phase 3 (Pricing)** build on each other (share Product entity)
3. **Phase 4 (Analytics)** and **Phase 5 (Orders)** are independent but both use Order entity
4. **Phase 6 (Customers)** and **Phase 7 (Questions)** are fully independent
5. **Phase 8 (Polish)** must be last

### Parallel Opportunities

**Within Phase 1**: T001 (JS), T002 (CSS) can run in parallel  
**Within Phase 2**: T011, T012, T013 (UseCases) and T014, T015, T016 (Tools) can run in parallel groups  
**Phase 4 and Phase 5** can run in parallel (different domains)  
**Phase 6 and Phase 7** can run in parallel (independent features)

### Testing Strategy

**After Phase 1**: Verify floating UI appears only for admins  
**After Phase 2**: Test inventory operations end-to-end  
**After Phase 3**: Test pricing with historical tracking  
**After Phase 4**: Test analytics with various date ranges  
**After Phase 5**: Test order status transitions  
**After Phase 6**: Test customer insights privacy filters  
**After Phase 7**: Test unanswered questions filtering  
**After Phase 8**: Full regression testing

---

## Definition of Done (per Phase)

- [ ] All tasks in phase marked complete
- [ ] Code committed and pushed
- [ ] Cache cleared and containers restarted
- [ ] Manual testing performed per "Independent Test" criteria
- [ ] No errors in logs
- [ ] Checkpoint criteria met

---

## Total Tasks: 76

- Phase 1 (Floating UI): 10 tasks
- Phase 2 (Inventory): 11 tasks
- Phase 3 (Pricing): 9 tasks
- Phase 4 (Analytics): 12 tasks
- Phase 5 (Orders): 10 tasks
- Phase 6 (Customers): 8 tasks
- Phase 7 (Questions): 6 tasks
- Phase 8 (Polish): 10 tasks

**MVP Scope**: Phase 1 + Phase 2 (21 tasks) = Core floating UI with inventory management  
**Full P1**: Phase 1 + Phase 2 + Phase 3 (30 tasks) = Floating UI + complete inventory + pricing  
**Full P2**: Add Phase 4 + Phase 5 (52 tasks) = + analytics + order management  
**Full P3**: Add Phase 6 + Phase 7 (66 tasks) = + customer insights + questions  
**Production**: All 76 tasks
