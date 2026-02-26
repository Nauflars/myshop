/// Centralized API endpoint constants for all backend routes.
class ApiEndpoints {
  ApiEndpoints._();

  // Authentication
  static const String login = '/api/login';
  static const String register = '/api/users';
  static const String me = '/api/users/me';
  static const String logout = '/logout';

  // Products
  static const String products = '/api/products';
  static const String productSearch = '/api/products/search';
  static String productDetail(String id) => '/api/products/$id';

  // Cart
  static const String cart = '/api/cart';
  static const String cartItems = '/api/cart/items';
  static String cartUpdateItem(String itemId) => '/api/cart/items/$itemId';
  static String cartRemoveItem(String itemId) => '/api/cart/items/$itemId';

  // Orders
  static const String orders = '/api/orders';
  static String orderDetail(String orderNumber) => '/api/orders/$orderNumber';
  static String orderUpdateStatus(String orderNumber) =>
      '/api/orders/$orderNumber/status';

  // Chatbot
  static const String chat = '/api/chat';

  // Recommendations
  static const String recommendations = '/api/products';

  // Admin
  static const String adminDashboard = '/api/admin/dashboard';
  static const String adminProducts = '/api/admin/products';
  static String adminProductDetail(String id) => '/api/admin/products/$id';
  static const String adminUsers = '/api/admin/users';
  static String adminUserDetail(String id) => '/api/admin/users/$id';
  static const String adminUnansweredQuestions =
      '/api/admin/unanswered-questions';
  static String adminQuestionDetail(String id) =>
      '/api/admin/unanswered-questions/$id';
  static const String adminBulkUpdateQuestions =
      '/api/admin/unanswered-questions/bulk-update';
  static const String adminSearchMetrics = '/api/admin/search-metrics';
  static const String adminAssistantChat = '/admin/assistant/chat';

  // Device Token
  static const String deviceToken = '/api/users/me/device-token';
}
