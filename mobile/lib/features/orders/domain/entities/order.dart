/// Order status enum matching backend values.
enum OrderStatus {
  pending('PENDING'),
  confirmed('CONFIRMED'),
  shipped('SHIPPED'),
  delivered('DELIVERED'),
  cancelled('CANCELLED');

  final String value;
  const OrderStatus(this.value);

  static OrderStatus fromString(String value) {
    return OrderStatus.values.firstWhere(
      (s) => s.value == value.toUpperCase(),
      orElse: () => OrderStatus.pending,
    );
  }

  /// Human-readable label.
  String get label {
    switch (this) {
      case OrderStatus.pending:
        return 'Pending';
      case OrderStatus.confirmed:
        return 'Confirmed';
      case OrderStatus.shipped:
        return 'Shipped';
      case OrderStatus.delivered:
        return 'Delivered';
      case OrderStatus.cancelled:
        return 'Cancelled';
    }
  }

  /// Whether the order is still active (not cancelled/delivered).
  bool get isActive =>
      this != OrderStatus.cancelled && this != OrderStatus.delivered;
}

/// Shipping address value object.
class ShippingAddress {
  final String street;
  final String city;
  final String state;
  final String zip;
  final String country;

  const ShippingAddress({
    required this.street,
    required this.city,
    this.state = '',
    required this.zip,
    required this.country,
  });

  /// Formatted multi-line address.
  String get formatted {
    final parts = <String>[street];
    if (state.isNotEmpty) {
      parts.add('$city, $state $zip');
    } else {
      parts.add('$city $zip');
    }
    parts.add(country);
    return parts.join('\n');
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is ShippingAddress &&
          street == other.street &&
          city == other.city &&
          zip == other.zip &&
          country == other.country;

  @override
  int get hashCode => Object.hash(street, city, zip, country);
}

/// Order item entity.
class OrderItem {
  final String productId;
  final String productName;
  final int quantity;
  final int priceInCents;
  final int subtotalInCents;

  const OrderItem({
    required this.productId,
    required this.productName,
    required this.quantity,
    required this.priceInCents,
    required this.subtotalInCents,
  });

  double get priceDecimal => priceInCents / 100.0;
  double get subtotalDecimal => subtotalInCents / 100.0;
  String get formattedPrice => '€${priceDecimal.toStringAsFixed(2)}';
  String get formattedSubtotal => '€${subtotalDecimal.toStringAsFixed(2)}';
}

/// Order entity.
class Order {
  final String id;
  final String orderNumber;
  final String userId;
  final List<OrderItem> items;
  final int totalInCents;
  final String currency;
  final OrderStatus status;
  final ShippingAddress? shippingAddress;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Order({
    required this.id,
    required this.orderNumber,
    required this.userId,
    required this.items,
    required this.totalInCents,
    this.currency = 'EUR',
    required this.status,
    this.shippingAddress,
    required this.createdAt,
    required this.updatedAt,
  });

  double get totalDecimal => totalInCents / 100.0;
  String get formattedTotal => '€${totalDecimal.toStringAsFixed(2)}';
  int get itemCount => items.length;
  int get totalQuantity =>
      items.fold(0, (sum, item) => sum + item.quantity);
}
