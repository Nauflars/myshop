# Tasks: Chat Assistant Improvements

**Feature**: 003-chat-improvements  
**Tech Stack**: Symfony 7, PHP 8.3, Doctrine ORM, MySQL 8.0, Symfony AI Bundle, JavaScript  
**Dependencies**: spec-002 (AI Shopping Assistant must be implemented)

---

## Implementation Strategy

Este feature mejora el asistente de chat existente con:
1. **Seguridad**: Eliminar IDs explícitos, usar Security context
2. **Persistencia**: Guardar conversaciones en DB automáticamente
3. **Control**: Usuario puede limpiar historial
4. **Admin**: Estadísticas y funcionalidades específicas

**MVP First**: US1, US2, US3 (core security + persistence)  
**Incremental Delivery**: Cada user story es independiente y desplegable

---

## Phase 1: Setup & Verification

**Goal**: Verificar dependencias y estado del proyecto

**Independent Test**: Proyecto compila, spec-002 está implementado, Security bundle configurado

- [ ] T001 Verify spec-002 implementation status (check if AI tools exist)
- [ ] T002 Verify Security bundle configuration in config/packages/security.yaml
- [ ] T003 Check if admin user exists in fixtures or create in src/DataFixtures/AppFixtures.php
- [ ] T004 Verify database connection and migrations are up to date

**Checkpoint**: Environment ready for implementation

---

## Phase 2: Foundational - Database Schema

**Goal**: Crear entidades y tablas para persistencia de conversaciones

**Independent Test**: Migraciones ejecutan sin error, tablas existen en DB, entidades validadas

### Domain Entities

- [ ] T005 [P] Create Conversation entity with id, user, title, createdAt, updatedAt in src/Domain/Entity/Conversation.php
- [ ] T006 [P] Create ConversationMessage entity with id, conversation, role, content, toolCalls, timestamp in src/Domain/Entity/ConversationMessage.php
- [ ] T007 [P] Add relationship methods to Conversation (addMessage, getLastMessage, getMessageCount) in src/Domain/Entity/Conversation.php
- [ ] T008 [P] Implement Conversation->generateTitle() method (from first user message) in src/Domain/Entity/Conversation.php

### Repository Interfaces

- [ ] T009 [P] Create ConversationRepositoryInterface with save, findById, findByUser, findActiveForUser in src/Domain/Repository/ConversationRepositoryInterface.php
- [ ] T010 [P] Create DoctrineConversationRepository implementing interface in src/Infrastructure/Repository/DoctrineConversationRepository.php

### Database Migration

- [ ] T011 Create Doctrine migration for conversations table in migrations/
- [ ] T012 Create Doctrine migration for conversation_messages table in migrations/
- [ ] T013 Execute migrations in Docker container (docker-compose exec php bin/console doctrine:migrations:migrate)

### Unit Tests

- [ ] T014 [P] Create unit test for Conversation entity (addMessage, generateTitle) in tests/Domain/Entity/ConversationTest.php
- [ ] T015 [P] Create unit test for ConversationMessage entity in tests/Domain/Entity/ConversationMessageTest.php

**Checkpoint**: Database schema ready, entities working

---

## Phase 3: US1 - Add to Cart Without User ID

**Goal**: Usuario autenticado añade productos sin proporcionar userId explícito

**Independent Test**: Usuario autenticado dice "añade iPhone al carrito", producto se añade sin pedir userId

### Story Details
- Priority: P1
- Test Criteria: AddToCartTool NO tiene parámetro userId, usa Security::getUser()

### Implementation Tasks

- [ ] T016 [US1] Verify AddToCartTool already uses Security (implemented in spec-002 Phase 15) in src/Infrastructure/AI/Tool/AddToCartTool.php
- [ ] T017 [US1] If not, update AddToCartTool to inject Security service in src/Infrastructure/AI/Tool/AddToCartTool.php
- [ ] T018 [US1] If not, remove userId parameter from AddToCartTool->__invoke() in src/Infrastructure/AI/Tool/AddToCartTool.php
- [ ] T019 [US1] If not, add validation: if user not authenticated, return Spanish error message in AddToCartTool
- [ ] T020 [US1] Verify AddToCartByName use case uses User entity (not userId string) in src/Application/UseCase/AI/AddToCartByName.php
- [ ] T021 [US1] Test manually: "añade producto X al carrito" sin proporcionar userId

