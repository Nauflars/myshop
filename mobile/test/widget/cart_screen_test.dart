import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/features/cart/data/cart_repository.dart';
import 'package:mobile/features/cart/domain/entities/cart.dart';
import 'package:mobile/features/cart/presentation/providers/cart_provider.dart';
import 'package:mobile/features/cart/presentation/screens/cart_screen.dart';

class MockCartRepository extends Mock implements CartRepository {}

void main() {
  late MockCartRepository mockRepo;

  final testCart = const Cart(
    id: '42',
    items: [
      CartItem(
        productId: 'p1',
        productName: 'Widget A',
        quantity: 2,
        priceInCents: 1500,
        subtotalInCents: 3000,
      ),
      CartItem(
        productId: 'p2',
        productName: 'Widget B',
        quantity: 1,
        priceInCents: 2500,
        subtotalInCents: 2500,
      ),
    ],
    totalInCents: 5500,
    currency: 'EUR',
    itemCount: 2,
    totalQuantity: 3,
  );

  const emptyCart = Cart();

  setUp(() {
    mockRepo = MockCartRepository();
  });

  Widget buildSubject({Cart initialCart = const Cart()}) {
    // Mock getCart so CartNotifier.loadCart() returns our test data.
    when(() => mockRepo.getCart()).thenAnswer((_) async => initialCart);

    return ProviderScope(
      overrides: [
        cartRepositoryProvider.overrideWithValue(mockRepo),
      ],
      child: MaterialApp(
        theme: ThemeData(
          splashFactory: InkRipple.splashFactory,
        ),
        home: const CartScreen(),
      ),
    );
  }

  group('CartScreen', () {
    testWidgets('shows empty state when cart is empty', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: emptyCart));
      await tester.pumpAndSettle();

      expect(find.text('Your cart is empty'), findsOneWidget);
      expect(find.text('Browse Products'), findsOneWidget);
    });

    testWidgets('shows cart items when cart has items', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.text('Widget A'), findsOneWidget);
      expect(find.text('Widget B'), findsOneWidget);
    });

    testWidgets('shows total and checkout button for non-empty cart',
        (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.text('€55.00'), findsOneWidget);
      expect(find.text('Checkout'), findsOneWidget);
      expect(find.text('3 items'), findsOneWidget);
    });

    testWidgets('shows clear button in app bar for non-empty cart',
        (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.text('Clear'), findsOneWidget);
    });

    testWidgets('does not show clear button for empty cart', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: emptyCart));
      await tester.pumpAndSettle();

      expect(find.text('Clear'), findsNothing);
    });

    testWidgets('clear button shows confirmation dialog', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Clear'));
      await tester.pumpAndSettle();

      expect(find.text('Clear Cart'), findsOneWidget);
      expect(
        find.text(
            'Are you sure you want to remove all items from your cart?'),
        findsOneWidget,
      );
      expect(find.text('Cancel'), findsOneWidget);
    });

    testWidgets('shows quantity for each item', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      // Quantity should appear in the CartItemWidget
      expect(find.text('2'), findsOneWidget);
      expect(find.text('1'), findsOneWidget);
    });

    testWidgets('shows Shopping Cart title in app bar', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: emptyCart));
      await tester.pumpAndSettle();

      expect(find.text('Shopping Cart'), findsOneWidget);
    });

    testWidgets('does not show checkout bar for empty cart', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: emptyCart));
      await tester.pumpAndSettle();

      expect(find.text('Checkout'), findsNothing);
      expect(find.text('Total'), findsNothing);
    });
  });
}
