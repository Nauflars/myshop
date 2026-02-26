/// Cart item entity.
class CartItem {
  final String productId;
  final String productName;
  final int quantity;
  final int priceInCents;
  final int subtotalInCents;

  const CartItem({
    required this.productId,
    required this.productName,
    required this.quantity,
    required this.priceInCents,
    required this.subtotalInCents,
  });

  /// Price as decimal.
  double get priceDecimal => priceInCents / 100.0;

  /// Subtotal as decimal.
  double get subtotalDecimal => subtotalInCents / 100.0;

  /// Formatted price.
  String get formattedPrice => '€${priceDecimal.toStringAsFixed(2)}';

  /// Formatted subtotal.
  String get formattedSubtotal => '€${subtotalDecimal.toStringAsFixed(2)}';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is CartItem &&
          runtimeType == other.runtimeType &&
          productId == other.productId;

  @override
  int get hashCode => productId.hashCode;
}

/// Cart entity with items and totals.
class Cart {
  final String? id;
  final List<CartItem> items;
  final int totalInCents;
  final String currency;
  final int itemCount;
  final int totalQuantity;
  final DateTime? updatedAt;

  const Cart({
    this.id,
    this.items = const [],
    this.totalInCents = 0,
    this.currency = 'EUR',
    this.itemCount = 0,
    this.totalQuantity = 0,
    this.updatedAt,
  });

  /// Total as decimal.
  double get totalDecimal => totalInCents / 100.0;

  /// Formatted total.
  String get formattedTotal => '€${totalDecimal.toStringAsFixed(2)}';

  /// Whether the cart is empty.
  bool get isEmpty => items.isEmpty;

  /// Whether the cart has items.
  bool get isNotEmpty => items.isNotEmpty;
}