**Checkpoint**: AddToCart no require userId explícito

---

## Phase 4: US2 - View Cart and Total Price

**Goal**: Usuario pregunta "¿qué hay en mi carrito?" y ve lista con precio total

**Independent Test**: Usuario autenticado pregunta por carrito, recibe lista detallada con total formateado

### Story Details
- Priority: P1
- Test Criteria: GetCartSummaryTool devuelve productos, cantidades, precios, y total

### Implementation Tasks

- [ ] T022 [US2] Verify GetCartSummaryTool exists (implemented in spec-002 Phase 15) in src/Infrastructure/AI/Tool/GetCartSummaryTool.php
- [ ] T023 [US2] Verify GetCartSummary use case returns totalItems, totalAmount, currency in src/Application/UseCase/AI/GetCartSummary.php
- [ ] T024 [US2] Verify tool response includes line items (product name, quantity, price each) in GetCartSummaryTool
- [ ] T025 [US2] Verify empty cart returns Spanish message "Tu carrito está vacío" in GetCartSummaryTool
- [ ] T026 [US2] Test manually: "muéstrame mi carrito" devuelve datos correctos

**Checkpoint**: Usuario puede ver carrito completo con total

---

## Phase 5: US3 - Conversation Persistence

**Goal**: Conversaciones persisten entre sesiones, usuario puede continuar donde lo dejó

**Independent Test**: Usuario envía mensaje → recarga página → ve historial anterior

### Story Details
- Priority: P1
- Test Criteria: Mensajes guardados en DB, frontend carga historial automáticamente

### Use Cases

- [ ] T027 [P] [US3] Create SaveConversation use case in src/Application/UseCase/AI/Conversation/SaveConversation.php
- [ ] T028 [P] [US3] Create LoadConversation use case in src/Application/UseCase/AI/Conversation/LoadConversation.php
- [ ] T029 [P] [US3] Create ListUserConversations use case in src/Application/UseCase/AI/Conversation/ListUserConversations.php

### Infrastructure Service

- [ ] T030 [US3] Create ConversationManager service for managing active conversation in src/Infrastructure/AI/Service/ConversationManager.php
- [ ] T031 [US3] Implement ConversationManager->getOrCreateConversation(User, ?conversationId) in ConversationManager
- [ ] T032 [US3] Implement ConversationManager->saveMessage(Conversation, role, content, ?toolCalls) in ConversationManager
- [ ] T033 [US3] Implement ConversationManager->loadMessages(Conversation, limit = 50) in ConversationManager

### Controller Updates

- [ ] T034 [US3] Update ChatbotController to accept conversationId parameter in src/Infrastructure/Controller/ChatbotController.php
- [ ] T035 [US3] Inject ConversationManager into ChatbotController in src/Infrastructure/Controller/ChatbotController.php
- [ ] T036 [US3] After agent response, save user message and assistant message to DB in ChatbotController
- [ ] T037 [US3] Return conversationId in JSON response for frontend to store in ChatbotController
- [ ] T038 [US3] Add endpoint GET /api/chat/history/{conversationId} to load previous messages in ChatbotController

### Frontend Updates

- [ ] T039 [US3] Update chatbot.js to store conversationId in localStorage in public/js/chatbot.js
- [ ] T040 [US3] On page load, check localStorage for conversationId in public/js/chatbot.js
- [ ] T041 [US3] If conversationId exists, fetch /api/chat/history/{conversationId} and display messages in public/js/chatbot.js
- [ ] T042 [US3] Send conversationId in each POST to /api/chat in public/js/chatbot.js
- [ ] T043 [US3] Display loading indicator while fetching history in public/js/chatbot.js

### Integration Tests

- [ ] T044 [P] [US3] Create test: save message → load conversation → verify message exists in tests/Integration/ConversationPersistenceTest.php
- [ ] T045 [P] [US3] Create test: user A cannot access user B's conversations in tests/Integration/ConversationSecurityTest.php
- [ ] T046 [P] [US3] Create test: ChatbotController saves messages automatically after agent response in tests/Integration/ChatbotControllerTest.php

