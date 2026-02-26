import 'package:dio/dio.dart';
import '../error/error_handler.dart';

/// Error interceptor that transforms Dio errors into user-friendly messages.
class ErrorInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final appError = AppErrorHandler.fromDioException(err);

    handler.next(
      DioException(
        requestOptions: err.requestOptions,
        response: err.response,
        type: err.type,
        error: appError,
        message: appError.message,
      ),
    );
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    // Handle API-level errors returned in response body
    if (response.statusCode != null &&
        response.statusCode! >= 400 &&
        response.statusCode! < 500) {
      final data = response.data;
      if (data is Map<String, dynamic> && data.containsKey('error')) {
        handler.reject(
          DioException(
            requestOptions: response.requestOptions,
            response: response,
            type: DioExceptionType.badResponse,
            message: data['error'] as String?,
          ),
        );
        return;
      }
    }
    handler.next(response);
  }
}

/// Connectivity interceptor stub.
/// Simplified to avoid connectivity_plus dependency issues.
/// All requests are passed through directly.
class ConnectivityInterceptor extends Interceptor {
  ConnectivityInterceptor();

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    // Pass through — connectivity check disabled for now.
    handler.next(options);
  }
}
