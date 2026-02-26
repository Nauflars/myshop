import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../domain/entities/product.dart';
import 'models/product_model.dart';

/// Paginated response for product listings.
class PaginatedProducts {
  final List<Product> items;
  final int total;
  final int page;
  final int limit;
  final bool hasMore;

  const PaginatedProducts({
    required this.items,
    required this.total,
    required this.page,
    required this.limit,
    required this.hasMore,
  });
}

/// Search result with extra metadata.
class SearchResult {
  final List<Product> products;
  final int total;
  final String mode;
  final String? fallbackReason;

  const SearchResult({
    required this.products,
    required this.total,
    required this.mode,
    this.fallbackReason,
  });
}

/// Repository handling product API calls.
class ProductRepository {
  final ApiClient _apiClient;

  ProductRepository(this._apiClient);

  /// Get paginated product list with optional filters.
  Future<PaginatedProducts> getProducts({
    int page = 1,
    int limit = 20,
    String? category,
    String? query,
    double? minPrice,
    double? maxPrice,
  }) async {
    final queryParams = <String, dynamic>{
      'page': page,
      'limit': limit,
    };
    if (category != null && category.isNotEmpty) {
      queryParams['category'] = category;
    }
    if (query != null && query.isNotEmpty) {
      queryParams['q'] = query;
    }
    if (minPrice != null) {
      queryParams['minPrice'] = minPrice;
    }
    if (maxPrice != null) {
      queryParams['maxPrice'] = maxPrice;
    }

    final response = await _apiClient.dio.get(
      ApiEndpoints.products,
      queryParameters: queryParams,
    );

    final data = response.data as Map<String, dynamic>;
    final items = (data['items'] as List<dynamic>)
        .map((json) => ProductModel.fromJson(json as Map<String, dynamic>))
        .toList();

    return PaginatedProducts(
      items: items,
      total: (data['total'] as num?)?.toInt() ?? items.length,
      page: (data['page'] as num?)?.toInt() ?? page,
      limit: (data['limit'] as num?)?.toInt() ?? limit,
      hasMore: data['hasMore'] as bool? ?? false,
    );
  }

  /// Search products using semantic or keyword mode.
  Future<SearchResult> search({
    required String query,
    String mode = 'semantic',
    int limit = 10,
    int offset = 0,
    double minSimilarity = 0.3,
    String? category,
  }) async {
    final queryParams = <String, dynamic>{
      'q': query,
      'mode': mode,
      'limit': limit,
      'offset': offset,
      'min_similarity': minSimilarity,
    };
    if (category != null && category.isNotEmpty) {
      queryParams['category'] = category;
    }

    final response = await _apiClient.dio.get(
      ApiEndpoints.productSearch,
      queryParameters: queryParams,
    );

    final data = response.data as Map<String, dynamic>;
    final products = (data['products'] as List<dynamic>? ?? [])
        .map((json) => ProductModel.fromJson(json as Map<String, dynamic>))
        .toList();

    return SearchResult(
      products: products,
      total: (data['total'] as num?)?.toInt() ?? products.length,
      mode: data['mode'] as String? ?? mode,
      fallbackReason: data['fallback_reason'] as String?,
    );
  }

  /// Get single product by ID.
  Future<Product> getById(String id) async {
    final response = await _apiClient.dio.get('${ApiEndpoints.products}/$id');
    return ProductModel.fromJson(response.data as Map<String, dynamic>);
  }

  /// Get personalized recommendations from the dedicated API endpoint.
  Future<List<Product>> getRecommendations({int limit = 10}) async {
    final response = await _apiClient.dio.get(
      ApiEndpoints.recommendations,
      queryParameters: {'limit': limit},
    );

    final data = response.data as Map<String, dynamic>;
    final items = (data['items'] as List<dynamic>)
        .map((json) => ProductModel.fromJson(json as Map<String, dynamic>))
        .toList();

    return items;
  }
}