**Checkpoint**: Conversaciones persisten, usuario ve historial al recargar

---

## Phase 6: US4 - Clear Chat History

**Goal**: Usuario puede limpiar su conversación con botón visible

**Independent Test**: Usuario hace clic en "Limpiar chat" → historial desaparece → nueva conversación comienza

### Story Details
- Priority: P1
- Test Criteria: Botón visible, conversación se archiva/elimina, nueva conversación se crea

### Use Case

- [ ] T047 [P] [US4] Create ClearConversation use case in src/Application/UseCase/AI/Conversation/ClearConversation.php
- [ ] T048 [US4] Implement logic to archive current conversation (set status or delete) in ClearConversation

### AI Tool

- [ ] T049 [P] [US4] Create ClearConversationTool with Spanish description in src/Infrastructure/AI/Tool/ClearConversationTool.php
- [ ] T050 [US4] Implement ClearConversationTool->__invoke() (no parameters, uses Security) in ClearConversationTool
- [ ] T051 [US4] Tool calls ClearConversation use case and returns Spanish confirmation message in ClearConversationTool

### Frontend Updates

- [ ] T052 [US4] Add "🗑️ Limpiar chat" button to chatbot widget template in templates/chatbot/widget.html.twig
- [ ] T053 [US4] Add click handler in chatbot.js to call clear endpoint or send clear message in public/js/chatbot.js
- [ ] T054 [US4] On successful clear, remove conversationId from localStorage in public/js/chatbot.js
- [ ] T055 [US4] Clear all visible messages from chat interface in public/js/chatbot.js
- [ ] T056 [US4] Display confirmation message from assistant in public/js/chatbot.js

### Integration Tests

- [ ] T057 [P] [US4] Create test: click clear button → conversationId changes → old messages not visible in tests/E2E/ClearChatTest.php
- [ ] T058 [P] [US4] Create test: user says "limpia el chat" → tool is called → conversation cleared in tests/Integration/ClearConversationToolTest.php

**Checkpoint**: Usuario puede limpiar chat y empezar de nuevo

---

## Phase 7: US5 - Admin Statistics

**Goal**: Administrador puede preguntar por estadísticas del negocio

**Independent Test**: Admin pregunta "¿cuáles son las estadísticas?", recibe total ventas, productos top, usuarios activos

### Story Details
- Priority: P2
- Test Criteria: Solo ROLE_ADMIN puede acceder, datos en español, cacheo de 5 minutos

### Use Case

- [ ] T059 [P] [US5] Create GetAdminStats use case in src/Application/UseCase/AI/GetAdminStats.php
- [ ] T060 [US5] Implement query for total sales this month in GetAdminStats
- [ ] T061 [US5] Implement query for top 5 products by sales in GetAdminStats
- [ ] T062 [US5] Implement query for active users count (logged in last 30 days) in GetAdminStats
- [ ] T063 [US5] Implement query for pending orders count in GetAdminStats
- [ ] T064 [US5] Format response as array with Spanish keys in GetAdminStats

### AI Tool

- [ ] T065 [P] [US5] Create GetAdminStatsTool with Spanish description in src/Infrastructure/AI/Tool/GetAdminStatsTool.php
- [ ] T066 [US5] Inject Security service into GetAdminStatsTool in GetAdminStatsTool
- [ ] T067 [US5] Validate $security->isGranted('ROLE_ADMIN') before executing in GetAdminStatsTool
- [ ] T068 [US5] If not admin, return Spanish error: "No tienes permisos para ver esta información" in GetAdminStatsTool
- [ ] T069 [US5] Call GetAdminStats use case and format response for AI agent in GetAdminStatsTool

### Caching

- [ ] T070 [US5] Add Symfony Cache component to GetAdminStats use case in GetAdminStats
- [ ] T071 [US5] Cache statistics for 5 minutes (300 seconds) in GetAdminStats

### Integration Tests

- [ ] T072 [P] [US5] Create test: admin user asks for stats → receives data in tests/Integration/GetAdminStatsToolTest.php
- [ ] T073 [P] [US5] Create test: regular user asks for stats → receives permission denied in tests/Integration/GetAdminStatsToolTest.php
- [ ] T074 [P] [US5] Create test: verify cache is used (query count = 0 on second call) in tests/Integration/AdminStatsCacheTest.php

