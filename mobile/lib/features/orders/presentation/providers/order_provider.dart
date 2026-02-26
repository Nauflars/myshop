import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/error/error_handler.dart';
import '../../../../core/network/api_client.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../checkout/data/checkout_repository.dart';
import '../../data/order_repository.dart';
import '../../domain/entities/order.dart';

// -- Providers --

final checkoutRepositoryProvider = Provider<CheckoutRepository>((ref) {
  return CheckoutRepository(ref.watch(apiClientProvider));
});

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return OrderRepository(ref.watch(apiClientProvider));
});

// -- Order List --

/// State for the order list.
class OrderListState {
  final List<Order> orders;
  final bool isLoading;
  final String? error;

  const OrderListState({
    this.orders = const [],
    this.isLoading = false,
    this.error,
  });

  OrderListState copyWith({
    List<Order>? orders,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return OrderListState(
      orders: orders ?? this.orders,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

/// Notifier managing order list state.
class OrderListNotifier extends StateNotifier<OrderListState> {
  final OrderRepository _repository;

  OrderListNotifier(this._repository) : super(const OrderListState());

  /// Load all orders.
  Future<void> loadOrders() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final orders = await _repository.listOrders();
      state = state.copyWith(orders: orders, isLoading: false);
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Refresh orders.
  Future<void> refresh() => loadOrders();
}

final orderListProvider =
    StateNotifierProvider<OrderListNotifier, OrderListState>((ref) {
  return OrderListNotifier(ref.watch(orderRepositoryProvider));
});

// -- Single Order Detail --

final orderDetailProvider =
    FutureProvider.family<Order, String>((ref, orderNumber) async {
  final repository = ref.watch(orderRepositoryProvider);
  return repository.getOrder(orderNumber);
});

// -- Checkout --

/// State for the checkout flow.
class CheckoutState {
  final bool isPlacingOrder;
  final Order? completedOrder;
  final String? error;

  const CheckoutState({
    this.isPlacingOrder = false,
    this.completedOrder,
    this.error,
  });

  CheckoutState copyWith({
    bool? isPlacingOrder,
    Order? completedOrder,
    String? error,
    bool clearError = false,
    bool clearOrder = false,
  }) {
    return CheckoutState(
      isPlacingOrder: isPlacingOrder ?? this.isPlacingOrder,
      completedOrder:
          clearOrder ? null : (completedOrder ?? this.completedOrder),
      error: clearError ? null : (error ?? this.error),
    );
  }
}

/// Notifier managing checkout flow.
class CheckoutNotifier extends StateNotifier<CheckoutState> {
  final CheckoutRepository _checkoutRepo;

  CheckoutNotifier(this._checkoutRepo) : super(const CheckoutState());

  /// Place an order with optional shipping address.
  Future<bool> placeOrder({ShippingAddress? shippingAddress}) async {
    state = state.copyWith(
      isPlacingOrder: true,
      clearError: true,
      clearOrder: true,
    );
    try {
      final order =
          await _checkoutRepo.placeOrder(shippingAddress: shippingAddress);
      state = state.copyWith(
        isPlacingOrder: false,
        completedOrder: order,
      );
      return true;
    } catch (e) {
      state = state.copyWith(
        isPlacingOrder: false,
        error: AppErrorHandler.getMessage(e),
      );
      return false;
    }
  }

  /// Reset state for a new checkout.
  void reset() {
    state = const CheckoutState();
  }
}

final checkoutProvider =
    StateNotifierProvider<CheckoutNotifier, CheckoutState>((ref) {
  return CheckoutNotifier(ref.watch(checkoutRepositoryProvider));
});
