/// Product entity representing a catalog item.
class Product {
  final String id;
  final String name;
  final String? nameEs;
  final String description;
  final int priceInCents;
  final String currency;
  final int stock;
  final String category;
  final bool inStock;
  final bool lowStock;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Product({
    required this.id,
    required this.name,
    this.nameEs,
    required this.description,
    required this.priceInCents,
    this.currency = 'EUR',
    required this.stock,
    required this.category,
    required this.inStock,
    required this.lowStock,
    required this.createdAt,
    required this.updatedAt,
  });

  /// Get price as decimal (e.g. 19.99).
  double get priceDecimal => priceInCents / 100.0;

  /// Get formatted price string (e.g. "€19.99").
  String get formattedPrice {
    final symbol = currency == 'EUR' ? '€' : '\$';
    return '$symbol${priceDecimal.toStringAsFixed(2)}';
  }

  /// Get display name (prefer Spanish if available).
  String displayName({String locale = 'es'}) {
    if (locale == 'es' && nameEs != null && nameEs!.isNotEmpty) {
      return nameEs!;
    }
    return name;
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Product && runtimeType == other.runtimeType && id == other.id;

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() => 'Product(id: $id, name: $name, price: $formattedPrice)';
}
