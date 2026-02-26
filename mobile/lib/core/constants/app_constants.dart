/// App-wide constants for categories, order statuses, roles, etc.
class AppConstants {
  AppConstants._();

  // App Info
  static const String appName = 'MyShop';
  static const String appVersion = '1.0.0';

  // Product Categories
  static const List<String> categories = [
    'Electronics',
    'Books',
    'Clothing',
    'Home & Garden',
    'Sports',
    'Toys',
    'Automotive',
    'Health & Beauty',
    'Food & Beverages',
    'Music',
    'Movies',
    'Other',
  ];

  // Order Statuses
  static const String orderPending = 'PENDING';
  static const String orderConfirmed = 'CONFIRMED';
  static const String orderShipped = 'SHIPPED';
  static const String orderDelivered = 'DELIVERED';
  static const String orderCancelled = 'CANCELLED';

  static const List<String> orderStatuses = [
    orderPending,
    orderConfirmed,
    orderShipped,
    orderDelivered,
    orderCancelled,
  ];

  static String orderStatusLabel(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
        return 'Pending';
      case 'CONFIRMED':
        return 'Confirmed';
      case 'SHIPPED':
        return 'Shipped';
      case 'DELIVERED':
        return 'Delivered';
      case 'CANCELLED':
        return 'Cancelled';
      default:
        return status;
    }
  }

  // User Roles
  static const String roleCustomer = 'ROLE_CUSTOMER';
  static const String roleSeller = 'ROLE_SELLER';
  static const String roleAdmin = 'ROLE_ADMIN';

  // Search
  static const int searchDebounceMs = 500;
  static const int defaultPageSize = 20;
  static const int maxPageSize = 50;

  // Cart
  static const int maxCartItemQuantity = 99;
  static const int minCartItemQuantity = 1;

  // Currency
  static const String defaultCurrency = 'USD';
  static const String currencySymbol = '\$';

  /// Format price from cents to display string.
  static String formatPrice(int priceInCents, {String currency = 'USD'}) {
    final amount = priceInCents / 100;
    return '\$${amount.toStringAsFixed(2)}';
  }
}
