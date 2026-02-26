import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../app.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/error_widget.dart';
import '../../../../core/widgets/loading_widget.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../cart/presentation/providers/cart_provider.dart';
import '../providers/product_provider.dart';
import '../widgets/product_card.dart';

/// Home screen with search field, recommendations carousel, and bottom navigation.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final recommendations = ref.watch(recommendationsProvider);
    final cartBadge = ref.watch(cartProvider).badgeCount;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('MyShop'),
        flexibleSpace: Container(
          decoration: const BoxDecoration(gradient: AppTheme.navBarGradient),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: () => context.go(AppRoutes.search),
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.shopping_cart_outlined),
                onPressed: () => context.go(AppRoutes.cart),
              ),
              if (cartBadge > 0)
                Positioned(
                  right: 4,
                  top: 4,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: AppTheme.secondaryColor,
                      shape: BoxShape.circle,
                    ),
                    constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                    child: Text(
                      '$cartBadge',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.account_circle_outlined),
            onSelected: (value) {
              if (value == 'orders') {
                context.go(AppRoutes.orders);
              } else if (value == 'logout') {
                ref.read(authProvider.notifier).logout();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                enabled: false,
                child: Text(
                  authState.user?.name ?? 'User',
                  style: theme.textTheme.titleSmall,
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(
                value: 'orders',
                child: ListTile(
                  leading: Icon(Icons.receipt_long),
                  title: Text('My Orders'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              const PopupMenuItem(
                value: 'logout',
                child: ListTile(
                  leading: Icon(Icons.logout),
                  title: Text('Logout'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(recommendationsProvider);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Hero welcome banner (matches web hero section gradient)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
                decoration: BoxDecoration(
                  gradient: AppTheme.heroGradient,
                  borderRadius: BorderRadius.circular(AppTheme.radiusLg),
                  boxShadow: AppTheme.shadowMd,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Welcome${authState.user != null ? ', ${authState.user!.name}' : ''}!',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Find amazing products today',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                    const SizedBox(height: 16),
                    // Search bar inside hero
                    GestureDetector(
                      onTap: () => context.go(AppRoutes.search),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 12),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.circular(AppTheme.radiusMd),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.search,
                                color: AppTheme.textLight, size: 20),
                            const SizedBox(width: 12),
                            Text(
                              'Search products...',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: AppTheme.textLight,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Recommendations section title with orange accent
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 4,
                          height: 24,
                          decoration: BoxDecoration(
                            color: AppTheme.secondaryColor,
                            borderRadius:
                                BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'Recommended for you',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    TextButton(
                      onPressed: () => context.go(AppRoutes.products),
                      child: const Text('See all'),
                    ),
                  ],
                ),
              ),

              recommendations.when(
                data: (products) {
                  if (products.isEmpty) {
                    return const Padding(
                      padding: EdgeInsets.all(32),
                      child: EmptyStateWidget(
                        icon: Icons.shopping_bag_outlined,
                        message: 'No products yet',
                      ),
                    );
                  }

                  return SizedBox(
                    height: 260,
                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      itemCount: products.length,
                      itemBuilder: (context, index) {
                        return SizedBox(
                          width: 180,
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 4),
                            child: ProductCard(
                              product: products[index],
                              onTap: () => context.go(
                                '/products/${products[index].id}',
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  );
                },
                loading: () => const SizedBox(
                  height: 260,
                  child: LoadingWidget(),
                ),
                error: (error, _) => Padding(
                  padding: const EdgeInsets.all(16),
                  child: AppErrorWidget(
                    message: error.toString(),
                    onRetry: () => ref.invalidate(recommendationsProvider),
                  ),
                ),
              ),

              const SizedBox(height: 16),

              // Browse by category section with accent bar
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Container(
                      width: 4,
                      height: 24,
                      decoration: BoxDecoration(
                        color: AppTheme.secondaryColor,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Browse Categories',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                height: 48,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  children: [
                    _CategoryChip(
                      label: 'All',
                      icon: Icons.grid_view,
                      onTap: () => context.go(AppRoutes.products),
                    ),
                    _CategoryChip(
                      label: 'Electronics',
                      icon: Icons.devices,
                      onTap: () => context.go(AppRoutes.products),
                    ),
                    _CategoryChip(
                      label: 'Clothing',
                      icon: Icons.checkroom,
                      onTap: () => context.go(AppRoutes.products),
                    ),
                    _CategoryChip(
                      label: 'Home',
                      icon: Icons.home_outlined,
                      onTap: () => context.go(AppRoutes.products),
                    ),
                    _CategoryChip(
                      label: 'Sports',
                      icon: Icons.sports_soccer,
                      onTap: () => context.go(AppRoutes.products),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: 0,
        onDestinationSelected: (index) {
          switch (index) {
            case 0:
              break; // Already on home
            case 1:
              context.go(AppRoutes.products);
              break;
            case 2:
              context.go(AppRoutes.cart);
              break;
            case 3:
              context.go(AppRoutes.orders);
              break;
          }
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.store_outlined),
            selectedIcon: Icon(Icons.store),
            label: 'Products',
          ),
          NavigationDestination(
            icon: Icon(Icons.shopping_cart_outlined),
            selectedIcon: Icon(Icons.shopping_cart),
            label: 'Cart',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long),
            label: 'Orders',
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback? onTap;

  const _CategoryChip({
    required this.label,
    required this.icon,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ActionChip(
        avatar: Icon(icon, size: 18),
        label: Text(label),
        onPressed: onTap,
      ),
    );
  }
}