**Checkpoint**: Admin tiene acceso a estadísticas vía chatbot

---

## Phase 8: US6 - User Information

**Goal**: Usuario puede preguntar "¿quién soy?" y ver su información

**Independent Test**: Usuario pregunta, recibe nombre, email, rol, número de conversaciones

### Story Details
- Priority: P2
- Test Criteria: No se expone ID interno, respuesta en español

### AI Tool

- [ ] T075 [P] [US6] Create GetUserInfoTool with Spanish description in src/Infrastructure/AI/Tool/GetUserInfoTool.php
- [ ] T076 [US6] Inject Security and ConversationRepository into GetUserInfoTool in GetUserInfoTool
- [ ] T077 [US6] Get current user from Security::getUser() in GetUserInfoTool
- [ ] T078 [US6] Count conversations with ConversationRepository->countByUser(user) in GetUserInfoTool
- [ ] T079 [US6] Format response with name, email, role (translated to Spanish), conversation count in GetUserInfoTool
- [ ] T080 [US6] Do NOT include user ID in response in GetUserInfoTool

### Repository Method

- [ ] T081 [US6] Add countByUser(User) method to ConversationRepositoryInterface in src/Domain/Repository/ConversationRepositoryInterface.php
- [ ] T082 [US6] Implement countByUser in DoctrineConversationRepository in src/Infrastructure/Repository/DoctrineConversationRepository.php

### Integration Tests

- [ ] T083 [P] [US6] Create test: user asks "¿quién soy?" → receives correct info in tests/Integration/GetUserInfoToolTest.php
- [ ] T084 [P] [US6] Create test: verify no internal IDs in response in tests/Integration/GetUserInfoToolTest.php

**Checkpoint**: Usuario puede consultar su información

---

## Phase 9: US7 - Admin Access Documentation

**Goal**: Documentar cómo acceder como administrador

**Independent Test**: Developer puede leer docs y acceder como admin exitosamente

### Story Details
- Priority: P3
- Test Criteria: Admin user en fixtures, credenciales documentadas, funcionalidades listadas

### Fixtures Update

- [ ] T085 [P] [US7] Update AppFixtures to create admin user if not exists in src/DataFixtures/AppFixtures.php
- [ ] T086 [US7] Set admin credentials: admin@myshop.com / admin123 in src/DataFixtures/AppFixtures.php
- [ ] T087 [US7] Assign ROLE_ADMIN to admin user in src/DataFixtures/AppFixtures.php
- [ ] T088 [US7] Load fixtures (docker-compose exec php bin/console doctrine:fixtures:load)

### Documentation

- [ ] T089 [P] [US7] Create ADMIN_FEATURES.md documenting admin-only chatbot features
- [ ] T090 [P] [US7] Update README.md with "Credenciales de Prueba" section
- [ ] T091 [P] [US7] Document admin credentials in README.md
- [ ] T092 [P] [US7] List admin features: GetAdminStatsTool in README.md
- [ ] T093 [P] [US7] Create CONVERSATION_PERSISTENCE.md explaining technical architecture
- [ ] T094 [P] [US7] Update API.md with new endpoint /api/chat/history/{conversationId}

### Verification Tests

- [ ] T095 [US7] Manual test: login as admin@myshop.com → access chatbot → ask for stats → verify works
- [ ] T096 [US7] Manual test: login as regular user → access chatbot → ask for stats → verify denied

**Checkpoint**: Admin access documented and functional

---

## Phase 10: Polish & Cross-Cutting Concerns

**Goal**: Completar tests, optimización, y validación final

### E2E Tests

- [ ] T097 [P] Create E2E test: complete conversation flow with persistence in tests/E2E/ConversationFlowTest.php
- [ ] T098 [P] Create E2E test: add to cart → view cart → checkout (no IDs exposed) in tests/E2E/ShoppingFlowTest.php
- [ ] T099 [P] Create E2E test: clear chat → verify new conversationId generated in tests/E2E/ClearChatFlowTest.php
- [ ] T100 [P] Create E2E test: admin stats access and permission validation in tests/E2E/AdminStatsFlowTest.php

