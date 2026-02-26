import '../../domain/entities/user.dart';

/// Data model for User that handles JSON serialization.
class UserModel extends User {
  const UserModel({
    required super.id,
    required super.name,
    required super.email,
    required super.roles,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as String,
      name: json['name'] as String,
      email: json['email'] as String,
      roles: (json['roles'] as List<dynamic>).cast<String>(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'roles': roles,
    };
  }
}
