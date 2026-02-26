import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../cart/presentation/providers/cart_provider.dart';
import '../../../orders/domain/entities/order.dart';
import '../../../orders/presentation/providers/order_provider.dart';

/// Checkout screen with order summary, shipping address form, place order.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _streetController = TextEditingController();
  final _cityController = TextEditingController();
  final _zipController = TextEditingController();
  final _countryController = TextEditingController(text: 'Spain');

  @override
  void dispose() {
    _streetController.dispose();
    _cityController.dispose();
    _zipController.dispose();
    _countryController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cartState = ref.watch(cartProvider);
    final checkoutState = ref.watch(checkoutProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AppRoutes.cart);
            }
          },
        ),
      ),
      body: cartState.cart.isEmpty
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.shopping_cart_outlined,
                      size: 64, color: Colors.grey),
                  const SizedBox(height: 16),
                  const Text('Your cart is empty'),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => context.go(AppRoutes.products),
                    child: const Text('Browse Products'),
                  ),
                ],
              ),
            )
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Order Summary
                  Text(
                    'Order Summary',
                    style: theme.textTheme.titleLarge
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        children: [
                          ...cartState.cart.items.map((item) => Padding(
                                padding:
                                    const EdgeInsets.symmetric(vertical: 4),
                                child: Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Text(
                                        '${item.productName} × ${item.quantity}',
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    Text(item.formattedSubtotal),
                                  ],
                                ),
                              )),
                          const Divider(height: 24),
                          Row(
                            mainAxisAlignment:
                                MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Total',
                                style: theme.textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.bold),
                              ),
                              Text(
                                cartState.cart.formattedTotal,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.secondaryColor,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 24),

                  // Shipping Address Form
                  Text(
                    'Shipping Address',
                    style: theme.textTheme.titleLarge
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  Form(
                    key: _formKey,
                    child: Column(
                      children: [
                        TextFormField(
                          controller: _streetController,
                          decoration: const InputDecoration(
                            labelText: 'Street Address',
                            prefixIcon: Icon(Icons.home_outlined),
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Street is required'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              flex: 2,
                              child: TextFormField(
                                controller: _cityController,
                                decoration: const InputDecoration(
                                  labelText: 'City',
                                  prefixIcon:
                                      Icon(Icons.location_city_outlined),
                                ),
                                validator: (v) =>
                                    (v == null || v.trim().isEmpty)
                                        ? 'City is required'
                                        : null,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _zipController,
                                decoration: const InputDecoration(
                                  labelText: 'ZIP Code',
                                ),
                                validator: (v) =>
                                    (v == null || v.trim().isEmpty)
                                        ? 'ZIP required'
                                        : null,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _countryController,
                          decoration: const InputDecoration(
                            labelText: 'Country',
                            prefixIcon: Icon(Icons.public),
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Country is required'
                              : null,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Error message
                  if (checkoutState.error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Text(
                        checkoutState.error!,
                        style: TextStyle(color: theme.colorScheme.error),
                      ),
                    ),

                  // Place Order Button
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: ElevatedButton(
                      onPressed: checkoutState.isPlacingOrder
                          ? null
                          : () => _placeOrder(),
                      child: checkoutState.isPlacingOrder
                          ? const SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2),
                            )
                          : Text(
                              'Place Order • ${cartState.cart.formattedTotal}'),
                    ),
                  ),

                  const SizedBox(height: 32),
                ],
              ),
            ),
    );
  }

  Future<void> _placeOrder() async {
    if (!_formKey.currentState!.validate()) return;

    final address = ShippingAddress(
      street: _streetController.text.trim(),
      city: _cityController.text.trim(),
      zip: _zipController.text.trim(),
      country: _countryController.text.trim(),
    );

    final success = await ref
        .read(checkoutProvider.notifier)
        .placeOrder(shippingAddress: address);

    if (success && mounted) {
      final order = ref.read(checkoutProvider).completedOrder;
      if (order != null) {
        // Refresh cart to empty it
        ref.read(cartProvider.notifier).refresh();
        context.go('/order-confirmation/${order.orderNumber}');
      }
    }
  }
}
