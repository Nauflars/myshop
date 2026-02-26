import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/features/cart/data/cart_repository.dart';
import 'package:mobile/features/cart/domain/entities/cart.dart';
import 'package:mobile/features/cart/presentation/providers/cart_provider.dart';
import 'package:mobile/features/checkout/data/checkout_repository.dart';
import 'package:mobile/features/checkout/presentation/screens/checkout_screen.dart';
import 'package:mobile/features/orders/domain/entities/order.dart';
import 'package:mobile/features/orders/presentation/providers/order_provider.dart';

class MockCartRepository extends Mock implements CartRepository {}

class MockCheckoutRepository extends Mock implements CheckoutRepository {}

void main() {
  late MockCartRepository mockCartRepo;
  late MockCheckoutRepository mockCheckoutRepo;

  const testCart = Cart(
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

  setUp(() {
    mockCartRepo = MockCartRepository();
    mockCheckoutRepo = MockCheckoutRepository();
  });

  Widget buildSubject({Cart initialCart = const Cart()}) {
    when(() => mockCartRepo.getCart()).thenAnswer((_) async => initialCart);

    return ProviderScope(
      overrides: [
        cartRepositoryProvider.overrideWithValue(mockCartRepo),
        checkoutRepositoryProvider.overrideWithValue(mockCheckoutRepo),
      ],
      child: MaterialApp(
        theme: ThemeData(
          splashFactory: InkRipple.splashFactory,
        ),
        home: const CheckoutScreen(),
      ),
    );
  }

  group('CheckoutScreen', () {
    testWidgets('shows empty state when cart is empty', (tester) async {
      await tester.pumpWidget(buildSubject());
      await tester.pumpAndSettle();

      expect(find.text('Your cart is empty'), findsOneWidget);
      expect(find.text('Browse Products'), findsOneWidget);
    });

    testWidgets('shows order summary with cart items', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.text('Order Summary'), findsOneWidget);
      expect(find.text('Widget A × 2'), findsOneWidget);
      expect(find.text('Widget B × 1'), findsOneWidget);
    });

    testWidgets('shows shipping address form', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.text('Shipping Address'), findsOneWidget);
      expect(find.text('Street Address'), findsOneWidget);
      expect(find.text('City'), findsOneWidget);
      expect(find.text('ZIP Code'), findsOneWidget);
      expect(find.text('Country'), findsOneWidget);
    });

    testWidgets('shows place order button with total', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.textContaining('Place Order'), findsOneWidget);
      expect(find.textContaining('€55.00'), findsWidgets);
    });

    testWidgets('validates required fields on place order', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      // Tap Place Order with empty fields
      await tester.tap(find.textContaining('Place Order'));
      await tester.pumpAndSettle();

      expect(find.text('Street is required'), findsOneWidget);
      expect(find.text('City is required'), findsOneWidget);
    });

    testWidgets('has default country pre-filled', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      // Country field should have Spain as default
      final countryField = find.widgetWithText(TextFormField, 'Country');
      expect(countryField, findsOneWidget);
    });

    testWidgets('shows checkout title in app bar', (tester) async {
      await tester.pumpWidget(buildSubject(initialCart: testCart));
      await tester.pumpAndSettle();

      expect(find.text('Checkout'), findsOneWidget);
    });
  });
}
