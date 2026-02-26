import 'package:dio/dio.dart';

/// Types of app errors for categorization.
enum AppErrorType {
  network,
  server,
  authentication,
  validation,
  notFound,
  forbidden,
  unknown,
}

/// Centralized error model with user-friendly messages.
class AppError {
  final String message;
  final AppErrorType type;
  final int? statusCode;
  final dynamic originalError;

  const AppError({
    required this.message,
    required this.type,
    this.statusCode,
    this.originalError,
  });

  /// Create an [AppError] from a Dio exception.
  factory AppError.fromDioException(DioException exception) {
    return AppErrorHandler.fromDioException(exception);
  }

  bool get isNetworkError => type == AppErrorType.network;
  bool get isAuthError => type == AppErrorType.authentication;
  bool get isServerError => type == AppErrorType.server;

  @override
  String toString() => 'AppError($type): $message';
}

/// Centralized error handler that transforms exceptions into user-friendly messages.
class AppErrorHandler {
  AppErrorHandler._();

  /// Transform a [DioException] into a user-friendly [AppError].
  static AppError fromDioException(DioException exception) {
    switch (exception.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const AppError(
          message:
              'Connection timed out. Please check your internet connection and try again.',
          type: AppErrorType.network,
        );

      case DioExceptionType.connectionError:
        return const AppError(
          message:
              'No internet connection. Please check your network settings.',
          type: AppErrorType.network,
        );

      case DioExceptionType.badResponse:
        return _handleResponseError(exception.response);

      case DioExceptionType.cancel:
        return const AppError(
          message: 'Request was cancelled.',
          type: AppErrorType.unknown,
        );

      case DioExceptionType.badCertificate:
        return const AppError(
          message: 'Security certificate error. Please contact support.',
          type: AppErrorType.network,
        );

      case DioExceptionType.unknown:
      default:
        if (exception.error is AppError) {
          return exception.error as AppError;
        }
        return const AppError(
          message: 'An unexpected error occurred. Please try again.',
          type: AppErrorType.unknown,
        );
    }
  }

  /// Handle HTTP response errors with appropriate user messages
  static AppError _handleResponseError(Response? response) {
    final statusCode = response?.statusCode ?? 0;
    final data = response?.data;
    String? serverMessage;

    if (data is Map<String, dynamic>) {
      serverMessage = data['error'] as String?;
    }

    switch (statusCode) {
      case 400:
        return AppError(
          message: serverMessage ?? 'Invalid request. Please check your input.',
          type: AppErrorType.validation,
          statusCode: statusCode,
        );
      case 401:
        return AppError(
          message: serverMessage ?? 'Session expired. Please log in again.',
          type: AppErrorType.authentication,
          statusCode: statusCode,
        );
      case 403:
        return AppError(
          message:
              serverMessage ?? 'You do not have permission to perform this action.',
          type: AppErrorType.forbidden,
          statusCode: statusCode,
        );
      case 404:
        return AppError(
          message: serverMessage ?? 'The requested resource was not found.',
          type: AppErrorType.notFound,
          statusCode: statusCode,
        );
      case 422:
        return AppError(
          message: serverMessage ?? 'Validation error. Please check your input.',
          type: AppErrorType.validation,
          statusCode: statusCode,
        );
      case 429:
        return const AppError(
          message: 'Too many requests. Please wait a moment and try again.',
          type: AppErrorType.server,
          statusCode: 429,
        );
      default:
        if (statusCode >= 500) {
          return AppError(
            message:
                'Server error. Please try again later or contact support.',
            type: AppErrorType.server,
            statusCode: statusCode,
          );
        }
        return AppError(
          message: serverMessage ?? 'An unexpected error occurred.',
          type: AppErrorType.unknown,
          statusCode: statusCode,
        );
    }
  }

  /// Extract user-friendly message from any exception.
  static String getMessage(dynamic error) {
    if (error is AppError) {
      return error.message;
    }
    if (error is DioException) {
      return fromDioException(error).message;
    }
    if (error is Exception) {
      return error.toString().replaceFirst('Exception: ', '');
    }
    return 'An unexpected error occurred.';
  }
}
