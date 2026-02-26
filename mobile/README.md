# MyShop Mobile Application

Cross-platform mobile app (Android + iOS + Web) built with Flutter, providing full feature parity with the MyShop web application.

## Tech Stack

| Technology | Purpose |
|-----------|---------|
| Flutter 3.x / Dart 3.x | Cross-platform UI framework |
| flutter_riverpod | State management + dependency injection |
| dio + cookie_jar | HTTP client with session persistence |
| go_router | Declarative routing with auth guards |
| flutter_secure_storage | Encrypted local storage |
| Material 3 | Design system |

## Design System

The mobile app mirrors the web CSS design system:

| Token | Value | Usage |
|-------|-------|-------|
| Primary | `#06038D` (deep navy) | AppBar, navigation, structural |
| Secondary | `#E87722` (vibrant orange) | CTAs, buttons, prices, accents |
| Background | `#F5F5F5` | Scaffold background |
| Text | `#333333` | Body text |
| Text Light | `#666666` | Secondary text |
| Border | `#EAEAEA` | Card borders, dividers |
| Success | `#1AA04F` | In stock, delivered |
| Warning | `#FFBA00` | Low stock, pending |
| Error | `#FF4848` | Out of stock, cancelled |

### Button Colors
- **ElevatedButton**: Orange (`#E87722`) background, white text — primary CTAs
- **TextButton**: Navy (`#06038D`) text — secondary links
- **OutlinedButton**: Grey border — tertiary actions
- **NavigationBar**: Orange selected, grey unselected
- **FilterChip/SegmentedButton**: Navy selected state

### Gradients
- **NavBar**: Navy → dark navy (AppBar flexibleSpace)
- **Hero**: Navy → light navy → orange (welcome banners)
- **Chat header**: Navy → orange

## Architecture

```
lib/
├── main.dart              # Entry point
├── app.dart               # GoRouter + MaterialApp
├── core/
│   ├── network/           # Dio client, interceptors, endpoints
│   ├── theme/             # Material 3 theme (brand colors)
│   ├── constants/         # App-wide constants
│   ├── error/             # Centralized error handling
│   └── widgets/           # Shared widgets (loading, error, offline)
└── features/
    ├── auth/              # Login, register, session
    ├── products/          # Home, catalog, search, detail
    ├── cart/              # Cart management
    ├── checkout/          # Checkout, order confirmation
    ├── orders/            # Order list, order detail
    └── chatbot/           # AI chat assistant (stub)
```

Each feature follows **clean architecture**: `domain/entities` → `data/models+repository` → `presentation/providers+screens+widgets`.

## Implemented Features (Phases 1-6 MVP)

- **Authentication**: Login, register, session persistence, logout
- **Product Browsing**: Home with recommendations, catalog with filters, search (semantic + keyword)
- **Product Detail**: Localized names, stock status, quantity picker, add-to-cart
- **Cart Management**: Add/remove/update items, quantity steppers, grand total, stock validation
- **Checkout**: Order summary, shipping address form, place order
- **Order Tracking**: Order history with status badges (Pending/Confirmed/Shipped/Delivered/Cancelled)
- **Cart Badge**: Real-time item count on navigation icons
- **Dark Theme**: Full dark mode support following system preference

## Development Setup

### Prerequisites
- Flutter 3.22+ / Dart 3.4+
- Android Studio (emulator) or Xcode (iOS simulator)
- Backend running at `http://10.0.2.2:8080` (Android emulator) or `http://localhost:8080` (web)

### Running

```bash
# From mobile/ directory
flutter pub get
flutter analyze
flutter test

# Run on Chrome (web)
flutter run -d chrome

# Run on Android emulator
flutter run -d emulator-5554

# Run tests
flutter test
```

### WSL Development Notes

Source files live in WSL (`/var/www2/myshop/mobile/`). For building on Windows:

```powershell
# Map WSL path
subst Z: "\\\\wsl.localhost\\Ubuntu-20.04\\var\\www2\\myshop\\mobile"

# Analyze and test from Z:
cd Z:\; flutter analyze; flutter test

# For emulator/device builds, sync to local path
robocopy "\\\\wsl.localhost\\Ubuntu-20.04\\var\\www2\\myshop\\mobile" "C:\\Users\\<user>\\dev\\myshop-mobile" /MIR /XD .dart_tool build .gradle
cd C:\\Users\\<user>\\dev\\myshop-mobile; flutter run -d emulator-5554
```

## Remaining Phases

- **Phase 7**: AI Chatbot (T068-T077) — conversational shopping assistant
- **Phase 8**: Admin Product Management (T078-T086) — mobile CRUD
- **Phase 9**: Admin Dashboard (T087-T092) — metrics and analytics
- **Phase 10**: Polish (T093-T106) — push notifications, offline support, integration tests
