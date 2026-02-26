# Tasks: Mobile Application

**Input**: Design documents from `/specs/015-mobile-app/`  
**Prerequisites**: plan.md (required), spec.md (required for user stories)

**Tests**: Tests are included for critical paths (backend PHPUnit for API changes, Flutter unit/widget tests).

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `src/`, `config/`, `migrations/`, `tests/` at repository root
- **Mobile**: `mobile/lib/`, `mobile/test/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization, backend API adaptations, and Flutter project scaffolding

### Backend API Adaptations

- [X] T001 Add `json_login` firewall and `/api/admin` access control to config/packages/security.yaml
- [X] T002 Create JSON login success handler in src/Infrastructure/Controller/ApiLoginController.php
- [X] T003 Add `shippingAddress` nullable field to Order entity in src/Domain/Entity/Order.php
- [X] T004 Create Doctrine migration for shippingAddress column on orders table in migrations/
- [X] T005 Modify checkout method to accept shippingAddress from request body in src/Infrastructure/Controller/OrderController.php
- [X] T006 Add pagination metadata (items, total, page, limit, hasMore) to product list response in src/Infrastructure/Controller/ProductController.php
- [X] T007 Add `nameEs` field to product serialization in src/Infrastructure/Controller/ProductController.php
- [X] T008 [P] Write PHPUnit tests for json_login authentication in tests/Integration/Controller/ApiLoginControllerTest.php
- [X] T009 [P] Write PHPUnit tests for checkout with shippingAddress in tests/Integration/Controller/OrderControllerTest.php
- [X] T010 [P] Write PHPUnit tests for paginated product list response in tests/Integration/Controller/ProductControllerTest.php

### Flutter Project Scaffolding

- [X] T011 Initialize Flutter project in mobile/ directory with `flutter create --org com.myshop mobile`
- [X] T012 Configure pubspec.yaml with dependencies: dio, flutter_riverpod, go_router, flutter_secure_storage, cookie_jar, dio_cookie_manager, hive, hive_flutter, connectivity_plus, cached_network_image, intl, flutter_markdown, fl_chart, firebase_messaging, mocktail in mobile/pubspec.yaml
- [X] T013 Configure analysis_options.yaml with flutter_lints rules in mobile/analysis_options.yaml
- [X] T014 Set minimum SDK versions: Android 10 (API 29) in mobile/android/app/build.gradle and iOS 15 in mobile/ios/Podfile

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T015 Create Dio API client with base URL configuration and JSON content type in mobile/lib/core/network/api_client.dart
- [X] T016 Implement cookie persistence interceptor using cookie_jar + flutter_secure_storage in mobile/lib/core/network/api_interceptors.dart
- [X] T017 Define centralized API endpoint constants for all backend routes in mobile/lib/core/network/api_endpoints.dart
- [X] T018 Create Material 3 app theme with brand colors (#06038D primary, #E87722 secondary) light/dark variants in mobile/lib/core/theme/app_theme.dart
- [X] T019 Create centralized error handler with user-friendly messages and offline detection in mobile/lib/core/error/error_handler.dart
- [X] T020 [P] Create shared loading indicator widget in mobile/lib/core/widgets/loading_widget.dart
- [X] T021 [P] Create shared error display widget in mobile/lib/core/widgets/error_widget.dart
- [X] T022 [P] Create offline connectivity banner widget using connectivity_plus in mobile/lib/core/widgets/offline_banner.dart
- [X] T023 [P] Create app-wide constants (categories, order statuses, roles) in mobile/lib/core/constants/app_constants.dart
- [X] T024 Configure GoRouter with route definitions and auth redirect guards in mobile/lib/app.dart
- [X] T025 Create main.dart entry point with ProviderScope and MaterialApp.router in mobile/lib/main.dart
- [X] T026 Write unit test for API client configuration and interceptors in mobile/test/unit/api_client_test.dart

**Checkpoint**: Foundation ready — user story implementation can now begin in parallel

---

## Phase 3: User Story 5 - Authentication & Registration (Priority: P1) 🎯 MVP

**Goal**: Users can register, log in, persist sessions, and log out. Roles (Customer/Seller/Admin) gate access to protected features.

**Independent Test**: Register new account → log out → log back in → verify session persists after app restart

### Tests for User Story 5

- [X] T027 [P] [US5] Write unit tests for AuthRepository (login, register, logout, session check) in mobile/test/unit/auth_repository_test.dart
- [X] T028 [P] [US5] Write widget tests for LoginScreen (form validation, error display, success navigation) in mobile/test/widget/login_screen_test.dart
- [X] T029 [P] [US5] Write widget tests for RegisterScreen (form fields, validation, success flow) in mobile/test/widget/register_screen_test.dart

### Implementation for User Story 5

- [X] T030 [P] [US5] Create User entity with id, name, email, roles in mobile/lib/features/auth/domain/entities/user.dart
- [X] T031 [P] [US5] Create UserModel (from/toJson) mapping API response to User entity in mobile/lib/features/auth/data/models/user_model.dart
- [X] T032 [US5] Implement AuthRepository with login (POST /api/login), register (POST /api/users), logout (GET /logout), getMe (GET /api/users/me) in mobile/lib/features/auth/data/auth_repository.dart
- [X] T033 [US5] Create AuthProvider (Riverpod StateNotifier) managing auth state, auto-login on startup via getMe in mobile/lib/features/auth/presentation/providers/auth_provider.dart
- [X] T034 [US5] Build LoginScreen with email/password fields, validation, error snackbar, remember-me option in mobile/lib/features/auth/presentation/screens/login_screen.dart
- [X] T035 [US5] Build RegisterScreen with name/email/password fields, validation, auto-login on success in mobile/lib/features/auth/presentation/screens/register_screen.dart
- [X] T036 [US5] Add auth guard to GoRouter — redirect unauthenticated users to LoginScreen in mobile/lib/app.dart

**Checkpoint**: User Story 5 fully functional — can register, login, logout, session persists

---

## Phase 4: User Story 1 - Customer Browses & Searches Products (Priority: P1) 🎯 MVP

**Goal**: Customers see personalized recommendations on home, browse catalog with filters, search semantically/keyword, view product details.

**Independent Test**: Open app → see recommendations → browse catalog → filter by category → search "running shoes" → view product detail

### Tests for User Story 1

- [X] T037 [P] [US1] Write unit tests for ProductRepository (list, search, getById, recommendations) in mobile/test/unit/product_repository_test.dart
- [X] T038 [P] [US1] Write widget tests for ProductListScreen (grid display, filters, pagination) in mobile/test/widget/product_list_screen_test.dart
- [X] T039 [P] [US1] Write widget tests for ProductDetailScreen (info display, add-to-cart button) in mobile/test/widget/product_detail_screen_test.dart

### Implementation for User Story 1

- [X] T040 [P] [US1] Create Product entity with id, name, nameEs, description, price, stock, category, inStock, lowStock in mobile/lib/features/products/domain/entities/product.dart
- [X] T041 [P] [US1] Create ProductModel (from/toJson) with price parsing from cents in mobile/lib/features/products/data/models/product_model.dart
- [X] T042 [US1] Implement ProductRepository with list (paginated + filters), search (semantic/keyword), getById, getRecommendations in mobile/lib/features/products/data/product_repository.dart
- [X] T043 [US1] Create ProductProvider (Riverpod) for catalog state, filters, pagination, and search results in mobile/lib/features/products/presentation/providers/product_provider.dart
- [X] T044 [P] [US1] Build ProductCard widget showing name, price, stock badge, category chip in mobile/lib/features/products/presentation/widgets/product_card.dart
- [X] T045 [US1] Build HomeScreen with app bar, search field, recommendations carousel, and bottom navigation bar in mobile/lib/features/products/presentation/screens/home_screen.dart
- [X] T046 [US1] Build ProductListScreen with category chips, price range slider, infinite-scroll grid, pull-to-refresh in mobile/lib/features/products/presentation/screens/product_list_screen.dart
- [X] T047 [US1] Build SearchScreen with text input, semantic/keyword mode toggle, debounced search (500ms), results list in mobile/lib/features/products/presentation/screens/search_screen.dart
- [X] T048 [US1] Build ProductDetailScreen with localized name, description, price, stock, category, quantity picker, add-to-cart button in mobile/lib/features/products/presentation/screens/product_detail_screen.dart

**Checkpoint**: User Story 1 fully functional — browse, filter, search, view products

---

## Phase 5: User Story 2 - Customer Manages Shopping Cart (Priority: P1) 🎯 MVP

**Goal**: Customers add/remove/update items in cart, see totals, stock validation enforced.

**Independent Test**: Add product to cart → view cart → change quantity → totals update → remove item → verify empty cart

### Tests for User Story 2

- [X] T049 [P] [US2] Write unit tests for CartRepository (get, addItem, updateItem, removeItem, clear) in mobile/test/unit/cart_repository_test.dart
- [X] T050 [P] [US2] Write widget tests for CartScreen (item list, quantity stepper, totals, empty state) in mobile/test/widget/cart_screen_test.dart

### Implementation for User Story 2

- [X] T051 [P] [US2] Create Cart and CartItem entities with totals calculation in mobile/lib/features/cart/domain/entities/cart.dart
- [X] T052 [P] [US2] Create CartModel and CartItemModel (from/toJson) in mobile/lib/features/cart/data/models/cart_model.dart
- [X] T053 [US2] Implement CartRepository with getCart, addItem, updateQuantity, removeItem, clearCart in mobile/lib/features/cart/data/cart_repository.dart
- [X] T054 [US2] Create CartProvider (Riverpod StateNotifier) with cart state, badge count, and refresh on changes in mobile/lib/features/cart/presentation/providers/cart_provider.dart
- [X] T055 [P] [US2] Build CartItemWidget with product name, quantity stepper, price, subtotal, remove button in mobile/lib/features/cart/presentation/widgets/cart_item_widget.dart
- [X] T056 [US2] Build CartScreen with item list, grand total, empty state message, proceed-to-checkout button, badge in bottom nav in mobile/lib/features/cart/presentation/screens/cart_screen.dart

**Checkpoint**: User Story 2 fully functional — full cart management with stock validation

---

## Phase 6: User Story 3 - Checkout & Order Tracking (Priority: P1) 🎯 MVP

**Goal**: Customers checkout from cart with shipping address, view order history and track status.

**Independent Test**: Go to checkout → enter shipping address → place order → see confirmation → view in order history with status

### Tests for User Story 3

- [X] T057 [P] [US3] Write unit tests for OrderRepository (checkout, list, getByNumber) and CheckoutRepository in mobile/test/unit/order_repository_test.dart
- [X] T058 [P] [US3] Write widget tests for CheckoutScreen (order summary, address form, place order) in mobile/test/widget/checkout_screen_test.dart

### Implementation for User Story 3

- [X] T059 [P] [US3] Create Order and OrderItem entities with status enum in mobile/lib/features/orders/domain/entities/order.dart
- [X] T060 [P] [US3] Create OrderModel and OrderItemModel (from/toJson) in mobile/lib/features/orders/data/models/order_model.dart
- [X] T061 [US3] Implement CheckoutRepository with placeOrder (POST /api/orders with shippingAddress) in mobile/lib/features/checkout/data/checkout_repository.dart
- [X] T062 [US3] Implement OrderRepository with listOrders, getOrder in mobile/lib/features/orders/data/order_repository.dart
- [X] T063 [US3] Create OrderProvider (Riverpod) for order list state and single order detail in mobile/lib/features/orders/presentation/providers/order_provider.dart
- [X] T064 [US3] Build CheckoutScreen with order summary from cart, shipping address form (street, city, zip, country), place-order button in mobile/lib/features/checkout/presentation/screens/checkout_screen.dart
- [X] T065 [US3] Build OrderConfirmationScreen with order number, success animation, view-order and continue-shopping buttons in mobile/lib/features/checkout/presentation/screens/order_confirmation_screen.dart
- [X] T066 [US3] Build OrderListScreen with order cards showing number, date, total, status badge (color-coded) in mobile/lib/features/orders/presentation/screens/order_list_screen.dart
- [X] T067 [US3] Build OrderDetailScreen with line items, totals, shipping address, status, timestamps in mobile/lib/features/orders/presentation/screens/order_detail_screen.dart

**Checkpoint**: User Story 3 fully functional — complete purchase flow end-to-end. **MVP COMPLETE** (auth + browse + cart + checkout + orders)

---

## Phase 7: User Story 4 - AI Customer Chatbot (Priority: P2)

**Goal**: Floating chatbot accessible from any screen. Natural language commands for search, cart, checkout, orders. Persistent conversation history.

**Independent Test**: Open chatbot → ask about products → add to cart via chat → check order status → clear conversation

### Tests for User Story 4

- [ ] T068 [P] [US4] Write unit tests for ChatbotRepository (sendMessage, getHistory, clear, resetContext) in mobile/test/unit/chatbot_repository_test.dart
- [ ] T069 [P] [US4] Write widget tests for ChatbotScreen (message bubbles, input, send, typing indicator) in mobile/test/widget/chatbot_screen_test.dart

### Implementation for User Story 4

- [ ] T070 [P] [US4] Create Message entity with id, text, role (user/bot), timestamp in mobile/lib/features/chatbot/domain/entities/message.dart
- [ ] T071 [P] [US4] Create MessageModel (from/toJson) mapping API response in mobile/lib/features/chatbot/data/models/message_model.dart
- [ ] T072 [US4] Implement ChatbotRepository with sendMessage (POST /api/chat), getHistory, clearConversation, resetContext in mobile/lib/features/chatbot/data/chatbot_repository.dart
- [ ] T073 [US4] Create ChatbotProvider (Riverpod StateNotifier) managing messages list, conversationId, loading state in mobile/lib/features/chatbot/presentation/providers/chatbot_provider.dart
- [ ] T074 [P] [US4] Build ChatBubble widget with user/bot styling, markdown rendering, timestamp in mobile/lib/features/chatbot/presentation/widgets/chat_bubble.dart
- [ ] T075 [US4] Build ChatbotScreen as bottom sheet or full screen with message list, text input, send button, typing indicator, clear/reset buttons in mobile/lib/features/chatbot/presentation/screens/chatbot_screen.dart
- [ ] T076 [US4] Add floating action button (chatbot FAB) to main scaffold visible on all authenticated screens in mobile/lib/app.dart
- [ ] T077 [US4] Integrate cart refresh — when chatbot response indicates cart change, trigger CartProvider refresh in mobile/lib/features/chatbot/presentation/providers/chatbot_provider.dart

**Checkpoint**: User Story 4 fully functional — full conversational shopping assistant

---

## Phase 8: User Story 6 - Admin Product Management (Priority: P3)

**Goal**: Admins/sellers manage products on mobile — CRUD operations + admin AI assistant for conversational stock management.

**Independent Test**: Login as admin → view product list → create product → edit product → check low stock via AI assistant → delete product

### Backend: Admin JSON API

- [ ] T078 Create AdminApiController with JSON endpoints under /api/admin/ in src/Infrastructure/Controller/AdminApiController.php:
  - GET /api/admin/dashboard (metrics)
  - GET /api/admin/products (paginated list with sort/filter)
  - POST /api/admin/products (create)
  - PUT /api/admin/products/{id} (update)
  - DELETE /api/admin/products/{id} (delete)
  - GET /api/admin/users (user list with search)
  - GET /api/admin/users/{id} (user details + stats)
  - GET /api/admin/unanswered-questions (paginated, filterable)
  - PUT /api/admin/unanswered-questions/{id} (update status + notes)
  - POST /api/admin/unanswered-questions/bulk-update (bulk status update)
  - GET /api/admin/search-metrics (metrics data)
- [ ] T079 [P] Write PHPUnit tests for AdminApiController endpoints in tests/Integration/Controller/AdminApiControllerTest.php

### Flutter: Admin Screens

- [ ] T080 [P] [US6] Create AdminRepository for product CRUD and admin assistant API calls in mobile/lib/features/admin/data/admin_repository.dart
- [ ] T081 [P] [US6] Create DashboardModel, AdminProductModel for admin-specific serialization in mobile/lib/features/admin/data/models/dashboard_model.dart
- [ ] T082 [US6] Create AdminProvider (Riverpod) for admin state (products, dashboard, users list) in mobile/lib/features/admin/presentation/providers/admin_provider.dart
- [ ] T083 [US6] Build AdminProductListScreen with search, sort, stock indicators in mobile/lib/features/admin/presentation/screens/admin_product_list_screen.dart
- [ ] T084 [US6] Build AdminProductFormScreen for create/edit with all product fields (name, nameEs, description, price, stock, category) in mobile/lib/features/admin/presentation/screens/admin_product_form_screen.dart
- [ ] T085 [US6] Build AdminAssistantScreen reusing ChatBubble widget, connected to POST /admin/assistant/chat in mobile/lib/features/admin/presentation/screens/admin_assistant_screen.dart
- [ ] T086 [US6] Add Admin tab to bottom navigation bar (visible only for ROLE_ADMIN/ROLE_SELLER users) in mobile/lib/app.dart

**Checkpoint**: User Story 6 fully functional — admin product management on mobile

---

## Phase 9: User Story 7 - Admin Dashboard & Analytics (Priority: P3)

**Goal**: Admins view business metrics, search analytics, and manage unanswered chatbot questions.

**Independent Test**: Login as admin → view dashboard metrics → review unanswered question → add notes → mark resolved

### Implementation for User Story 7

- [ ] T087 [P] [US7] Build AdminDashboardScreen with orders count, revenue, active users metrics using fl_chart in mobile/lib/features/admin/presentation/screens/admin_dashboard_screen.dart
- [ ] T088 [P] [US7] Build AdminUserListScreen with search and role filter in mobile/lib/features/admin/presentation/screens/admin_user_list_screen.dart
- [ ] T089 [P] [US7] Build AdminUserDetailScreen with user info and order statistics in mobile/lib/features/admin/presentation/screens/admin_user_detail_screen.dart
- [ ] T090 [US7] Build AdminQuestionsScreen with paginated list, status/reason filters in mobile/lib/features/admin/presentation/screens/admin_questions_screen.dart
- [ ] T091 [US7] Build AdminQuestionDetailScreen with full question, reason, admin notes form, mark-as-resolved button in mobile/lib/features/admin/presentation/screens/admin_question_detail_screen.dart
- [ ] T092 [US7] Build AdminMetricsScreen with search performance charts (response times, cache hit rate, API costs) in mobile/lib/features/admin/presentation/screens/admin_metrics_screen.dart

**Checkpoint**: User Story 7 fully functional — full admin dashboard and analytics on mobile

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Push notifications, offline support, platform polish, and integration testing

### Push Notifications

- [ ] T093 Install kreait/firebase-php via composer and configure FCM in backend
- [ ] T094 Create PushNotificationService that sends FCM notifications on order status change in src/Infrastructure/Service/PushNotificationService.php
- [ ] T095 Create DeviceToken entity and POST /api/users/me/device-token endpoint in src/Infrastructure/Controller/DeviceTokenController.php
- [ ] T096 Create Doctrine migration for device_tokens table in migrations/
- [ ] T097 Add firebase_messaging configuration for Android (google-services.json) and iOS (APNs) in mobile/android/ and mobile/ios/
- [ ] T098 Implement device token registration on login and notification handling with deep-link to order detail in mobile/lib/core/notifications/push_notification_handler.dart

### Offline Support & Polish

- [ ] T099 [P] Implement local cache layer using Hive for products, cart, and orders in mobile/lib/core/cache/local_cache.dart
- [ ] T100 [P] Add connectivity awareness — show offline_banner, serve cached data, queue failed writes in mobile/lib/core/network/connectivity_manager.dart
- [ ] T101 [P] Configure app launcher icon with MyShop brand using flutter_launcher_icons in mobile/pubspec.yaml
- [ ] T102 [P] Configure splash screen with primary blue (#06038D) using flutter_native_splash in mobile/pubspec.yaml
- [ ] T103 Support portrait and landscape orientations with responsive LayoutBuilder in all screens
- [ ] T104 Write integration test for complete purchase flow (register → browse → add to cart → checkout → view order) in mobile/test/integration/purchase_flow_test.dart
- [ ] T105 Run full PHPUnit test suite to verify no backend regressions after all changes
- [ ] T106 Test on Android emulator (API 29+) and iOS simulator (iOS 15+) — verify all screens and flows

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (Flutter project exists, backend auth ready)
- **US5 Auth (Phase 3)**: Depends on Phase 2 — MUST be first user story (gates all others)
- **US1 Products (Phase 4)**: Depends on Phase 3 (auth) — can start after auth works
- **US2 Cart (Phase 5)**: Depends on Phase 4 (products exist to add to cart)
- **US3 Checkout (Phase 6)**: Depends on Phase 5 (cart populated to checkout)
- **US4 Chatbot (Phase 7)**: Depends on Phase 3 (auth) — can run parallel with US1-US3
- **US6 Admin Products (Phase 8)**: Depends on Phase 1 (backend admin API) + Phase 3 (auth)
- **US7 Admin Dashboard (Phase 9)**: Depends on Phase 8 (admin repository/provider exists)
- **Polish (Phase 10)**: Depends on all desired user stories being complete

### User Story Dependencies

```
Phase 1: Setup
    ↓
