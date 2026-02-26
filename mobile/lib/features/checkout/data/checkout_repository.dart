import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../orders/data/models/order_model.dart';
import '../../orders/domain/entities/order.dart';

/// Repository handling checkout API calls.
class CheckoutRepository {
  final ApiClient _apiClient;

  CheckoutRepository(this._apiClient);

  /// Place an order from the current cart.
  /// Returns the created order.
  Future<Order> placeOrder({ShippingAddress? shippingAddress}) async {
    final Map<String, dynamic> data = {};

    if (shippingAddress != null) {
      data['shippingAddress'] = {
        'street': shippingAddress.street,
        'city': shippingAddress.city,
        if (shippingAddress.state.isNotEmpty) 'state': shippingAddress.state,
        'zip': shippingAddress.zip,
        'country': shippingAddress.country,
      };
    }

    final response = await _apiClient.dio.post(
      ApiEndpoints.orders,
      data: data,
    );

    return OrderModel.fromJson(response.data as Map<String, dynamic>);
  }
}
