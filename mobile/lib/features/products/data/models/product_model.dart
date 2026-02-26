import '../../domain/entities/product.dart';

/// Data model for Product that handles JSON serialization.
class ProductModel extends Product {
  const ProductModel({
    required super.id,
    required super.name,
    super.nameEs,
    required super.description,
    required super.priceInCents,
    super.currency,
    required super.stock,
    required super.category,
    required super.inStock,
    required super.lowStock,
    required super.createdAt,
    required super.updatedAt,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    final price = json['price'] as Map<String, dynamic>? ?? {};

    return ProductModel(
      id: (json['id'] ?? '').toString(),
      name: json['name'] as String? ?? '',
      nameEs: json['nameEs'] as String?,
      description: json['description'] as String? ?? '',
      priceInCents: (price['amount'] as num?)?.toInt() ?? 0,
      currency: price['currency'] as String? ?? 'EUR',
      stock: (json['stock'] as num?)?.toInt() ?? 0,
      category: json['category'] as String? ?? '',
      inStock: json['inStock'] as bool? ?? false,
      lowStock: json['lowStock'] as bool? ?? false,
      createdAt: json['createdAt'] != null
          ? DateTime.parse(json['createdAt'] as String)
          : DateTime.now(),
      updatedAt: json['updatedAt'] != null
          ? DateTime.parse(json['updatedAt'] as String)
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'nameEs': nameEs,
      'description': description,
      'price': {
        'amount': priceInCents,
        'currency': currency,
      },
      'stock': stock,
      'category': category,
      'inStock': inStock,
      'lowStock': lowStock,
      'createdAt': createdAt.toIso8601String(),
      'updatedAt': updatedAt.toIso8601String(),
    };
  }
}