Phase 2: Foundational
    ↓
Phase 3: US5 Authentication (MUST be first)
    ↓
    ├── Phase 4: US1 Products → Phase 5: US2 Cart → Phase 6: US3 Checkout ← MVP COMPLETE
    ├── Phase 7: US4 Chatbot (parallel with US1-US3 after auth)
    └── Phase 8: US6 Admin (parallel after backend admin API + auth) → Phase 9: US7 Dashboard
    ↓
Phase 10: Polish (after all stories)
```

### Within Each User Story

- Tests written first (TDD red phase)
- Domain entities/models before repositories
- Repositories before providers
- Providers before screens/widgets
- Core implementation before integration

### Parallel Opportunities

**Phase 1 (Setup)**:
```
T008, T009, T010 can run in parallel (independent PHPUnit tests)
```

**Phase 2 (Foundational)**:
```
T020, T021, T022, T023 can run in parallel (independent widgets/constants)
```

**Phase 3-7 (User Stories)**:
```
Within each story: test tasks marked [P] can run in parallel
Within each story: entity + model tasks marked [P] can run in parallel
US4 Chatbot can run in parallel with US1/US2/US3 (different feature module)
```

**Phase 8-9 (Admin)**:
```
T079 (backend tests) parallel with T080, T081 (Flutter admin repo/models)
T087, T088, T089 can run in parallel (independent screens)
```

---

## Parallel Example: User Story 1

```bash
# Launch all tests for US1 together:
T037: "Unit tests for ProductRepository"
T038: "Widget tests for ProductListScreen"
T039: "Widget tests for ProductDetailScreen"

