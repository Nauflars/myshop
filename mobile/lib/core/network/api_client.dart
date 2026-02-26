import 'package:dio/dio.dart';
import 'package:dio_cookie_manager/dio_cookie_manager.dart';
import 'package:cookie_jar/cookie_jar.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'api_interceptors.dart';

/// Centralized Dio HTTP client for all API communication.
///
/// Configures base URL, JSON content type, cookie management,
/// and error interceptors.
///
/// Base URL resolution:
///  - Web (Chrome/Edge): http://localhost  (browser can reach host directly)
///  - Android emulator:  http://10.0.2.2   (special alias → host loopback)
///  - iOS simulator:     http://localhost
///  - Physical device:   set via [baseUrl] constructor parameter
class ApiClient {
  /// Resolve the correct base URL for the current platform.
  static String get _defaultBaseUrl {
    if (kIsWeb) {
      // Running in browser — connect directly to localhost
      return 'http://localhost:8080';
    }
    // Android emulator sees host as 10.0.2.2, iOS simulator uses localhost
    return 'http://10.0.2.2:8080';
  }

  late final Dio dio;
  late final CookieJar cookieJar;

  ApiClient({String? baseUrl}) {
    cookieJar = CookieJar();

    dio = Dio(
      BaseOptions(
        baseUrl: baseUrl ?? _defaultBaseUrl,
        contentType: 'application/json',
        responseType: ResponseType.json,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        sendTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
        },
        validateStatus: (status) => status != null && status < 500,
      ),
    );

    dio.interceptors.addAll([
      // CookieManager uses dart:io and cannot run in web environments.
      if (!kIsWeb) CookieManager(cookieJar),
      ErrorInterceptor(),
      LogInterceptor(
        requestBody: true,
        responseBody: true,
        error: true,
        logPrint: (obj) {
          // ignore: avoid_print
          assert(() {
            print(obj);
            return true;
          }());
        },
      ),
    ]);
  }

  /// Clear all stored cookies (used on logout).
  Future<void> clearCookies() async {
    await cookieJar.deleteAll();
  }
}
