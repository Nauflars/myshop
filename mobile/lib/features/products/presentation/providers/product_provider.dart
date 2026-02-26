import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/error/error_handler.dart';
import '../../../../core/network/api_client.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../data/product_repository.dart';
import '../../domain/entities/product.dart';

// -- Providers --

final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ProductRepository(ref.watch(apiClientProvider));
});

/// State for the product catalog.
class ProductCatalogState {
  final List<Product> products;
  final int total;
  final int currentPage;
  final bool hasMore;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;
  final String? selectedCategory;
  final double? minPrice;
  final double? maxPrice;

  const ProductCatalogState({
    this.products = const [],
    this.total = 0,
    this.currentPage = 1,
    this.hasMore = false,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
    this.selectedCategory,
    this.minPrice,
    this.maxPrice,
  });

  ProductCatalogState copyWith({
    List<Product>? products,
    int? total,
    int? currentPage,
    bool? hasMore,
    bool? isLoading,
    bool? isLoadingMore,
    String? error,
    String? selectedCategory,
    double? minPrice,
    double? maxPrice,
    bool clearError = false,
    bool clearCategory = false,
    bool clearMinPrice = false,
    bool clearMaxPrice = false,
  }) {
    return ProductCatalogState(
      products: products ?? this.products,
      total: total ?? this.total,
      currentPage: currentPage ?? this.currentPage,
      hasMore: hasMore ?? this.hasMore,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      error: clearError ? null : (error ?? this.error),
      selectedCategory:
          clearCategory ? null : (selectedCategory ?? this.selectedCategory),
      minPrice: clearMinPrice ? null : (minPrice ?? this.minPrice),
      maxPrice: clearMaxPrice ? null : (maxPrice ?? this.maxPrice),
    );
  }
}

/// Product catalog notifier — manages product list, pagination, filters.
class ProductCatalogNotifier extends StateNotifier<ProductCatalogState> {
  final ProductRepository _repository;

  ProductCatalogNotifier(this._repository)
      : super(const ProductCatalogState()) {
    loadProducts();
  }

  /// Load first page of products.
  Future<void> loadProducts() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _repository.getProducts(
        page: 1,
        category: state.selectedCategory,
        minPrice: state.minPrice,
        maxPrice: state.maxPrice,
      );
      state = state.copyWith(
        products: result.items,
        total: result.total,
        currentPage: 1,
        hasMore: result.hasMore,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Load next page (infinite scroll).
  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore) return;

    state = state.copyWith(isLoadingMore: true);
    try {
      final nextPage = state.currentPage + 1;
      final result = await _repository.getProducts(
        page: nextPage,
        category: state.selectedCategory,
        minPrice: state.minPrice,
        maxPrice: state.maxPrice,
      );
      state = state.copyWith(
        products: [...state.products, ...result.items],
        total: result.total,
        currentPage: nextPage,
        hasMore: result.hasMore,
        isLoadingMore: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoadingMore: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Apply category filter and reload.
  void setCategory(String? category) {
    state = state.copyWith(
      selectedCategory: category,
      clearCategory: category == null,
    );
    loadProducts();
  }

  /// Apply price range and reload.
  void setPriceRange({double? min, double? max}) {
    state = state.copyWith(
      minPrice: min,
      maxPrice: max,
      clearMinPrice: min == null,
      clearMaxPrice: max == null,
    );
    loadProducts();
  }

  /// Refresh products (pull-to-refresh).
  Future<void> refresh() => loadProducts();
}

final productCatalogProvider =
    StateNotifierProvider<ProductCatalogNotifier, ProductCatalogState>((ref) {
  return ProductCatalogNotifier(ref.watch(productRepositoryProvider));
});

/// State for search.
class SearchState {
  final List<Product> results;
  final int total;
  final String mode;
  final String? fallbackReason;
  final bool isLoading;
  final String? error;
  final String query;

  const SearchState({
    this.results = const [],
    this.total = 0,
    this.mode = 'semantic',
    this.fallbackReason,
    this.isLoading = false,
    this.error,
    this.query = '',
  });

  SearchState copyWith({
    List<Product>? results,
    int? total,
    String? mode,
    String? fallbackReason,
    bool? isLoading,
    String? error,
    String? query,
    bool clearError = false,
    bool clearFallback = false,
  }) {
    return SearchState(
      results: results ?? this.results,
      total: total ?? this.total,
      mode: mode ?? this.mode,
      fallbackReason:
          clearFallback ? null : (fallbackReason ?? this.fallbackReason),
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      query: query ?? this.query,
    );
  }
}

/// Search notifier — manages search with debounce.
class SearchNotifier extends StateNotifier<SearchState> {
  final ProductRepository _repository;
  Timer? _debounceTimer;

  SearchNotifier(this._repository) : super(const SearchState());

  /// Search with debounce (500ms).
  void search(String query) {
    state = state.copyWith(query: query);
    _debounceTimer?.cancel();

    if (query.trim().isEmpty) {
      state = const SearchState();
      return;
    }

    _debounceTimer = Timer(const Duration(milliseconds: 500), () {
      _executeSearch(query);
    });
  }

  /// Change search mode (semantic/keyword).
  void setMode(String mode) {
    state = state.copyWith(mode: mode);
    if (state.query.isNotEmpty) {
      _executeSearch(state.query);
    }
  }

  Future<void> _executeSearch(String query) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _repository.search(
        query: query,
        mode: state.mode,
      );
      state = state.copyWith(
        results: result.products,
        total: result.total,
        mode: result.mode,
        fallbackReason: result.fallbackReason,
        isLoading: false,
        clearFallback: result.fallbackReason == null,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Clear search.
  void clear() {
    _debounceTimer?.cancel();
    state = const SearchState();
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    super.dispose();
  }
}

final searchProvider =
    StateNotifierProvider<SearchNotifier, SearchState>((ref) {
  return SearchNotifier(ref.watch(productRepositoryProvider));
});

/// Single product detail provider.
final productDetailProvider =
    FutureProvider.family<Product, String>((ref, id) async {
  final repo = ref.watch(productRepositoryProvider);
  return repo.getById(id);
});

/// Recommendations provider.
final recommendationsProvider = FutureProvider<List<Product>>((ref) async {
  final repo = ref.watch(productRepositoryProvider);
  return repo.getRecommendations(limit: 10);
});
