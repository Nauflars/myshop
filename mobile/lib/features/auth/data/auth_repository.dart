import 'package:dio/dio.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../domain/entities/user.dart';
import 'models/user_model.dart';

/// Repository handling authentication API calls.
class AuthRepository {
  final ApiClient _apiClient;

  AuthRepository(this._apiClient);

  /// Login with email and password via json_login.
  Future<User> login({
    required String email,
    required String password,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.login,
      data: {
        'email': email,
        'password': password,
      },
    );

    return UserModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Register a new user account.
  Future<User> register({
    required String name,
    required String email,
    required String password,
    String role = 'ROLE_CUSTOMER',
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.register,
      data: {
        'name': name,
        'email': email,
        'password': password,
        'role': role,
      },
    );

    return UserModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Get current authenticated user.
  Future<User> getMe() async {
    final response = await _apiClient.dio.get(ApiEndpoints.me);
    return UserModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Logout the current user.
  Future<void> logout() async {
    try {
      await _apiClient.dio.get(ApiEndpoints.logout);
    } on DioException {
      // Logout may redirect — ignore errors
    }
    await _apiClient.clearCookies();
  }
}
