# Implementation Plan: Mobile Application

**Feature Branch**: `015-mobile-app`  
**Created**: 2026-02-25  
**Spec**: [spec.md](spec.md)  
**Status**: Draft

## Summary

Build a cross-platform mobile application (Android + iOS) using Flutter that provides full feature parity with the existing MyShop web application. The app consumes the existing Symfony backend API with minimal backend adaptations to enable mobile authentication and admin JSON endpoints.

## Technical Context

| Aspect | Details |
|--------|---------|
| **Language** | Dart 3.x (Flutter mobile), PHP 8.3 (backend adaptations) |
| **Mobile Framework** | Flutter 3.x with Material 3 design system |
| **Backend Framework** | Symfony 7.x (existing — minimal changes only) |
| **State Management** | flutter_riverpod |
| **HTTP Client** | dio + cookie_jar for session persistence |
| **Routing** | go_router (declarative, deep-link support) |
| **Local Storage** | flutter_secure_storage (auth tokens/cookies), hive (offline cache) |
| **Push Notifications** | Firebase Cloud Messaging (FCM) for Android + iOS |
| **Testing** | flutter_test + mocktail (unit/widget), integration_test (E2E), PHPUnit (backend) |
| **Min Platforms** | Android 10 (API 29), iOS 15 |
| **Database** | MySQL 8.0, MongoDB 7.0, Redis 7 (existing — no changes) |
| **Performance** | Home screen < 3s load on 4G, search < 2s, chatbot < 3s |
| **Constraints** | Must work with existing session-based auth adapted via json_login; backend changes kept minimal |

## Constitution Check

- [x] **TDD**: Widget tests and unit tests for all repositories, providers, and use cases
- [x] **DDD**: Flutter clean architecture mirrors backend: domain entities, use cases, repositories, presentation
- [x] **SOLID**: Feature-modular structure with dependency injection via Riverpod providers
- [x] **Test Coverage**: Unit tests for business logic, widget tests for screens, integration tests for E2E flows
- [x] **Clean Code**: Dart linting via `flutter_lints`, consistent naming, no business logic in widgets

### Red Flags Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| Backend uses session/CSRF auth (not mobile-friendly) | HIGH | Add `json_login` firewall (~10 lines YAML) — minimal backend change |
| Admin endpoints return HTML not JSON | MEDIUM | Create new `AdminApiController` with JSON endpoints under `/api/admin/` |
| Order entity lacks shippingAddress field | MEDIUM | Add nullable field + Doctrine migration |
| Product list has no pagination metadata | LOW | Wrap response in `{items, total, page, limit, hasMore}` |
| No push notification infrastructure exists | LOW | Add FCM PHP library + event subscriber for order status changes |

## Project Structure

### Documentation

```
specs/015-mobile-app/
├── spec.md                    # Feature specification
├── plan.md                    # This file
├── tasks.md                   # Task breakdown
└── checklists/
    └── requirements.md        # Quality checklist
```

### Source Code — Backend Adaptations

```
config/
└── packages/
    └── security.yaml          # MODIFY: Add json_login firewall for /api/login

src/Infrastructure/Controller/
├── ApiLoginController.php     # NEW: JSON login success handler
├── AdminApiController.php     # NEW: Admin JSON API endpoints
├── OrderController.php        # MODIFY: Accept shippingAddress in checkout
└── ProductController.php      # MODIFY: Add pagination metadata, nameEs

src/Domain/Entity/
└── Order.php                  # MODIFY: Add shippingAddress field

src/Infrastructure/Service/
└── PushNotificationService.php # NEW: FCM push notifications

migrations/
└── VersionXXX.php             # NEW: shippingAddress on orders, device_tokens table
```

### Source Code — Flutter Mobile App

