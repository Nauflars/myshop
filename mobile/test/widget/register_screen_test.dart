import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mobile/features/auth/data/auth_repository.dart';
import 'package:mobile/features/auth/domain/entities/user.dart';
import 'package:mobile/features/auth/presentation/providers/auth_provider.dart';
import 'package:mobile/features/auth/presentation/screens/register_screen.dart';

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
    initialLocation: '/register',
    routes: [
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) =>
            const Scaffold(body: Text('Login Screen')),
      ),
      GoRoute(
        path: '/',
        builder: (context, state) =>
            const Scaffold(body: Text('Home Screen')),
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

  group('RegisterScreen', () {
    testWidgets('renders all form fields', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      expect(find.text('Full Name'), findsOneWidget);
      expect(find.text('Email'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
      expect(find.text('Confirm Password'), findsOneWidget);
      expect(find.text('Create Account'), findsWidgets);
    });

    testWidgets('shows branding text', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      expect(find.text('Join MyShop'), findsOneWidget);
      expect(
          find.text('Create your account to start shopping'), findsOneWidget);
    });

    testWidgets('shows validation errors on empty submit', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      // Scroll down to make button visible and tap
      await tester.ensureVisible(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.pumpAndSettle();

      expect(find.text('Name is required'), findsOneWidget);
      expect(find.text('Email is required'), findsOneWidget);
      expect(find.text('Password is required'), findsOneWidget);
      expect(find.text('Please confirm your password'), findsOneWidget);
    });

    testWidgets('validates minimum name length', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Full Name'), 'A');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'valid@email.com');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'password123');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Confirm Password'),
          'password123');

      await tester.ensureVisible(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.pumpAndSettle();

      expect(
          find.text('Name must be at least 2 characters'), findsOneWidget);
    });

    testWidgets('validates email format', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Full Name'), 'Test');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'invalidemail');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'password123');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Confirm Password'),
          'password123');

      await tester.ensureVisible(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.pumpAndSettle();

      expect(find.text('Please enter a valid email'), findsOneWidget);
    });

    testWidgets('validates password minimum length', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Full Name'), 'Test');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'test@test.com');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'abc');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Confirm Password'), 'abc');

      await tester.ensureVisible(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.pumpAndSettle();

      expect(find.text('Password must be at least 6 characters'),
          findsOneWidget);
    });

    testWidgets('validates password confirmation matches', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Full Name'), 'Test');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'test@test.com');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'password123');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Confirm Password'),
          'different');

      await tester.ensureVisible(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.pumpAndSettle();

      expect(find.text('Passwords do not match'), findsOneWidget);
    });

    testWidgets('toggles password visibility for both fields',
        (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      // Two visibility icons (password + confirm)
      final visibilityIcons = find.byIcon(Icons.visibility_outlined);
      expect(visibilityIcons, findsNWidgets(2));

      // Toggle first password field
      await tester.tap(visibilityIcons.first);
      await tester.pumpAndSettle();

      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
    });

    testWidgets('successful registration calls repository', (tester) async {
      when(() => mockRepo.register(
            name: 'New User',
            email: 'new@example.com',
            password: 'password123',
          )).thenAnswer((_) async => _testUser);
      when(() => mockRepo.login(
            email: 'new@example.com',
            password: 'password123',
          )).thenAnswer((_) async => _testUser);

      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.widgetWithText(TextFormField, 'Full Name'), 'New User');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Email'), 'new@example.com');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Password'), 'password123');
      await tester.enterText(
          find.widgetWithText(TextFormField, 'Confirm Password'),
          'password123');

      await tester.ensureVisible(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Create Account'));
      await tester.pumpAndSettle();

      verify(() => mockRepo.register(
            name: 'New User',
            email: 'new@example.com',
            password: 'password123',
          )).called(1);
    });

    testWidgets('has login navigation link', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      expect(
          find.text('Already have an account? Sign in'), findsOneWidget);
    });

    testWidgets('tapping sign in link navigates to login', (tester) async {
      await tester.pumpWidget(_buildTestWidget(
        overrides: [
          authRepositoryProvider.overrideWithValue(mockRepo),
        ],
      ));
      await tester.pumpAndSettle();

      await tester.ensureVisible(
          find.text('Already have an account? Sign in'));
      await tester.tap(find.text('Already have an account? Sign in'));
      await tester.pumpAndSettle();

      expect(find.text('Login Screen'), findsOneWidget);
    });
  });
}