### Performance Tests

- [ ] T101 [P] Create test: verify save message < 100ms in tests/Performance/ConversationPerformanceTest.php
- [ ] T102 [P] Create test: verify load history < 200ms in tests/Performance/ConversationPerformanceTest.php
- [ ] T103 [P] Create test: verify admin stats cache hit < 50ms in tests/Performance/AdminStatsCacheTest.php

### Security Validation

- [ ] T104 [P] Create test: verify all AI tools validate authentication in tests/Security/ToolAuthenticationTest.php
- [ ] T105 [P] Create test: verify GetAdminStatsTool only accessible by ROLE_ADMIN in tests/Security/AdminAuthorizationTest.php
- [ ] T106 [P] Create test: verify no internal IDs (UUID) exposed in any tool response in tests/Security/IdExposureTest.php

### Configuration Updates

- [ ] T107 Update config/packages/ai.yaml system prompt with new tools in config/packages/ai.yaml
- [ ] T108 Add ClearConversationTool to system prompt tool list in config/packages/ai.yaml
- [ ] T109 Add GetAdminStatsTool to system prompt with admin-only note in config/packages/ai.yaml
- [ ] T110 Add GetUserInfoTool to system prompt in config/packages/ai.yaml
- [ ] T111 Add instructions about conversation persistence in system prompt in config/packages/ai.yaml

### UI/UX Polish

- [ ] T112 Add CSS styling for "Limpiar chat" button in public/css/style.css
- [ ] T113 Add fade-in animation for loaded history messages in public/js/chatbot.js
- [ ] T114 Add loading spinner for history fetch in chatbot widget in public/js/chatbot.js
- [ ] T115 Add confirmation dialog before clearing chat in public/js/chatbot.js

### Final Validation

- [ ] T116 Run all unit tests: docker-compose exec php bin/console bin/phpunit tests/
- [ ] T117 Run all integration tests: docker-compose exec php bin/console bin/phpunit tests/Integration/
- [ ] T118 Run all E2E tests: docker-compose exec php bin/console bin/phpunit tests/E2E/
- [ ] T119 Clear Symfony cache: docker-compose exec php bin/console cache:clear
- [ ] T120 Verify no errors in logs: docker-compose logs php

**Checkpoint**: All tests passing, feature complete

---

## Dependencies & Parallel Execution

### Story Completion Order
```
Phase 2 (Foundational) → MUST complete first
└── Phase 3 (US1), Phase 4 (US2) → Can run in parallel (verification tasks)
    └── Phase 5 (US3) → MUST complete before Phase 6
        └── Phase 6 (US4) → Depends on US3
            ├── Phase 7 (US5) → Independent, can parallelize
            ├── Phase 8 (US6) → Independent, can parallelize
            └── Phase 9 (US7) → Documentation, can parallelize
                └── Phase 10 (Polish) → Final phase

```

### Parallel Opportunities per Story

**Phase 2**: T005, T006 (entities) | T014, T015 (tests)  
**Phase 3**: T016-T020 all parallel (verification tasks)  
**Phase 4**: T022-T025 all parallel (verification tasks)  
**Phase 5**: T027-T029 (use cases) | T044-T046 (tests)  
**Phase 6**: T047-T048 | T049-T051 | T057-T058  
**Phase 7**: T059-T064 (use case) | T065-T069 (tool) | T072-T074 (tests)  
**Phase 8**: T075-T080 (tool) | T081-T082 (repo) | T083-T084 (tests)  
**Phase 9**: T085-T088 (fixtures) | T089-T094 (docs) can all parallelize  
**Phase 10**: T097-T103 (tests) can all parallelize

---

## Summary

**Total Tasks**: 120  
**User Stories**: 7 (US1-US7)  
**Estimated Time**: 11-18 hours  

**Critical Path**:  
Phase 2 (Foundational) → Phase 5 (US3 Persistence) → Phase 6 (US4 Clear) → Phase 10 (Polish)

**MVP Scope**: Phase 1-6 (T001-T058) = Core security + persistence + clear functionality

**Parallel Work**: Phases 7, 8, 9 can be developed simultaneously after Phase 6