```
mobile/
├── pubspec.yaml               # Flutter dependencies
├── analysis_options.yaml      # Dart linting rules
├── android/                   # Android platform config
├── ios/                       # iOS platform config
├── lib/
│   ├── main.dart              # App entry point
│   ├── app.dart               # MaterialApp + GoRouter setup
│   ├── core/
│   │   ├── network/
│   │   │   ├── api_client.dart          # Dio HTTP client + cookie management
│   │   │   ├── api_endpoints.dart       # Centralized endpoint constants
│   │   │   └── api_interceptors.dart    # Auth, error, logging interceptors
│   │   ├── theme/
│   │   │   └── app_theme.dart           # Material 3 theme (brand colors)
│   │   ├── constants/
│   │   │   └── app_constants.dart       # App-wide constants
│   │   ├── error/
│   │   │   └── error_handler.dart       # Centralized error handling
│   │   └── widgets/
│   │       ├── loading_widget.dart      # Shared loading indicator
│   │       ├── error_widget.dart        # Shared error display
│   │       └── offline_banner.dart      # Connectivity indicator
│   ├── features/
│   │   ├── auth/
│   │   │   ├── data/
│   │   │   │   ├── auth_repository.dart
│   │   │   │   └── models/user_model.dart
│   │   │   ├── domain/
│   │   │   │   └── entities/user.dart
│   │   │   └── presentation/
│   │   │       ├── providers/auth_provider.dart
│   │   │       ├── screens/login_screen.dart
│   │   │       └── screens/register_screen.dart
│   │   ├── products/
│   │   │   ├── data/
│   │   │   │   ├── product_repository.dart
│   │   │   │   └── models/product_model.dart
│   │   │   ├── domain/
│   │   │   │   └── entities/product.dart
│   │   │   └── presentation/
│   │   │       ├── providers/product_provider.dart
│   │   │       ├── screens/home_screen.dart
│   │   │       ├── screens/product_list_screen.dart
│   │   │       ├── screens/product_detail_screen.dart
│   │   │       ├── screens/search_screen.dart
│   │   │       └── widgets/product_card.dart
│   │   ├── cart/
│   │   │   ├── data/
│   │   │   │   ├── cart_repository.dart
│   │   │   │   └── models/cart_model.dart
│   │   │   ├── domain/
│   │   │   │   └── entities/cart.dart
│   │   │   └── presentation/
│   │   │       ├── providers/cart_provider.dart
│   │   │       ├── screens/cart_screen.dart
│   │   │       └── widgets/cart_item_widget.dart
│   │   ├── checkout/
│   │   │   ├── data/
│   │   │   │   └── checkout_repository.dart
│   │   │   └── presentation/
│   │   │       ├── screens/checkout_screen.dart
│   │   │       └── screens/order_confirmation_screen.dart
│   │   ├── orders/
│   │   │   ├── data/
│   │   │   │   ├── order_repository.dart
│   │   │   │   └── models/order_model.dart
│   │   │   ├── domain/
│   │   │   │   └── entities/order.dart
│   │   │   └── presentation/
│   │   │       ├── providers/order_provider.dart
│   │   │       ├── screens/order_list_screen.dart
│   │   │       └── screens/order_detail_screen.dart
│   │   ├── chatbot/
│   │   │   ├── data/
│   │   │   │   ├── chatbot_repository.dart
│   │   │   │   └── models/message_model.dart
│   │   │   ├── domain/
│   │   │   │   └── entities/message.dart
│   │   │   └── presentation/
│   │   │       ├── providers/chatbot_provider.dart
│   │   │       ├── screens/chatbot_screen.dart
│   │   │       └── widgets/chat_bubble.dart
│   │   └── admin/
│   │       ├── data/
│   │       │   ├── admin_repository.dart
│   │       │   └── models/dashboard_model.dart
│   │       └── presentation/
│   │           ├── providers/admin_provider.dart
│   │           ├── screens/admin_dashboard_screen.dart
│   │           ├── screens/admin_product_list_screen.dart
│   │           ├── screens/admin_product_form_screen.dart
│   │           ├── screens/admin_user_list_screen.dart
│   │           ├── screens/admin_user_detail_screen.dart
│   │           ├── screens/admin_questions_screen.dart
│   │           ├── screens/admin_question_detail_screen.dart
│   │           ├── screens/admin_metrics_screen.dart
│   │           └── screens/admin_assistant_screen.dart
│   └── test/
│       ├── unit/
│       │   ├── auth_repository_test.dart
│       │   ├── product_repository_test.dart
│       │   ├── cart_repository_test.dart
│       │   ├── order_repository_test.dart
│       │   └── chatbot_repository_test.dart
│       ├── widget/
│       │   ├── login_screen_test.dart
│       │   ├── product_list_screen_test.dart
│       │   ├── cart_screen_test.dart
│       │   └── checkout_screen_test.dart
│       └── integration/
│           └── purchase_flow_test.dart
```

## Key Libraries

### Flutter (mobile/pubspec.yaml)

| Package | Purpose |
|---------|---------|
| `flutter_riverpod` | State management with dependency injection |
| `dio` | HTTP client with interceptors |
| `cookie_jar` + `dio_cookie_manager` | Session cookie persistence |
| `go_router` | Declarative routing with deep linking |
| `flutter_secure_storage` | Encrypted local storage for session data |
| `hive` + `hive_flutter` | Fast local cache for offline support |
| `firebase_messaging` | Push notifications (FCM) |
| `cached_network_image` | Image caching for product images |
| `connectivity_plus` | Network state detection |
| `fl_chart` | Admin dashboard charts |
| `flutter_markdown` | Chatbot message rendering |
| `intl` | Localization and number/date formatting |
| `mocktail` | Testing mocks |

### Backend (composer.json additions)

| Package | Purpose |
|---------|---------|
| `kreait/firebase-php` | FCM push notification sending |
