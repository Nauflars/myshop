import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../../domain/entities/order.dart';
import '../providers/order_provider.dart';

/// Order list screen with order cards showing number, date, total, status badge.
class OrderListScreen extends ConsumerStatefulWidget {
  const OrderListScreen({super.key});

  @override
  ConsumerState<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends ConsumerState<OrderListScreen> {
  @override
  void initState() {
    super.initState();
    // Load orders on first build
    Future.microtask(() => ref.read(orderListProvider.notifier).loadOrders());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(orderListProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Orders'),
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
      ),
      body: _buildBody(state, theme),
    );
  }

  Widget _buildBody(OrderListState state, ThemeData theme) {
    if (state.isLoading && state.orders.isEmpty) {
      return const LoadingWidget();
    }

    if (state.error != null && state.orders.isEmpty) {
      return AppErrorWidget(
        message: state.error!,
        onRetry: () => ref.read(orderListProvider.notifier).refresh(),
      );
    }

    if (state.orders.isEmpty) {
      return EmptyStateWidget(
        icon: Icons.receipt_long_outlined,
        message: 'No orders yet',
        actionLabel: 'Start Shopping',
        onAction: () => context.go(AppRoutes.products),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(orderListProvider.notifier).refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: state.orders.length,
        itemBuilder: (context, index) =>
            _OrderCard(order: state.orders[index]),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final Order order;

  const _OrderCard({required this.order});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => context.go(
          AppRoutes.orderDetail
              .replaceFirst(':orderNumber', order.orderNumber),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      order.orderNumber,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  _StatusBadge(status: order.status),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    _formatDate(order.createdAt),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  Text(
                    order.formattedTotal,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppTheme.secondaryColor,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${order.itemCount} item${order.itemCount == 1 ? '' : 's'}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}

class _StatusBadge extends StatelessWidget {
  final OrderStatus status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    // Use web CSS gradient badges for status
    final (Color bg, Color fg) = switch (status) {
      OrderStatus.pending => (AppTheme.warningBg, AppTheme.warningDark),
      OrderStatus.confirmed => (const Color(0xFFE3F2FD), const Color(0xFF1565C0)),
      OrderStatus.shipped => (const Color(0xFFF3E5F5), const Color(0xFF7B1FA2)),
      OrderStatus.delivered => (AppTheme.successBg, AppTheme.successDark),
      OrderStatus.cancelled => (AppTheme.errorBg, AppTheme.errorDark),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppTheme.radiusPill),
      ),
      child: Text(
        status.label,
        style: TextStyle(
          color: fg,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
