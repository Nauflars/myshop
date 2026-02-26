/// User entity representing an authenticated user.
class User {
  final String id;
  final String name;
  final String email;
  final List<String> roles;

  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
  });

  bool get isAdmin => roles.contains('ROLE_ADMIN');
  bool get isSeller =>
      roles.contains('ROLE_SELLER') || roles.contains('ROLE_ADMIN');
  bool get isCustomer => roles.contains('ROLE_CUSTOMER');

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is User && runtimeType == other.runtimeType && id == other.id;

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() => 'User(id: $id, name: $name, email: $email)';
}
