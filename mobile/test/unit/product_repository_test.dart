import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/core/network/api_endpoints.dart';
import 'package:mobile/features/products/data/models/product_model.dart';
import 'package:mobile/features/products/data/product_repository.dart';
import 'package:mobile/features/products/domain/entities/product.dart';

class MockApiClient extends Mock implements ApiClient {}

class MockDio extends Mock implements Dio {}

void main() {
  late MockApiClient mockApiClient;
  late MockDio mockDio;
  late ProductRepository repository;

  final testProductJson = {
    'id': '1',
    'name': 'Test Product',
    'nameEs': 'Producto de Prueba',
    'description': 'A test product',
    'price': {'amount': 1999, 'currency': 'EUR'},
    'stock': 10,
    'category': 'Electronics',
    'inStock': true,
    'lowStock': false,
    'createdAt': '2025-01-01T00:00:00+00:00',
    'updatedAt': '2025-01-01T00:00:00+00:00',
  };

  setUp(() {
    mockApiClient = MockApiClient();
    mockDio = MockDio();
    when(() => mockApiClient.dio).thenReturn(mockDio);
    repository = ProductRepository(mockApiClient);
  });

  group('Product entity', () {
    test('priceDecimal converts cents to decimal', () {
      final product = ProductModel.fromJson(testProductJson);
      expect(product.priceDecimal, 19.99);
    });

    test('formattedPrice shows EUR symbol', () {
      final product = ProductModel.fromJson(testProductJson);
      expect(product.formattedPrice, '€19.99');
    });

    test('displayName prefers Spanish name when locale is es', () {
      final product = ProductModel.fromJson(testProductJson);
      expect(product.displayName(locale: 'es'), 'Producto de Prueba');
      expect(product.displayName(locale: 'en'), 'Test Product');
    });

    test('displayName falls back to name when nameEs is null', () {
      final json = Map<String, dynamic>.from(testProductJson);
      json['nameEs'] = null;
      final product = ProductModel.fromJson(json);
      expect(product.displayName(locale: 'es'), 'Test Product');
    });

    test('equality based on id', () {
      final p1 = ProductModel.fromJson(testProductJson);
      final json2 = Map<String, dynamic>.from(testProductJson);
      json2['name'] = 'Different Name';
      final p2 = ProductModel.fromJson(json2);
      final json3 = Map<String, dynamic>.from(testProductJson);
      json3['id'] = '2';
      final p3 = ProductModel.fromJson(json3);

      expect(p1, equals(p2));
      expect(p1, isNot(equals(p3)));
    });
  });

  group('ProductModel', () {
    test('fromJson parses all fields correctly', () {
      final model = ProductModel.fromJson(testProductJson);
      expect(model.id, '1');
      expect(model.name, 'Test Product');
      expect(model.nameEs, 'Producto de Prueba');
      expect(model.description, 'A test product');
      expect(model.priceInCents, 1999);
      expect(model.currency, 'EUR');
      expect(model.stock, 10);
      expect(model.category, 'Electronics');
      expect(model.inStock, true);
      expect(model.lowStock, false);
    });

    test('fromJson handles missing optional fields', () {
      final model = ProductModel.fromJson({
        'id': '1',
        'name': 'Basic',
        'description': '',
        'price': {'amount': 500},
        'stock': 0,
        'category': '',
        'inStock': false,
        'lowStock': false,
        'createdAt': '2025-01-01T00:00:00+00:00',
        'updatedAt': '2025-01-01T00:00:00+00:00',
      });
      expect(model.nameEs, isNull);
      expect(model.currency, 'EUR'); // default
    });

    test('toJson serializes correctly', () {
      final model = ProductModel.fromJson(testProductJson);
      final json = model.toJson();
      expect(json['id'], '1');
      expect(json['name'], 'Test Product');
      expect(json['price']['amount'], 1999);
      expect(json['price']['currency'], 'EUR');
    });
  });

  group('ProductRepository', () {
    group('getProducts', () {
      test('fetches paginated products', () async {
        when(() => mockDio.get(
              ApiEndpoints.products,
              queryParameters: {
                'page': 1,
                'limit': 20,
              },
            )).thenAnswer(
          (_) async => Response(
            data: {
              'items': [testProductJson],
              'total': 1,
              'page': 1,
              'limit': 20,
              'hasMore': false,
            },
            statusCode: 200,
            requestOptions: RequestOptions(path: ApiEndpoints.products),
          ),
        );

        final result = await repository.getProducts();
        expect(result.items.length, 1);
        expect(result.total, 1);
        expect(result.hasMore, false);
        expect(result.items.first.name, 'Test Product');
      });

      test('sends category and price filters', () async {
        when(() => mockDio.get(
              ApiEndpoints.products,
              queryParameters: {
                'page': 1,
                'limit': 20,
                'category': 'Electronics',
                'minPrice': 10.0,
                'maxPrice': 50.0,
              },
            )).thenAnswer(
          (_) async => Response(
            data: {
              'items': [],
              'total': 0,
              'page': 1,
              'limit': 20,
              'hasMore': false,
            },
            statusCode: 200,
            requestOptions: RequestOptions(path: ApiEndpoints.products),
          ),
        );

        final result = await repository.getProducts(
          category: 'Electronics',
          minPrice: 10.0,
          maxPrice: 50.0,
        );
        expect(result.items, isEmpty);
      });
    });

    group('search', () {
      test('sends search query with mode', () async {
        when(() => mockDio.get(
              ApiEndpoints.productSearch,
              queryParameters: {
                'q': 'laptop',
                'mode': 'semantic',
                'limit': 10,
                'offset': 0,
                'min_similarity': 0.3,
              },
            )).thenAnswer(
          (_) async => Response(
            data: {
              'products': [testProductJson],
              'total': 1,
              'mode': 'semantic',
            },
            statusCode: 200,
            requestOptions: RequestOptions(path: ApiEndpoints.productSearch),
          ),
        );

        final result = await repository.search(query: 'laptop');
        expect(result.products.length, 1);
        expect(result.mode, 'semantic');
        expect(result.fallbackReason, isNull);
      });
    });

    group('getById', () {
      test('fetches single product', () async {
        when(() => mockDio.get('${ApiEndpoints.products}/42')).thenAnswer(
          (_) async => Response(
            data: testProductJson,
            statusCode: 200,
            requestOptions: RequestOptions(path: '${ApiEndpoints.products}/42'),
          ),
        );

        final product = await repository.getById('42');
        expect(product.id, '1');
        expect(product.name, 'Test Product');
      });

      test('throws on product not found', () async {
        when(() => mockDio.get('${ApiEndpoints.products}/999')).thenThrow(
          DioException(
            type: DioExceptionType.badResponse,
            response: Response(
              statusCode: 404,
              data: {'error': 'Product not found'},
              requestOptions:
                  RequestOptions(path: '${ApiEndpoints.products}/999'),
            ),
            requestOptions:
                RequestOptions(path: '${ApiEndpoints.products}/999'),
          ),
        );

        expect(
          () => repository.getById('999'),
          throwsA(isA<DioException>()),
        );
      });
    });

    group('getRecommendations', () {
      test('returns list of products', () async {
        when(() => mockDio.get(
              ApiEndpoints.products,
              queryParameters: {
                'page': 1,
                'limit': 10,
              },
            )).thenAnswer(
          (_) async => Response(
            data: {
              'items': [testProductJson, testProductJson],
              'total': 2,
              'page': 1,
              'limit': 10,
              'hasMore': false,
            },
            statusCode: 200,
            requestOptions: RequestOptions(path: ApiEndpoints.products),
          ),
        );

        final products = await repository.getRecommendations(limit: 10);
        expect(products.length, 2);
      });
    });
  });
}
