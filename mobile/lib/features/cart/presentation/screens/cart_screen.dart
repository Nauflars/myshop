import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../providers/cart_provider.dart';
import '../widgets/cart_item_widget.dart';

/// Cart screen with item list, grand total, empty state, proceed-to-checkout button.
class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cartState = ref.watch(cartProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Shopping Cart'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AppRoutes.home);
            }
          },
        ),
        actions: [
          if (cartState.cart.isNotEmpty)
            TextButton(
              onPressed: () => _showClearConfirmation(context, ref),
              child: const Text('Clear'),
            ),
        ],
      ),
      body: _buildBody(context, ref, cartState, theme),
      bottomNavigationBar: cartState.cart.isNotEmpty
          ? _buildCheckoutBar(context, cartState, theme)
          : null,
    );
  }

  Widget _buildBody(
    BuildContext context,
    WidgetRef ref,
    CartState cartState,
    ThemeData theme,
  ) {
    if (cartState.isLoading && cartState.cart.isEmpty) {
      return const LoadingWidget();
    }

    if (cartState.error != null && cartState.cart.isEmpty) {
      return AppErrorWidget(
        message: cartState.error!,
        onRetry: () => ref.read(cartProvider.notifier).refresh(),
      );
    }

    if (cartState.cart.isEmpty) {
      return EmptyStateWidget(
        icon: Icons.shopping_cart_outlined,
        message: 'Your cart is empty',
        actionLabel: 'Browse Products',
        onAction: () => context.go(AppRoutes.products),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(cartProvider.notifier).refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.only(top: 8, bottom: 100),
        itemCount: cartState.cart.items.length,
        itemBuilder: (context, index) {
          final item = cartState.cart.items[index];
          return CartItemWidget(
            item: item,
            onQuantityChanged: (newQuantity) {
              ref.read(cartProvider.notifier).updateQuantity(
                    productId: item.productId,
                    quantity: newQuantity,
                  );
            },
            onRemove: () {
              ref.read(cartProvider.notifier).removeItem(item.productId);
            },
          );
        },
      ),
    );
  }

  Widget _buildCheckoutBar(
    BuildContext context,
    CartState cartState,
    ThemeData theme,
  ) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        border: const Border(
          top: BorderSide(color: AppTheme.borderColor),
        ),
        boxShadow: AppTheme.shadowSm,
      ),
      child: SafeArea(
        child: Row(
          children: [
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Total',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  Text(
                    cartState.cart.formattedTotal,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: AppTheme.secondaryColor,
                    ),
                  ),
                  Text(
                    '${cartState.cart.totalQuantity} item${cartState.cart.totalQuantity == 1 ? '' : 's'}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: SizedBox(
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: () => context.go(AppRoutes.checkout),
                  icon: const Icon(Icons.payment),
                  label: const Text('Checkout'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showClearConfirmation(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Clear Cart'),
        content:
            const Text('Are you sure you want to remove all items from your cart?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              ref.read(cartProvider.notifier).clearCart();
              Navigator.of(context).pop();
            },
            child: const Text('Clear'),
          ),
        ],
      ),
    );
  }
}
