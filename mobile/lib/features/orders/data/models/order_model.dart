import '../../domain/entities/order.dart';

/// Data model for ShippingAddress with JSON serialization.
class ShippingAddressModel extends ShippingAddress {
  const ShippingAddressModel({
    required super.street,
    required super.city,
    super.state,
    required super.zip,
    required super.country,
  });

  factory ShippingAddressModel.fromJson(Map<String, dynamic> json) {
    return ShippingAddressModel(
      street: json['street'] as String? ?? '',
      city: json['city'] as String? ?? '',
      state: json['state'] as String? ?? '',
      zip: json['zip'] as String? ?? '',
      country: json['country'] as String? ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'street': street,
      'city': city,
      if (state.isNotEmpty) 'state': state,
      'zip': zip,
      'country': country,
    };
  }
}

/// Data model for OrderItem with JSON serialization.
class OrderItemModel extends OrderItem {
  const OrderItemModel({
    required super.productId,
    required super.productName,
    required super.quantity,
    required super.priceInCents,
    required super.subtotalInCents,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> json) {
    return OrderItemModel(
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

/// Data model for Order with JSON serialization.
class OrderModel extends Order {
  const OrderModel({
    required super.id,
    required super.orderNumber,
    required super.userId,
    required super.items,
    required super.totalInCents,
    super.currency,
    required super.status,
    super.shippingAddress,
    required super.createdAt,
    required super.updatedAt,
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List<dynamic>? ?? [])
        .map((item) => OrderItemModel.fromJson(item as Map<String, dynamic>))
        .toList();

    ShippingAddressModel? shippingAddress;
    if (json['shippingAddress'] != null &&
        json['shippingAddress'] is Map<String, dynamic>) {
      shippingAddress = ShippingAddressModel.fromJson(
          json['shippingAddress'] as Map<String, dynamic>);
    }

    return OrderModel(
      id: (json['id'] ?? '').toString(),
      orderNumber: json['orderNumber'] as String? ?? '',
      userId: (json['userId'] ?? '').toString(),
      items: items,
      totalInCents: (json['totalInCents'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? 'EUR',
      status: OrderStatus.fromString(json['status'] as String? ?? 'PENDING'),
      shippingAddress: shippingAddress,
      createdAt: json['createdAt'] != null
          ? DateTime.parse(json['createdAt'] as String)
          : DateTime.now(),
      updatedAt: json['updatedAt'] != null
          ? DateTime.parse(json['updatedAt'] as String)
          : DateTime.now(),
    );
  }
}
