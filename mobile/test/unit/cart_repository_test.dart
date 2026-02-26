import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/core/network/api_endpoints.dart';
import 'package:mobile/features/cart/data/cart_repository.dart';
import 'package:mobile/features/cart/data/models/cart_model.dart';
import 'package:mobile/features/cart/domain/entities/cart.dart';

class MockApiClient extends Mock implements ApiClient {}

class MockDio extends Mock implements Dio {}

void main() {
  late MockApiClient mockApiClient;
  late MockDio mockDio;
  late CartRepository repository;

  final testCartJson = {
    'id': '42',
    'items': [
      {
        'productId': 'p1',
        'productName': 'Widget A',
        'quantity': 2,
        'priceInCents': 1500,
        'subtotalInCents': 3000,
      },
      {
        'productId': 'p2',
        'productName': 'Widget B',
        'quantity': 1,
        'priceInCents': 2500,
        'subtotalInCents': 2500,
      },
    ],
    'totalInCents': 5500,
    'currency': 'EUR',
    'itemCount': 2,
    'totalQuantity': 3,
    'updatedAt': '2025-02-10T12:00:00+00:00',
  };

  setUp(() {
    mockApiClient = MockApiClient();
    mockDio = MockDio();
    when(() => mockApiClient.dio).thenReturn(mockDio);
    repository = CartRepository(mockApiClient);
  });

  // ── CartItem entity ──────────────────────────────────────
  group('CartItem entity', () {
    test('priceDecimal converts cents to decimal', () {
      const item = CartItem(
        productId: 'p1',
        productName: 'Test',
        quantity: 1,
        priceInCents: 1999,
        subtotalInCents: 1999,
      );
      expect(item.priceDecimal, 19.99);
    });

    test('formattedPrice shows EUR symbol', () {
      const item = CartItem(
        productId: 'p1',
        productName: 'Test',
        quantity: 1,
        priceInCents: 500,
        subtotalInCents: 500,
      );
      expect(item.formattedPrice, '€5.00');
    });

    test('subtotalDecimal and formattedSubtotal', () {
      const item = CartItem(
        productId: 'p1',
        productName: 'Test',
        quantity: 3,
        priceInCents: 1000,
        subtotalInCents: 3000,
      );
      expect(item.subtotalDecimal, 30.0);
      expect(item.formattedSubtotal, '€30.00');
    });

    test('equality is based on productId', () {
      const a = CartItem(
        productId: 'p1',
        productName: 'A',
        quantity: 1,
        priceInCents: 100,
        subtotalInCents: 100,
      );
      const b = CartItem(
        productId: 'p1',
        productName: 'B',
        quantity: 5,
        priceInCents: 200,
        subtotalInCents: 1000,
      );
      expect(a, equals(b));
    });
  });

  // ── Cart entity ──────────────────────────────────────────
  group('Cart entity', () {
    test('default cart is empty', () {
      const cart = Cart();
      expect(cart.isEmpty, isTrue);
      expect(cart.isNotEmpty, isFalse);
      expect(cart.totalInCents, 0);
      expect(cart.formattedTotal, '€0.00');
    });

    test('totalDecimal and formattedTotal', () {
      const cart = Cart(totalInCents: 5500);
      expect(cart.totalDecimal, 55.0);
      expect(cart.formattedTotal, '€55.00');
    });

    test('isNotEmpty when items present', () {
      const cart = Cart(items: [
        CartItem(
          productId: 'p1',
          productName: 'A',
          quantity: 1,
          priceInCents: 100,
          subtotalInCents: 100,
        ),
      ]);
      expect(cart.isEmpty, isFalse);
      expect(cart.isNotEmpty, isTrue);
    });
  });

  // ── CartItemModel JSON ───────────────────────────────────
  group('CartItemModel.fromJson', () {
    test('parses all fields correctly', () {
      final model = CartItemModel.fromJson(
          testCartJson['items'] is List
              ? (testCartJson['items'] as List).first as Map<String, dynamic>
              : {});
      expect(model.productId, 'p1');
      expect(model.productName, 'Widget A');
      expect(model.quantity, 2);
      expect(model.priceInCents, 1500);
      expect(model.subtotalInCents, 3000);
    });

    test('handles missing fields with defaults', () {
      final model = CartItemModel.fromJson({});
      expect(model.productId, '');
      expect(model.productName, '');
      expect(model.quantity, 0);
      expect(model.priceInCents, 0);
      expect(model.subtotalInCents, 0);
    });
  });

  // ── CartModel JSON ───────────────────────────────────────
  group('CartModel.fromJson', () {
    test('parses full cart response', () {
      final model = CartModel.fromJson(testCartJson);
      expect(model.id, '42');
      expect(model.items, hasLength(2));
      expect(model.totalInCents, 5500);
      expect(model.currency, 'EUR');
      expect(model.itemCount, 2);
      expect(model.totalQuantity, 3);
      expect(model.updatedAt, isNotNull);
    });

    test('parses empty cart', () {
      final model = CartModel.fromJson({
        'id': null,
        'items': [],
        'totalInCents': 0,
        'currency': 'EUR',
        'itemCount': 0,
        'totalQuantity': 0,
      });
      expect(model.items, isEmpty);
      expect(model.totalInCents, 0);
    });

    test('handles missing optional fields', () {
      final model = CartModel.fromJson({'items': []});
      expect(model.id, isNull);
      expect(model.currency, 'EUR');
      expect(model.updatedAt, isNull);
    });
  });

  // ── CartRepository API calls ─────────────────────────────
  group('CartRepository', () {
    test('getCart sends GET to cart endpoint', () async {
      when(() => mockDio.get(ApiEndpoints.cart)).thenAnswer(
        (_) async => Response(
          data: testCartJson,
          statusCode: 200,
          requestOptions: RequestOptions(path: ApiEndpoints.cart),
        ),
      );

      final cart = await repository.getCart();
      expect(cart.items, hasLength(2));
      expect(cart.totalInCents, 5500);
      verify(() => mockDio.get(ApiEndpoints.cart)).called(1);
    });

    test('addItem sends POST with productId and quantity', () async {
      when(() => mockDio.post(
            ApiEndpoints.cartItems,
            data: {'productId': 'p1', 'quantity': 2},
          )).thenAnswer(
        (_) async => Response(
          data: testCartJson,
          statusCode: 200,
          requestOptions: RequestOptions(path: ApiEndpoints.cartItems),
        ),
      );

      final cart = await repository.addItem(productId: 'p1', quantity: 2);
      expect(cart.totalInCents, 5500);
      verify(() => mockDio.post(
            ApiEndpoints.cartItems,
            data: {'productId': 'p1', 'quantity': 2},
          )).called(1);
    });

    test('updateQuantity sends PUT with new quantity', () async {
      final url = ApiEndpoints.cartUpdateItem('p1');
      when(() => mockDio.put(url, data: {'quantity': 5})).thenAnswer(
        (_) async => Response(
          data: testCartJson,
          statusCode: 200,
          requestOptions: RequestOptions(path: url),
        ),
      );

      final cart =
          await repository.updateQuantity(productId: 'p1', quantity: 5);
      expect(cart.totalInCents, 5500);
      verify(() => mockDio.put(url, data: {'quantity': 5})).called(1);
    });

    test('removeItem sends DELETE to item endpoint', () async {
      final url = ApiEndpoints.cartRemoveItem('p2');
      when(() => mockDio.delete(url)).thenAnswer(
        (_) async => Response(
          data: testCartJson,
          statusCode: 200,
          requestOptions: RequestOptions(path: url),
        ),
      );

      final cart = await repository.removeItem('p2');
      expect(cart.totalInCents, 5500);
      verify(() => mockDio.delete(url)).called(1);
    });

    test('clearCart sends DELETE to cart endpoint', () async {
      when(() => mockDio.delete(ApiEndpoints.cart)).thenAnswer(
        (_) async => Response(
          data: null,
          statusCode: 204,
          requestOptions: RequestOptions(path: ApiEndpoints.cart),
        ),
      );

      await repository.clearCart();
      verify(() => mockDio.delete(ApiEndpoints.cart)).called(1);
    });
  });
}
