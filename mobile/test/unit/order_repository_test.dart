import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/core/network/api_endpoints.dart';
import 'package:mobile/features/checkout/data/checkout_repository.dart';
import 'package:mobile/features/orders/data/models/order_model.dart';
import 'package:mobile/features/orders/data/order_repository.dart';
import 'package:mobile/features/orders/domain/entities/order.dart';

class MockApiClient extends Mock implements ApiClient {}

class MockDio extends Mock implements Dio {}

void main() {
  late MockApiClient mockApiClient;
  late MockDio mockDio;
  late OrderRepository orderRepo;
  late CheckoutRepository checkoutRepo;

  final testOrderJson = {
    'id': 'abc-123',
    'orderNumber': 'ORD-20260225-A1B2C3D4',
    'userId': 'user-1',
    'items': [
      {
        'productId': 'p1',
        'productName': 'Widget Pro',
        'quantity': 2,
        'priceInCents': 1999,
        'subtotalInCents': 3998,
      },
    ],
    'totalInCents': 3998,
    'currency': 'EUR',
    'status': 'PENDING',
    'shippingAddress': {
      'street': '123 Main St',
      'city': 'Madrid',
      'zip': '28001',
      'country': 'Spain',
    },
    'createdAt': '2026-02-25T14:30:00+00:00',
    'updatedAt': '2026-02-25T14:30:00+00:00',
  };

  setUp(() {
    mockApiClient = MockApiClient();
    mockDio = MockDio();
    when(() => mockApiClient.dio).thenReturn(mockDio);
    orderRepo = OrderRepository(mockApiClient);
    checkoutRepo = CheckoutRepository(mockApiClient);
  });

  // ── Order entity ──────────────────────────────────────

  group('OrderStatus', () {
    test('fromString parses valid status', () {
      expect(OrderStatus.fromString('PENDING'), OrderStatus.pending);
      expect(OrderStatus.fromString('CONFIRMED'), OrderStatus.confirmed);
      expect(OrderStatus.fromString('SHIPPED'), OrderStatus.shipped);
      expect(OrderStatus.fromString('DELIVERED'), OrderStatus.delivered);
      expect(OrderStatus.fromString('CANCELLED'), OrderStatus.cancelled);
    });

    test('fromString defaults to pending for unknown', () {
      expect(OrderStatus.fromString('UNKNOWN'), OrderStatus.pending);
    });

    test('isActive returns correctly', () {
      expect(OrderStatus.pending.isActive, isTrue);
      expect(OrderStatus.confirmed.isActive, isTrue);
      expect(OrderStatus.shipped.isActive, isTrue);
      expect(OrderStatus.delivered.isActive, isFalse);
      expect(OrderStatus.cancelled.isActive, isFalse);
    });

    test('label returns human-readable text', () {
      expect(OrderStatus.pending.label, 'Pending');
      expect(OrderStatus.delivered.label, 'Delivered');
    });
  });

  group('ShippingAddress', () {
    test('formatted returns multi-line address', () {
      const addr = ShippingAddress(
        street: '123 Main St',
        city: 'Madrid',
        zip: '28001',
        country: 'Spain',
      );
      expect(addr.formatted, contains('123 Main St'));
      expect(addr.formatted, contains('Madrid'));
      expect(addr.formatted, contains('Spain'));
    });

    test('equality based on street, city, zip, country', () {
      const a = ShippingAddress(
          street: '123', city: 'A', zip: '00', country: 'X');
      const b = ShippingAddress(
          street: '123', city: 'A', zip: '00', country: 'X');
      expect(a, equals(b));
    });
  });

  group('Order entity', () {
    test('totalDecimal and formattedTotal', () {
      final order = OrderModel.fromJson(testOrderJson);
      expect(order.totalDecimal, 39.98);
      expect(order.formattedTotal, '€39.98');
    });

    test('itemCount and totalQuantity', () {
      final order = OrderModel.fromJson(testOrderJson);
      expect(order.itemCount, 1);
      expect(order.totalQuantity, 2);
    });
  });

  group('OrderItem entity', () {
    test('price formatting', () {
      final order = OrderModel.fromJson(testOrderJson);
      final item = order.items.first;
      expect(item.priceDecimal, 19.99);
      expect(item.formattedPrice, '€19.99');
      expect(item.subtotalDecimal, 39.98);
      expect(item.formattedSubtotal, '€39.98');
    });
  });

  // ── OrderModel JSON ──────────────────────────────────

  group('OrderModel.fromJson', () {
    test('parses full order response', () {
      final model = OrderModel.fromJson(testOrderJson);
      expect(model.id, 'abc-123');
      expect(model.orderNumber, 'ORD-20260225-A1B2C3D4');
      expect(model.userId, 'user-1');
      expect(model.items, hasLength(1));
      expect(model.totalInCents, 3998);
      expect(model.currency, 'EUR');
      expect(model.status, OrderStatus.pending);
      expect(model.shippingAddress, isNotNull);
      expect(model.shippingAddress!.street, '123 Main St');
      expect(model.createdAt, isNotNull);
    });

    test('handles null shippingAddress', () {
      final json = Map<String, dynamic>.from(testOrderJson);
      json['shippingAddress'] = null;
      final model = OrderModel.fromJson(json);
      expect(model.shippingAddress, isNull);
    });

    test('handles missing optional fields', () {
      final model = OrderModel.fromJson({
        'items': [],
      });
      expect(model.id, '');
      expect(model.orderNumber, '');
      expect(model.status, OrderStatus.pending);
      expect(model.currency, 'EUR');
    });
  });

  group('ShippingAddressModel', () {
    test('fromJson and toJson round-trip', () {
      final model = ShippingAddressModel.fromJson({
        'street': '456 Oak Ave',
        'city': 'Barcelona',
        'state': 'CT',
        'zip': '08001',
        'country': 'Spain',
      });
      expect(model.street, '456 Oak Ave');
      final json = model.toJson();
      expect(json['street'], '456 Oak Ave');
      expect(json['state'], 'CT');
    });
  });

  // ── OrderRepository API ──────────────────────────────

  group('OrderRepository', () {
    test('listOrders sends GET to orders endpoint', () async {
      when(() => mockDio.get(ApiEndpoints.orders)).thenAnswer(
        (_) async => Response(
          data: [testOrderJson],
          statusCode: 200,
          requestOptions: RequestOptions(path: ApiEndpoints.orders),
        ),
      );

      final orders = await orderRepo.listOrders();
      expect(orders, hasLength(1));
      expect(orders.first.orderNumber, 'ORD-20260225-A1B2C3D4');
      verify(() => mockDio.get(ApiEndpoints.orders)).called(1);
    });

    test('listOrders handles empty list', () async {
      when(() => mockDio.get(ApiEndpoints.orders)).thenAnswer(
        (_) async => Response(
          data: [],
          statusCode: 200,
          requestOptions: RequestOptions(path: ApiEndpoints.orders),
        ),
      );

      final orders = await orderRepo.listOrders();
      expect(orders, isEmpty);
    });

    test('getOrder sends GET to order detail endpoint', () async {
      const orderNum = 'ORD-20260225-A1B2C3D4';
      final url = ApiEndpoints.orderDetail(orderNum);
      when(() => mockDio.get(url)).thenAnswer(
        (_) async => Response(
          data: testOrderJson,
          statusCode: 200,
          requestOptions: RequestOptions(path: url),
        ),
      );

      final order = await orderRepo.getOrder(orderNum);
      expect(order.orderNumber, orderNum);
      verify(() => mockDio.get(url)).called(1);
    });
  });

  // ── CheckoutRepository API ───────────────────────────

  group('CheckoutRepository', () {
    test('placeOrder sends POST with shipping address', () async {
      when(() => mockDio.post(
            ApiEndpoints.orders,
            data: any(named: 'data'),
          )).thenAnswer(
        (_) async => Response(
          data: testOrderJson,
          statusCode: 201,
          requestOptions: RequestOptions(path: ApiEndpoints.orders),
        ),
      );

      const address = ShippingAddress(
        street: '123 Main St',
        city: 'Madrid',
        zip: '28001',
        country: 'Spain',
      );

      final order = await checkoutRepo.placeOrder(shippingAddress: address);
      expect(order.orderNumber, 'ORD-20260225-A1B2C3D4');
      verify(() => mockDio.post(
            ApiEndpoints.orders,
            data: any(named: 'data'),
          )).called(1);
    });

    test('placeOrder without address sends empty data', () async {
      when(() => mockDio.post(
            ApiEndpoints.orders,
            data: any(named: 'data'),
          )).thenAnswer(
        (_) async => Response(
          data: testOrderJson,
          statusCode: 201,
          requestOptions: RequestOptions(path: ApiEndpoints.orders),
        ),
      );

      final order = await checkoutRepo.placeOrder();
      expect(order.orderNumber, 'ORD-20260225-A1B2C3D4');
    });
  });
}
