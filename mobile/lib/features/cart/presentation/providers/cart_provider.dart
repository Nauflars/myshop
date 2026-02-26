import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/error/error_handler.dart';
import '../../../../core/network/api_client.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../data/cart_repository.dart';
import '../../domain/entities/cart.dart';

// -- Providers --

final cartRepositoryProvider = Provider<CartRepository>((ref) {
  return CartRepository(ref.watch(apiClientProvider));
});

/// Cart state.
class CartState {
  final Cart cart;
  final bool isLoading;
  final String? error;

  const CartState({
    this.cart = const Cart(),
    this.isLoading = false,
    this.error,
  });

  /// Badge count for navigation bar.
  int get badgeCount => cart.totalQuantity;

  CartState copyWith({
    Cart? cart,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return CartState(
      cart: cart ?? this.cart,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

/// Cart notifier managing cart operations.
class CartNotifier extends StateNotifier<CartState> {
  final CartRepository _repository;

  CartNotifier(this._repository) : super(const CartState()) {
    loadCart();
  }

  /// Load cart from API.
  Future<void> loadCart() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final cart = await _repository.getCart();
      state = state.copyWith(cart: cart, isLoading: false);
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Add item to cart.
  Future<void> addItem({
    required String productId,
    int quantity = 1,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final cart = await _repository.addItem(
        productId: productId,
        quantity: quantity,
      );
      state = state.copyWith(cart: cart, isLoading: false);
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Update item quantity.
  Future<void> updateQuantity({
    required String productId,
    required int quantity,
  }) async {
    state = state.copyWith(clearError: true);
    try {
      final cart = await _repository.updateQuantity(
        productId: productId,
        quantity: quantity,
      );
      state = state.copyWith(cart: cart);
    } catch (e) {
      state = state.copyWith(
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Remove item from cart.
  Future<void> removeItem(String productId) async {
    state = state.copyWith(clearError: true);
    try {
      final cart = await _repository.removeItem(productId);
      state = state.copyWith(cart: cart);
    } catch (e) {
      state = state.copyWith(
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Clear cart.
  Future<void> clearCart() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _repository.clearCart();
      state = state.copyWith(cart: const Cart(), isLoading: false);
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Refresh cart (for use by other features like chatbot).
  Future<void> refresh() => loadCart();
}

final cartProvider =
    StateNotifierProvider<CartNotifier, CartState>((ref) {
  return CartNotifier(ref.watch(cartRepositoryProvider));
});
