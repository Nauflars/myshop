import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/core/network/api_endpoints.dart';
import 'package:mobile/features/auth/data/auth_repository.dart';
import 'package:mobile/features/auth/data/models/user_model.dart';
import 'package:mobile/features/auth/domain/entities/user.dart';

// -- Mocks --
class MockApiClient extends Mock implements ApiClient {}

class MockDio extends Mock implements Dio {}

class MockCookieJar extends Mock {
  Future<void> deleteAll() async {}
}

void main() {
  late MockApiClient mockApiClient;
  late MockDio mockDio;
  late AuthRepository repository;

  final testUserJson = {
    'id': '1',
    'name': 'John Doe',
    'email': 'john@example.com',
    'roles': ['ROLE_CUSTOMER'],
  };

  setUp(() {
    mockApiClient = MockApiClient();
    mockDio = MockDio();
    when(() => mockApiClient.dio).thenReturn(mockDio);
    repository = AuthRepository(mockApiClient);
  });

  setUpAll(() {
    registerFallbackValue(Uri());
  });

  group('AuthRepository', () {
    group('login', () {
      test('sends POST to login endpoint with email and password', () async {
        when(() => mockDio.post(
              ApiEndpoints.login,
              data: {'email': 'john@example.com', 'password': 'secret'},
            )).thenAnswer(
          (_) async => Response(
            data: testUserJson,
            statusCode: 200,
            requestOptions: RequestOptions(path: ApiEndpoints.login),
          ),
        );

        final user = await repository.login(
          email: 'john@example.com',
          password: 'secret',
        );

        expect(user, isA<User>());
        expect(user.id, '1');
        expect(user.name, 'John Doe');
        expect(user.email, 'john@example.com');
        expect(user.roles, ['ROLE_CUSTOMER']);
      });

      test('throws DioException on invalid credentials', () async {
        when(() => mockDio.post(
              ApiEndpoints.login,
              data: any(named: 'data'),
            )).thenThrow(
          DioException(
            type: DioExceptionType.badResponse,
            response: Response(
              statusCode: 401,
              data: {'error': 'Invalid credentials'},
              requestOptions: RequestOptions(path: ApiEndpoints.login),
            ),
            requestOptions: RequestOptions(path: ApiEndpoints.login),
          ),
        );

        expect(
          () => repository.login(email: 'bad@example.com', password: 'wrong'),
          throwsA(isA<DioException>()),
        );
      });
    });

    group('register', () {
      test('sends POST to register endpoint with user data', () async {
        when(() => mockDio.post(
              ApiEndpoints.register,
              data: {
                'name': 'Jane Doe',
                'email': 'jane@example.com',
                'password': 'password123',
                'role': 'ROLE_CUSTOMER',
              },
            )).thenAnswer(
          (_) async => Response(
            data: {
              'id': '2',
              'name': 'Jane Doe',
              'email': 'jane@example.com',
              'roles': ['ROLE_CUSTOMER'],
            },
            statusCode: 201,
            requestOptions: RequestOptions(path: ApiEndpoints.register),
          ),
        );

        final user = await repository.register(
          name: 'Jane Doe',
          email: 'jane@example.com',
          password: 'password123',
        );

        expect(user.id, '2');
        expect(user.name, 'Jane Doe');
        expect(user.email, 'jane@example.com');
      });

      test('allows custom role on register', () async {
        when(() => mockDio.post(
              ApiEndpoints.register,
              data: {
                'name': 'Admin',
                'email': 'admin@example.com',
                'password': 'admin123',
                'role': 'ROLE_ADMIN',
              },
            )).thenAnswer(
          (_) async => Response(
            data: {
              'id': '3',
              'name': 'Admin',
              'email': 'admin@example.com',
              'roles': ['ROLE_ADMIN'],
            },
            statusCode: 201,
            requestOptions: RequestOptions(path: ApiEndpoints.register),
          ),
        );

        final user = await repository.register(
          name: 'Admin',
          email: 'admin@example.com',
          password: 'admin123',
          role: 'ROLE_ADMIN',
        );

        expect(user.roles, ['ROLE_ADMIN']);
      });
    });

    group('getMe', () {
      test('sends GET to me endpoint and returns user', () async {
        when(() => mockDio.get(ApiEndpoints.me)).thenAnswer(
          (_) async => Response(
            data: testUserJson,
            statusCode: 200,
            requestOptions: RequestOptions(path: ApiEndpoints.me),
          ),
        );

        final user = await repository.getMe();

        expect(user.id, '1');
        expect(user.name, 'John Doe');
        verify(() => mockDio.get(ApiEndpoints.me)).called(1);
      });

      test('throws on unauthenticated access', () async {
        when(() => mockDio.get(ApiEndpoints.me)).thenThrow(
          DioException(
            type: DioExceptionType.badResponse,
            response: Response(
              statusCode: 401,
              requestOptions: RequestOptions(path: ApiEndpoints.me),
            ),
            requestOptions: RequestOptions(path: ApiEndpoints.me),
          ),
        );

        expect(
          () => repository.getMe(),
          throwsA(isA<DioException>()),
        );
      });
    });

    group('logout', () {
      test('sends GET to logout endpoint and clears cookies', () async {
        when(() => mockDio.get(ApiEndpoints.logout)).thenAnswer(
          (_) async => Response(
            data: null,
            statusCode: 302,
            requestOptions: RequestOptions(path: ApiEndpoints.logout),
          ),
        );
        when(() => mockApiClient.clearCookies()).thenAnswer((_) async {});

        await repository.logout();

        verify(() => mockDio.get(ApiEndpoints.logout)).called(1);
        verify(() => mockApiClient.clearCookies()).called(1);
      });

      test('clears cookies even when logout request fails', () async {
        when(() => mockDio.get(ApiEndpoints.logout)).thenThrow(
          DioException(
            type: DioExceptionType.badResponse,
            requestOptions: RequestOptions(path: ApiEndpoints.logout),
          ),
        );
        when(() => mockApiClient.clearCookies()).thenAnswer((_) async {});

        await repository.logout();

        verify(() => mockApiClient.clearCookies()).called(1);
      });
    });
  });

  group('User entity', () {
    test('isAdmin returns true for ROLE_ADMIN', () {
      const user = User(
        id: '1',
        name: 'Admin',
        email: 'admin@test.com',
        roles: ['ROLE_ADMIN'],
      );
      expect(user.isAdmin, true);
      expect(user.isSeller, true); // Admin implies seller
      expect(user.isCustomer, false);
    });

    test('isSeller returns true for ROLE_SELLER', () {
      const user = User(
        id: '2',
        name: 'Seller',
        email: 'seller@test.com',
        roles: ['ROLE_SELLER'],
      );
      expect(user.isSeller, true);
      expect(user.isAdmin, false);
    });

    test('isCustomer returns true for ROLE_CUSTOMER', () {
      const user = User(
        id: '3',
        name: 'Customer',
        email: 'customer@test.com',
        roles: ['ROLE_CUSTOMER'],
      );
      expect(user.isCustomer, true);
      expect(user.isAdmin, false);
      expect(user.isSeller, false);
    });

    test('equality based on id', () {
      const user1 = User(
        id: '1',
        name: 'User',
        email: 'a@b.com',
        roles: ['ROLE_CUSTOMER'],
      );
      const user2 = User(
        id: '1',
        name: 'Different Name',
        email: 'c@d.com',
        roles: ['ROLE_ADMIN'],
      );
      const user3 = User(
        id: '2',
        name: 'User',
        email: 'a@b.com',
        roles: ['ROLE_CUSTOMER'],
      );

      expect(user1, equals(user2));
      expect(user1, isNot(equals(user3)));
    });

    test('toString includes id, name, email', () {
      const user = User(
        id: '1',
        name: 'John',
        email: 'john@test.com',
        roles: [],
      );
      expect(user.toString(), contains('id: 1'));
      expect(user.toString(), contains('name: John'));
      expect(user.toString(), contains('email: john@test.com'));
    });
  });

  group('UserModel', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': '42',
        'name': 'Test User',
        'email': 'test@example.com',
        'roles': ['ROLE_CUSTOMER', 'ROLE_SELLER'],
      };

      final model =
          UserModel.fromJson(json);

      expect(model.id, '42');
      expect(model.name, 'Test User');
      expect(model.email, 'test@example.com');
      expect(model.roles, ['ROLE_CUSTOMER', 'ROLE_SELLER']);
    });

    test('toJson serializes correctly', () {
      final model = UserModel(
        id: '42',
        name: 'Test User',
        email: 'test@example.com',
        roles: ['ROLE_CUSTOMER'],
      );

      final json = model.toJson();

      expect(json['id'], '42');
      expect(json['name'], 'Test User');
      expect(json['email'], 'test@example.com');
      expect(json['roles'], ['ROLE_CUSTOMER']);
    });

    test('fromJson handles dynamic role list', () {
      final json = {
        'id': '1',
        'name': 'Admin',
        'email': 'admin@test.com',
        'roles': <dynamic>['ROLE_ADMIN', 'ROLE_CUSTOMER'],
      };

      final model =
          UserModel.fromJson(json);
      expect(model.roles, isA<List<String>>());
      expect(model.roles.length, 2);
    });
  });
}
