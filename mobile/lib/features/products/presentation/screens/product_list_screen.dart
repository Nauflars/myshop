import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../providers/product_provider.dart';
import '../widgets/product_card.dart';

/// Product list screen with category chips, price range, infinite-scroll grid.
class ProductListScreen extends ConsumerStatefulWidget {
  const ProductListScreen({super.key});

  @override
  ConsumerState<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends ConsumerState<ProductListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(productCatalogProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final catalogState = ref.watch(productCatalogProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Products'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go(AppRoutes.home),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: () => context.go(AppRoutes.search),
          ),
        ],
      ),
      body: Column(
        children: [
          // Category filter chips
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 8),
              children: [
                _FilterChip(
                  label: 'All',
                  selected: catalogState.selectedCategory == null,
                  onSelected: () {
                    ref.read(productCatalogProvider.notifier).setCategory(null);
                  },
                ),
                for (final category in AppConstants.categories)
                  _FilterChip(
                    label: category,
                    selected: catalogState.selectedCategory == category,
                    onSelected: () {
                      ref
                          .read(productCatalogProvider.notifier)
                          .setCategory(category);
                    },
                  ),
              ],
            ),
          ),

          // Results count
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              children: [
                Text(
                  '${catalogState.total} product${catalogState.total == 1 ? '' : 's'}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),

          // Product grid
          Expanded(
            child: _buildBody(catalogState, theme),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(ProductCatalogState state, ThemeData theme) {
    if (state.isLoading && state.products.isEmpty) {
      return const LoadingWidget();
    }

    if (state.error != null && state.products.isEmpty) {
      return AppErrorWidget(
        message: state.error!,
        onRetry: () => ref.read(productCatalogProvider.notifier).refresh(),
      );
    }

    if (state.products.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.inventory_2_outlined,
        message: 'No products found',
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(productCatalogProvider.notifier).refresh(),
      child: GridView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(8),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          childAspectRatio: 0.65,
          crossAxisSpacing: 8,
          mainAxisSpacing: 8,
        ),
        itemCount: state.products.length + (state.isLoadingMore ? 2 : 0),
        itemBuilder: (context, index) {
          if (index >= state.products.length) {
            return const Center(child: InlineLoadingWidget());
          }

          final product = state.products[index];
          return ProductCard(
            product: product,
            onTap: () => context.go('/products/${product.id}'),
          );
        },
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onSelected;

  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: FilterChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onSelected(),
      ),
    );
  }
}
