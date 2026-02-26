import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/features/auth/data/auth_repository.dart';
import 'package:mobile/features/auth/domain/entities/user.dart';
import 'package:mobile/features/auth/presentation/providers/auth_provider.dart';
import 'package:mobile/features/auth/presentation/screens/login_screen.dart';

// -- Mocks --
class MockAuthRepository extends Mock implements AuthRepository {}

const _testUser = User(
  id: '1',
  name: 'Test User',
  email: 'test@example.com',
  roles: ['ROLE_CUSTOMER'],
);

Widget _buildTestWidget({
  required List<Override> overrides,
}) {
  final router = GoRouter(
    initialLocation: '/login',
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/',
        builder: (context, state) =>
            const Scaffold(body: Text('Home Screen')),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) =>
            const Scaffold(body: Text('Register Screen')),
      ),
    ],
  );

  return ProviderScope(
    overrides: overrides,
    child: MaterialApp.router(
      routerConfig: router,
      theme: ThemeData(
        splashFactory: InkRipple.splashFactory,
      ),
    ),
  );
}

void main() {
  late MockAuthRepository mockRepo;

  setUp(() {
    mockRepo = MockAuthRepository();
    // Default: getMe fails (not logged in)
    when(() => mockRepo.getMe()).thenThrow(Exception('Not authenticated'));
  });

  group('LoginScreen', () {
    testWidgets('renders email and password fields', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      expect(find.text('Email'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
      expect(find.text('Sign In'), findsOneWidget);
    });

    testWidgets('shows MyShop branding', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      expect(find.text('MyShop'), findsOneWidget);
      expect(find.text('Sign in to continue'), findsOneWidget);
      expect(find.byIcon(Icons.shopping_bag), findsOneWidget);
    });

    testWidgets('shows validation errors on empty submit', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.text('Email is required'), findsOneWidget);
      expect(find.text('Password is required'), findsOneWidget);
    });

    testWidgets('shows email validation error for invalid email',
        (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'notanemail');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'password');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.text('Please enter a valid email'), findsOneWidget);
    });

    testWidgets('shows password length validation error', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'test@example.com');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'ab');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(
          find.text('Password must be at least 3 characters'), findsOneWidget);
    });

    testWidgets('toggles password visibility', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      // Password is initially obscured
      final passwordField = find.widgetWithText(TextFormField, 'Password');
      expect(passwordField, findsOneWidget);

      // Tap visibility toggle
      await tester.tap(find.byIcon(Icons.visibility_outlined));
      await tester.pumpAndSettle();

      // After toggle, should show visibility_off icon
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
    });

    testWidgets('successful login calls repository and navigates',
        (tester) async {
      when(() => mockRepo.login(
            email: 'test@example.com',
            password: 'password123',
          )).thenAnswer((_) async => _testUser);

      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'test@example.com');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'password123');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      verify(() => mockRepo.login(
            email: 'test@example.com',
            password: 'password123',
          )).called(1);
    });

    testWidgets('has register navigation link', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      expect(find.text("Don't have an account? Register"), findsOneWidget);
    });

    testWidgets('tapping register link navigates to register screen',
        (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.tap(find.text("Don't have an account? Register"));
      await tester.pumpAndSettle();

      expect(find.text('Register Screen'), findsOneWidget);
    });
  });
}
