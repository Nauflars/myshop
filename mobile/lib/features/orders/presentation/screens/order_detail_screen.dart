import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../../domain/entities/order.dart';
import '../providers/order_provider.dart';

/// Order detail screen with line items, totals, shipping address, status.
class OrderDetailScreen extends ConsumerWidget {
  final String orderNumber;

  const OrderDetailScreen({super.key, required this.orderNumber});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(orderDetailProvider(orderNumber));
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(orderNumber),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AppRoutes.orders);
            }
          },
        ),
      ),
      body: orderAsync.when(
        data: (order) => _buildOrderDetail(order, theme),
        loading: () => const LoadingWidget(),
        error: (error, _) => AppErrorWidget(
          message: error.toString(),
          onRetry: () => ref.invalidate(orderDetailProvider(orderNumber)),
        ),
      ),
    );
  }

  Widget _buildOrderDetail(Order order, ThemeData theme) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Status section
          _buildStatusSection(order, theme),
          const SizedBox(height: 24),

          // Items section
          Text(
            'Items',
            style: theme.textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  ...order.items.map((item) => Padding(
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    item.productName,
                                    style: theme.textTheme.bodyMedium
                                        ?.copyWith(
                                            fontWeight: FontWeight.w600),
                                  ),
                                  Text(
                                    '${item.formattedPrice} × ${item.quantity}',
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color:
                                          theme.colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            Text(
                              item.formattedSubtotal,
                              style: theme.textTheme.bodyMedium,
                            ),
                          ],
                        ),
                      )),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Total',
                        style: theme.textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      Text(
                        order.formattedTotal,
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

          // Shipping address
          if (order.shippingAddress != null) ...[
            Text(
              'Shipping Address',
              style: theme.textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Icon(Icons.location_on_outlined),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        order.shippingAddress!.formatted,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
          ],

          // Timestamps
          Text(
            'Timeline',
            style: theme.textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  _TimelineRow(
                    label: 'Ordered',
                    date: order.createdAt,
                    theme: theme,
                  ),
                  if (order.updatedAt != order.createdAt)
                    _TimelineRow(
                      label: 'Last Updated',
                      date: order.updatedAt,
                      theme: theme,
                    ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildStatusSection(Order order, ThemeData theme) {
    // Match web CSS status colors
    final (Color bg, Color fg, IconData icon) = switch (order.status) {
      OrderStatus.pending => (
          AppTheme.warningBg,
          AppTheme.warningDark,
          Icons.hourglass_empty
        ),
      OrderStatus.confirmed => (
          const Color(0xFFE3F2FD),
          const Color(0xFF1565C0),
          Icons.check_circle_outline
        ),
      OrderStatus.shipped => (
          const Color(0xFFF3E5F5),
          const Color(0xFF7B1FA2),
          Icons.local_shipping_outlined
        ),
      OrderStatus.delivered => (
          AppTheme.successBg,
          AppTheme.successDark,
          Icons.done_all
        ),
      OrderStatus.cancelled => (
          AppTheme.errorBg,
          AppTheme.errorDark,
          Icons.cancel_outlined
        ),
    };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: fg, size: 32),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                order.status.label,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: fg,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text(
                '${order.totalQuantity} item${order.totalQuantity == 1 ? '' : 's'} • ${order.formattedTotal}',
                style: theme.textTheme.bodySmall?.copyWith(color: fg),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  final String label;
  final DateTime date;
  final ThemeData theme;

  const _TimelineRow({
    required this.label,
    required this.date,
    required this.theme,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          Text(
            '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}',
            style: theme.textTheme.bodyMedium,
          ),
        ],
      ),
    );
  }
}
