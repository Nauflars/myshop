import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../../../cart/presentation/providers/cart_provider.dart';
import '../providers/product_provider.dart';

/// Product detail screen with localized name, description, price, stock, add-to-cart.
class ProductDetailScreen extends ConsumerStatefulWidget {
  final String productId;

  const ProductDetailScreen({super.key, required this.productId});

  @override
  ConsumerState<ProductDetailScreen> createState() =>
      _ProductDetailScreenState();
}

class _ProductDetailScreenState extends ConsumerState<ProductDetailScreen> {
  int _quantity = 1;

  @override
  Widget build(BuildContext context) {
    final productAsync = ref.watch(productDetailProvider(widget.productId));
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AppRoutes.products);
            }
          },
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_cart_outlined),
            onPressed: () => context.go(AppRoutes.cart),
          ),
        ],
      ),
      body: productAsync.when(
        data: (product) => SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Product image placeholder
              AspectRatio(
                aspectRatio: 1.2,
                child: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [AppTheme.backgroundColor, Color(0xFFE8E8E8)],
                    ),
                  ),
                  child: Center(
                    child: Icon(
                      Icons.shopping_bag_outlined,
                      size: 80,
                      color: AppTheme.greyColor.withAlpha(100),
                    ),
                  ),
                ),
              ),

              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Category chip
                    if (product.category.isNotEmpty)
                      Chip(
                        label: Text(product.category),
                        visualDensity: VisualDensity.compact,
                      ),

                    const SizedBox(height: 8),

                    // Product name
                    Text(
                      product.displayName(),
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    // English name if different
                    if (product.nameEs != null &&
                        product.nameEs!.isNotEmpty &&
                        product.nameEs != product.name)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          product.name,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      ),

                    const SizedBox(height: 16),

                    // Price — orange like web (--second-color gradient)
                    Text(
                      product.formattedPrice,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppTheme.secondaryColor,
                      ),
                    ),

                    const SizedBox(height: 8),

                    // Stock status — web green #4CAF50 / red #F44336
                    Row(
                      children: [
                        Icon(
                          product.inStock
                              ? Icons.check_circle
                              : Icons.cancel,
                          size: 20,
                          color: product.inStock
                              ? AppTheme.inStockColor
                              : AppTheme.outOfStockColor,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          product.inStock
                              ? (product.lowStock
                                  ? 'Low Stock (${product.stock} left)'
                                  : 'In Stock (${product.stock} available)')
                              : 'Out of Stock',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: product.inStock
                                ? (product.lowStock
                                    ? AppTheme.warningColor
                                    : AppTheme.inStockColor)
                                : AppTheme.outOfStockColor,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),

                    // Description
                    Text(
                      'Description',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      product.description.isNotEmpty
                          ? product.description
                          : 'No description available.',
                      style: theme.textTheme.bodyLarge,
                    ),

                    const SizedBox(height: 24),

                    // Quantity selector
                    if (product.inStock) ...[
                      Text(
                        'Quantity',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          IconButton(
                            style: IconButton.styleFrom(
                              backgroundColor: AppTheme.primaryColor,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            onPressed: _quantity > 1
                                ? () => setState(() => _quantity--)
                                : null,
                            icon: const Icon(Icons.remove),
                          ),
                          Padding(
                            padding:
                                const EdgeInsets.symmetric(horizontal: 16),
                            child: Text(
                              '$_quantity',
                              style: theme.textTheme.titleLarge
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                          ),
                          IconButton(
                            style: IconButton.styleFrom(
                              backgroundColor: AppTheme.primaryColor,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            onPressed: _quantity < product.stock
                                ? () => setState(() => _quantity++)
                                : null,
                            icon: const Icon(Icons.add),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),

                      // Add to cart button
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: ElevatedButton.icon(
                          onPressed: () async {
                            await ref.read(cartProvider.notifier).addItem(
                                  productId: product.id,
                                  quantity: _quantity,
                                );
                            if (context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(
                                      '$_quantity × ${product.displayName()} added to cart'),
                                  action: SnackBarAction(
                                    label: 'View Cart',
                                    onPressed: () =>
                                        context.go(AppRoutes.cart),
                                  ),
                                ),
                              );
                            }
                          },
                          icon: const Icon(Icons.add_shopping_cart),
                          label: Text(
                              'Add to Cart • ${product.formattedPrice}'),
                        ),
                      ),
                    ],

                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ],
          ),
        ),
        loading: () => const LoadingWidget(),
        error: (error, _) => AppErrorWidget(
          message: error.toString(),
          onRetry: () =>
              ref.invalidate(productDetailProvider(widget.productId)),
        ),
      ),
    );
  }
}
