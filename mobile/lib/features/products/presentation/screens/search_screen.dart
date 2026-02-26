import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../providers/product_provider.dart';
import '../widgets/product_card.dart';

/// Search screen with text input, semantic/keyword mode toggle, debounced search.
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final searchState = ref.watch(searchProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _searchController,
          autofocus: true,
          style: const TextStyle(color: Colors.white),
          cursorColor: AppTheme.secondaryColor,
          decoration: InputDecoration(
            hintText: 'Search products...',
            hintStyle: TextStyle(color: Colors.white.withAlpha(180)),
            border: InputBorder.none,
            enabledBorder: InputBorder.none,
            focusedBorder: InputBorder.none,
            filled: false,
            suffixIcon: _searchController.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear, color: Colors.white),
                    onPressed: () {
                      _searchController.clear();
                      ref.read(searchProvider.notifier).clear();
                      setState(() {});
                    },
                  )
                : null,
          ),
          onChanged: (value) {
            ref.read(searchProvider.notifier).search(value);
            setState(() {});
          },
        ),
      ),
      body: Column(
        children: [
          // Search mode toggle
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: 'semantic',
                      label: Text('Smart'),
                      icon: Icon(Icons.auto_awesome, size: 16),
                    ),
                    ButtonSegment(
                      value: 'keyword',
                      label: Text('Keyword'),
                      icon: Icon(Icons.text_fields, size: 16),
                    ),
                  ],
                  selected: {searchState.mode},
                  onSelectionChanged: (selected) {
                    ref
                        .read(searchProvider.notifier)
                        .setMode(selected.first);
                  },
                ),
                const Spacer(),
                if (searchState.total > 0)
                  Text(
                    '${searchState.total} result${searchState.total == 1 ? '' : 's'}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
              ],
            ),
          ),

          // Fallback notice — web warning alert style
          if (searchState.fallbackReason != null)
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.warningBg,
                borderRadius: BorderRadius.circular(AppTheme.radiusLg),
                border: const Border(
                  left: BorderSide(color: AppTheme.warningColor, width: 4),
                ),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline,
                      size: 16, color: AppTheme.warningDark),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      searchState.fallbackReason!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppTheme.warningDark,
                      ),
                    ),
                  ),
                ],
              ),
            ),

          // Results
          Expanded(
            child: _buildResults(searchState, theme),
          ),
        ],
      ),
    );
  }

  Widget _buildResults(SearchState state, ThemeData theme) {
    if (state.query.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.search,
        message: 'Search for products',
      );
    }

    if (state.isLoading) {
      return const LoadingWidget();
    }

    if (state.error != null) {
      return AppErrorWidget(
        message: state.error!,
        onRetry: () =>
            ref.read(searchProvider.notifier).search(state.query),
      );
    }

    if (state.results.isEmpty) {
      return EmptyStateWidget(
        icon: Icons.search_off,
        message: 'No results for "${state.query}"',
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(8),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 0.65,
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
      ),
      itemCount: state.results.length,
      itemBuilder: (context, index) {
        final product = state.results[index];
        return ProductCard(
          product: product,
          onTap: () => context.go('/products/${product.id}'),
        );
      },
    );
  }
}
