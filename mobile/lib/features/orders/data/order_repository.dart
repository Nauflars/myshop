import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../domain/entities/order.dart';
import 'models/order_model.dart';

/// Repository handling order API calls.
class OrderRepository {
  final ApiClient _apiClient;

  OrderRepository(this._apiClient);

  /// List all orders for the current user.
  Future<List<Order>> listOrders() async {
    final response = await _apiClient.dio.get(ApiEndpoints.orders);
    final list = response.data as List<dynamic>;
    return list
        .map((json) => OrderModel.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  /// Get a single order by order number.
  Future<Order> getOrder(String orderNumber) async {
    final response = await _apiClient.dio.get(
      ApiEndpoints.orderDetail(orderNumber),
    );
    return OrderModel.fromJson(response.data as Map<String, dynamic>);
  }
}
