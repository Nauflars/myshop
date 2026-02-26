import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/theme/app_theme.dart';
import 'core/widgets/offline_banner.dart';
import 'features/auth/presentation/screens/login_screen.dart';
import 'features/auth/presentation/screens/register_screen.dart';
import 'features/auth/presentation/providers/auth_provider.dart';
import 'features/products/presentation/screens/home_screen.dart';
import 'features/products/presentation/screens/product_list_screen.dart';
import 'features/products/presentation/screens/product_detail_screen.dart';
import 'features/products/presentation/screens/search_screen.dart';
import 'features/cart/presentation/screens/cart_screen.dart';
import 'features/checkout/presentation/screens/checkout_screen.dart';
import 'features/checkout/presentation/screens/order_confirmation_screen.dart';
import 'features/orders/presentation/screens/order_list_screen.dart';
import 'features/orders/presentation/screens/order_detail_screen.dart';
import 'features/chatbot/presentation/screens/chatbot_screen.dart';

/// Route names for navigation.
class AppRoutes {
  AppRoutes._();

  static const String login = '/login';
  static const String register = '/register';
  static const String home = '/';
  static const String products = '/products';
  static const String productDetail = '/products/:id';
  static const String search = '/search';
  static const String cart = '/cart';
  static const String checkout = '/checkout';
  static const String orderConfirmation = '/order-confirmation/:orderNumber';
  static const String orders = '/orders';
  static const String orderDetail = '/orders/:orderNumber';
  static const String chatbot = '/chatbot';
}

/// GoRouter provider with auth redirect guards.
final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: AppRoutes.home,
    debugLogDiagnostics: true,
    redirect: (context, state) {
      final isLoggedIn = authState.isAuthenticated;
      final isLoginRoute = state.matchedLocation == AppRoutes.login;
      final isRegisterRoute = state.matchedLocation == AppRoutes.register;
      final isAuthRoute = isLoginRoute || isRegisterRoute;

      // If not logged in and not on auth route, redirect to login
      if (!isLoggedIn && !isAuthRoute) {
        return AppRoutes.login;
      }

      // If logged in and on auth route, redirect to home
      if (isLoggedIn && isAuthRoute) {
        return AppRoutes.home;
      }

      return null;
    },
    routes: [
      // Auth routes
      GoRoute(
        path: AppRoutes.login,
        name: 'login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: AppRoutes.register,
        name: 'register',
        builder: (context, state) => const RegisterScreen(),
      ),

      // Main app routes
      GoRoute(
        path: AppRoutes.home,
        name: 'home',
        builder: (context, state) => const HomeScreen(),
      ),
      GoRoute(
        path: AppRoutes.products,
        name: 'products',
        builder: (context, state) => const ProductListScreen(),
      ),
      GoRoute(
        path: AppRoutes.productDetail,
        name: 'productDetail',
        builder: (context, state) {
          final id = state.pathParameters['id']!;
          return ProductDetailScreen(productId: id);
        },
      ),
      GoRoute(
        path: AppRoutes.search,
        name: 'search',
        builder: (context, state) => const SearchScreen(),
      ),
      GoRoute(
        path: AppRoutes.cart,
        name: 'cart',
        builder: (context, state) => const CartScreen(),
      ),
      GoRoute(
        path: AppRoutes.checkout,
        name: 'checkout',
        builder: (context, state) => const CheckoutScreen(),
      ),
      GoRoute(
        path: AppRoutes.orderConfirmation,
        name: 'orderConfirmation',
        builder: (context, state) {
          final orderNumber = state.pathParameters['orderNumber']!;
          return OrderConfirmationScreen(orderNumber: orderNumber);
        },
      ),
      GoRoute(
        path: AppRoutes.orders,
        name: 'orders',
        builder: (context, state) => const OrderListScreen(),
      ),
      GoRoute(
        path: AppRoutes.orderDetail,
        name: 'orderDetail',
        builder: (context, state) {
          final orderNumber = state.pathParameters['orderNumber']!;
          return OrderDetailScreen(orderNumber: orderNumber);
        },
      ),
      GoRoute(
        path: AppRoutes.chatbot,
        name: 'chatbot',
        builder: (context, state) => const ChatbotScreen(),
      ),
    ],
  );
});

/// Root application widget with MaterialApp.router and GoRouter.
class MyShopApp extends ConsumerWidget {
  const MyShopApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'MyShop',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.system,
      routerConfig: router,
      builder: (context, child) {
        return Column(
          children: [
            const OfflineBanner(),
            Expanded(child: child ?? const SizedBox.shrink()),
          ],
        );
      },
    );
  }
}
