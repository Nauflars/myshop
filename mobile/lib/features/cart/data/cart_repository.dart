import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../domain/entities/cart.dart';
import 'models/cart_model.dart';

/// Repository handling cart API calls.
class CartRepository {
  final ApiClient _apiClient;

  CartRepository(this._apiClient);

  /// Get current cart.
  Future<Cart> getCart() async {
    final response = await _apiClient.dio.get(ApiEndpoints.cart);
    return CartModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Add item to cart.
  Future<Cart> addItem({
    required String productId,
    int quantity = 1,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.cartItems,
      data: {
        'productId': productId,
        'quantity': quantity,
      },
    );
    return CartModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Update item quantity in cart.
  Future<Cart> updateQuantity({
    required String productId,
    required int quantity,
  }) async {
    final response = await _apiClient.dio.put(
      ApiEndpoints.cartUpdateItem(productId),
      data: {'quantity': quantity},
    );
    return CartModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Remove item from cart.
  Future<Cart> removeItem(String productId) async {
    final response = await _apiClient.dio.delete(
      ApiEndpoints.cartRemoveItem(productId),
    );
    return CartModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Clear cart.
  Future<void> clearCart() async {
    await _apiClient.dio.delete(ApiEndpoints.cart);
  }
}
