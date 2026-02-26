import '../../domain/entities/cart.dart';

/// Data model for CartItem with JSON serialization.
class CartItemModel extends CartItem {
  const CartItemModel({
    required super.productId,
    required super.productName,
    required super.quantity,
    required super.priceInCents,
    required super.subtotalInCents,
  });

  factory CartItemModel.fromJson(Map<String, dynamic> json) {
    return CartItemModel(
      productId: (json['productId'] ?? '').toString(),
      productName: json['productName'] as String? ?? '',
      quantity: (json['quantity'] as num?)?.toInt() ?? 0,
      priceInCents: (json['priceInCents'] as num?)?.toInt() ?? 0,
      subtotalInCents: (json['subtotalInCents'] as num?)?.toInt() ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'productId': productId,
      'productName': productName,
      'quantity': quantity,
      'priceInCents': priceInCents,
      'subtotalInCents': subtotalInCents,
    };
  }
}

/// Data model for Cart with JSON serialization.
class CartModel extends Cart {
  const CartModel({
    super.id,
    super.items,
    super.totalInCents,
    super.currency,
    super.itemCount,
    super.totalQuantity,
    super.updatedAt,
  });

  factory CartModel.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List<dynamic>? ?? [])
        .map((item) => CartItemModel.fromJson(item as Map<String, dynamic>))
        .toList();

    return CartModel(
      id: json['id']?.toString(),
      items: items,
      totalInCents: (json['totalInCents'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? 'EUR',
      itemCount: (json['itemCount'] as num?)?.toInt() ?? items.length,
      totalQuantity: (json['totalQuantity'] as num?)?.toInt() ?? 0,
      updatedAt: json['updatedAt'] != null
          ? DateTime.parse(json['updatedAt'] as String)
          : null,
    );
  }
}