# Launch entity + model together:
T040: "Create Product entity"
T041: "Create ProductModel"

# Then sequential: repository → provider → screens
```

---

## Implementation Strategy

### MVP First (Auth + Products + Cart + Checkout)

1. Complete Phase 1: Setup (backend adaptations + Flutter scaffolding)
2. Complete Phase 2: Foundational (core infrastructure)
3. Complete Phase 3: US5 Authentication
4. Complete Phase 4-6: US1 Products → US2 Cart → US3 Checkout
5. **STOP and VALIDATE**: Full purchase flow works end-to-end on both platforms
6. Deploy/demo if ready — **this is the MVP**

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US5 Auth → Can log in/register → Deploy/Demo
3. Add US1 Products → Can browse/search → Deploy/Demo
4. Add US2 Cart + US3 Checkout → Full purchase flow → Deploy/Demo (**MVP!**)
5. Add US4 Chatbot → Conversational shopping → Deploy/Demo
6. Add US6 Admin + US7 Dashboard → Admin on mobile → Deploy/Demo
7. Add Push Notifications + Offline → Production-ready → Deploy/Demo

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: US5 Auth → US1 Products → US2 Cart → US3 Checkout (sequential core flow)
   - Developer B: Backend Admin API (T078) → US4 Chatbot (after auth)
   - Developer C: US6 Admin Screens + US7 Dashboard (after backend admin API)
3. All converge for Phase 10 Polish

---

## Notes

- [P] tasks = different files, no dependencies on incomplete tasks
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Backend adaptations (Phase 1) are minimal but **critical** — the mobile app cannot function without json_login
- The Order entity currently has NO shippingAddress field — the migration in T004 adds it
- Admin Twig pages continue to work unchanged — new JSON endpoints are additive
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
