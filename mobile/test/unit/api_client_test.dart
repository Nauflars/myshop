import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/core/network/api_endpoints.dart';
import 'package:mobile/core/error/error_handler.dart';
import 'package:dio/dio.dart';

void main() {
  group('ApiClient', () {
    late ApiClient apiClient;

    setUp(() {
      apiClient = ApiClient(baseUrl: 'http://localhost:8080');
    });

    test('should configure Dio with correct base URL', () {
      expect(apiClient.dio.options.baseUrl, 'http://localhost:8080');
    });

    test('should set JSON content type', () {
      expect(apiClient.dio.options.contentType, 'application/json');
    });

    test('should set JSON response type', () {
      expect(apiClient.dio.options.responseType, ResponseType.json);
    });

    test('should set Accept header', () {
      expect(apiClient.dio.options.headers['Accept'], 'application/json');
    });

    test('should configure timeouts', () {
      expect(apiClient.dio.options.connectTimeout, const Duration(seconds: 15));
      expect(apiClient.dio.options.receiveTimeout, const Duration(seconds: 15));
      expect(apiClient.dio.options.sendTimeout, const Duration(seconds: 15));
    });

    test('should have interceptors configured', () {
      // CookieManager + ErrorInterceptor + LogInterceptor = 3
      expect(apiClient.dio.interceptors.length, greaterThanOrEqualTo(3));
    });

    test('should use default base URL when none provided', () {
      final defaultClient = ApiClient();
      expect(defaultClient.dio.options.baseUrl, 'http://10.0.2.2:8080');
    });
  });

  group('ApiEndpoints', () {
    test('login endpoint', () {
      expect(ApiEndpoints.login, '/api/login');
    });

    test('register endpoint', () {
      expect(ApiEndpoints.register, '/api/users');
    });

    test('me endpoint', () {
      expect(ApiEndpoints.me, '/api/users/me');
    });

    test('products endpoint', () {
      expect(ApiEndpoints.products, '/api/products');
    });

    test('product detail endpoint', () {
      expect(ApiEndpoints.productDetail('123'), '/api/products/123');
    });

    test('cart endpoint', () {
      expect(ApiEndpoints.cart, '/api/cart');
    });

    test('orders endpoint', () {
      expect(ApiEndpoints.orders, '/api/orders');
    });

    test('order detail endpoint', () {
      expect(ApiEndpoints.orderDetail('ORD-123'), '/api/orders/ORD-123');
    });

    test('chat endpoint', () {
      expect(ApiEndpoints.chat, '/api/chat');
    });

    test('admin dashboard endpoint', () {
      expect(ApiEndpoints.adminDashboard, '/api/admin/dashboard');
    });
  });

  group('AppErrorHandler', () {
    test('handles connection timeout', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.connectionTimeout,
          requestOptions: RequestOptions(),
        ),
      );
      expect(error.type, AppErrorType.network);
      expect(error.message, contains('timed out'));
    });

    test('handles connection error', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.connectionError,
          requestOptions: RequestOptions(),
        ),
      );
      expect(error.type, AppErrorType.network);
      expect(error.message, contains('internet'));
    });

    test('handles 401 unauthorized', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.badResponse,
          requestOptions: RequestOptions(),
          response: Response(
            statusCode: 401,
            requestOptions: RequestOptions(),
          ),
        ),
      );
      expect(error.type, AppErrorType.authentication);
      expect(error.statusCode, 401);
    });

    test('handles 403 forbidden', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.badResponse,
          requestOptions: RequestOptions(),
          response: Response(
            statusCode: 403,
            requestOptions: RequestOptions(),
          ),
        ),
      );
      expect(error.type, AppErrorType.forbidden);
    });

    test('handles 404 not found', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.badResponse,
          requestOptions: RequestOptions(),
          response: Response(
            statusCode: 404,
            requestOptions: RequestOptions(),
          ),
        ),
      );
      expect(error.type, AppErrorType.notFound);
    });

    test('handles 500 server error', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.badResponse,
          requestOptions: RequestOptions(),
          response: Response(
            statusCode: 500,
            requestOptions: RequestOptions(),
          ),
        ),
      );
      expect(error.type, AppErrorType.server);
    });

    test('extracts server error message from response body', () {
      final error = AppErrorHandler.fromDioException(
        DioException(
          type: DioExceptionType.badResponse,
          requestOptions: RequestOptions(),
          response: Response(
            statusCode: 400,
            data: {'error': 'Cart is empty'},
            requestOptions: RequestOptions(),
          ),
        ),
      );
      expect(error.message, 'Cart is empty');
      expect(error.type, AppErrorType.validation);
    });

    test('getMessage returns user-friendly message for AppError', () {
      const appError = AppError(
        message: 'Test error',
        type: AppErrorType.unknown,
      );
      expect(AppErrorHandler.getMessage(appError), 'Test error');
    });

    test('isNetworkError returns true for network errors', () {
      const error = AppError(
        message: 'No connection',
        type: AppErrorType.network,
      );
      expect(error.isNetworkError, true);
      expect(error.isAuthError, false);
    });

    test('isAuthError returns true for auth errors', () {
      const error = AppError(
        message: 'Unauthorized',
        type: AppErrorType.authentication,
      );
      expect(error.isAuthError, true);
      expect(error.isNetworkError, false);
    });
  });
}
