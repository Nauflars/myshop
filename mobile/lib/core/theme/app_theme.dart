import 'package:flutter/material.dart';

/// MyShop brand theme — mirrors the web CSS design system.
///
/// CSS variables mapping:
///   --main-color:          #06038D  (deep navy/indigo)
///   --main-color-light:    #0805B8
///   --main-color-dark:     #040265
///   --second-color:        #E87722  (vibrant orange)
///   --second-color-light:  #FF9142
///   --second-color-dark:   #C86510
///   --background-color:    #F5F5F5
///   --text-color:          #333333
///   --text-light:          #666666
///   --border / --eaeaea:   #EAEAEA
///   --success-color:       #1AA04F
///   --warning-color:       #FFBA00
///   --red-color:           #FF4848
///   --grey-color:          #A9A8B4
class AppTheme {
  AppTheme._();

  // ── Brand colors (from CSS :root) ──────────────────────────────────
  static const Color primaryColor = Color(0xFF06038D);
  static const Color primaryLight = Color(0xFF0805B8);
  static const Color primaryDark = Color(0xFF040265);

  static const Color secondaryColor = Color(0xFFE87722);
  static const Color secondaryLight = Color(0xFFFF9142);
  static const Color secondaryDark = Color(0xFFC86510);

  // ── Status colors (from CSS) ───────────────────────────────────────
  static const Color successColor = Color(0xFF1AA04F);
  static const Color successDark = Color(0xFF155724);
  static const Color successBg = Color(0xFFD4EDDA);

  static const Color warningColor = Color(0xFFFFBA00);
  static const Color warningDark = Color(0xFF856404);
  static const Color warningBg = Color(0xFFFFF3CD);

  static const Color errorColor = Color(0xFFFF4848);
  static const Color errorDark = Color(0xFF721C24);
  static const Color errorBg = Color(0xFFF8D7DA);

  static const Color infoColor = Color(0xFF0C5460);
  static const Color infoBg = Color(0xFFD1ECF1);

  // ── Neutral palette ────────────────────────────────────────────────
  static const Color backgroundColor = Color(0xFFF5F5F5);
  static const Color backgroundDark = Color(0xFFE8E8E8);
  static const Color textColor = Color(0xFF333333);
  static const Color textLight = Color(0xFF666666);
  static const Color greyColor = Color(0xFFA9A8B4);
  static const Color borderColor = Color(0xFFEAEAEA);
  static const Color cardColor = Color(0xFFFFFFFF);

  // ── Product-specific colors ────────────────────────────────────────
  static const Color inStockColor = Color(0xFF4CAF50);
  static const Color outOfStockColor = Color(0xFFF44336);
  static const Color goldAdmin = Color(0xFFFFD700);

  // ── Order Status Colors (from web inline styles) ───────────────────
  static const Color pendingColor = Color(0xFFFFBA00);
  static const Color confirmedColor = Color(0xFF2196F3);
  static const Color shippedColor = Color(0xFF9C27B0);
  static const Color deliveredColor = Color(0xFF1AA04F);
  static const Color cancelledColor = Color(0xFFFF4848);

  // ── Web CSS: border-radius: 12px / 8px / 20px (pill) ──────────────
  static const double radiusLg = 12.0;
  static const double radiusMd = 8.0;
  static const double radiusSm = 4.0;
  static const double radiusPill = 20.0;

  // ── Shadows matching CSS --shadow-sm / --shadow-md ─────────────────
  static List<BoxShadow> get shadowSm => [
        BoxShadow(
          color: Colors.black.withAlpha(20), // ~0.08
          blurRadius: 8,
          offset: const Offset(0, 2),
        ),
      ];

  static List<BoxShadow> get shadowMd => [
        BoxShadow(
          color: Colors.black.withAlpha(31), // ~0.12
          blurRadius: 16,
          offset: const Offset(0, 4),
        ),
      ];

  // ── Font family (same system stack as *-apple-system, …, Arial) ────
  static const String fontFamily = 'Roboto'; // Material default, close to CSS

  // ── Gradient helpers (from web CSS btn-primary gradient) ────────────
  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primaryColor, primaryLight],
  );

  static const LinearGradient secondaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [secondaryColor, secondaryLight],
  );

  static const LinearGradient navBarGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primaryColor, primaryDark],
  );

  static const LinearGradient heroGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primaryColor, primaryLight, secondaryColor],
  );

  static const LinearGradient chatHeaderGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primaryColor, secondaryColor],
  );

  // ====================================================================
  //  LIGHT THEME
  // ====================================================================
  static ThemeData get lightTheme {
    const colorScheme = ColorScheme(
      brightness: Brightness.light,
      // Primary
      primary: primaryColor,
      onPrimary: Colors.white,
      primaryContainer: Color(0xFFD6DBF5), // navy-blue tint (not lavender)
      onPrimaryContainer: primaryDark,
      // Secondary
      secondary: secondaryColor,
      onSecondary: Colors.white,
      secondaryContainer: Color(0xFFFFDCC2),
      onSecondaryContainer: secondaryDark,
      // Tertiary (info)
      tertiary: Color(0xFF0C5460),
      onTertiary: Colors.white,
      // Error
      error: errorColor,
      onError: Colors.white,
      errorContainer: errorBg,
      onErrorContainer: errorDark,
      // Surface / Background
      surface: cardColor,
      onSurface: textColor,
      onSurfaceVariant: textLight,
      // Outline
      outline: greyColor,
      outlineVariant: borderColor,
      // Misc
      shadow: Colors.black,
      scrim: Colors.black,
      inverseSurface: Color(0xFF313033),
      onInverseSurface: Color(0xFFF4EFF4),
      inversePrimary: Color(0xFFB8B3FF),
      surfaceContainerHighest: backgroundColor,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: backgroundColor,
      fontFamily: fontFamily,

      // Remove M3 purple surface tint
      // ignore: deprecated_member_use
      // Using surfaceTintColor to remove purple tint

      // ── AppBar — gradient navy like web navbar ─────────────────────
      appBarTheme: const AppBarTheme(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: Colors.white,
        ),
        iconTheme: IconThemeData(color: Colors.white),
        actionsIconTheme: IconThemeData(color: Colors.white),
      ),

      // ── ElevatedButton — web .btn-primary (orange CTAs) ─────────────
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: secondaryColor,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
          elevation: 2,
          shadowColor: secondaryColor.withAlpha(80),
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd),
          ),
        ),
      ),

      // ── FilledButton / IconButton.filled — navy ───────────────────
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primaryColor,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd),
          ),
        ),
      ),

      // ── SegmentedButton — navy selected, clear deselected ─────────
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: ButtonStyle(
          backgroundColor: WidgetStateProperty.resolveWith((states) {
            if (states.contains(WidgetState.selected)) {
              return primaryColor;
            }
            return Colors.transparent;
          }),
          foregroundColor: WidgetStateProperty.resolveWith((states) {
            if (states.contains(WidgetState.selected)) {
              return Colors.white;
            }
            return textColor;
          }),
          side: WidgetStateProperty.all(
            const BorderSide(color: borderColor, width: 1.5),
          ),
          shape: WidgetStateProperty.all(
            RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(radiusMd),
            ),
          ),
        ),
      ),

      // ── OutlinedButton — web .btn-outline-secondary ────────────────
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: textColor,
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd),
          ),
          side: const BorderSide(color: greyColor, width: 2),
        ),
      ),

      // ── TextButton — navy primary links ────────────────────────────
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primaryColor,
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
        ),
      ),

      // ── Input fields — web form styling ────────────────────────────
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: borderColor, width: 2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: borderColor, width: 2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: primaryColor, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: errorColor, width: 2),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: errorColor, width: 2),
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        labelStyle: const TextStyle(
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
        hintStyle: const TextStyle(color: textLight),
      ),

      // ── Cards — white, rounded 12px, subtle shadow, #EAEAEA border ─
      cardTheme: CardTheme(
        elevation: 0,
        color: cardColor,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusLg),
          side: const BorderSide(color: borderColor),
        ),
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
      ),

      // ── Chips — pill shape (20px) like web category badges ─────────
      chipTheme: ChipThemeData(
        backgroundColor: const Color(0x14E87722), // orange tint unselected
        selectedColor: secondaryColor,
        labelStyle: const TextStyle(
          color: textColor,
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.5,
        ),
        secondaryLabelStyle: const TextStyle(
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.5,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusPill),
        ),
        side: BorderSide.none,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        checkmarkColor: Colors.white,
      ),

      // ── FAB — secondary orange like web cart badge ─────────────────
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: secondaryColor,
        foregroundColor: Colors.white,
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
      ),

      // ── NavigationBar (bottom) — white bar, orange selected ─────────
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 8,
        indicatorColor: secondaryColor.withAlpha(30), // subtle orange tint
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: secondaryColor,
            );
          }
          return const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
            color: textLight,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(color: secondaryColor, size: 24);
          }
          return const IconThemeData(color: textLight, size: 24);
        }),
      ),

      // ── BottomNavBar (fallback) ────────────────────────────────────
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        selectedItemColor: secondaryColor,
        unselectedItemColor: textLight,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
        backgroundColor: Colors.white,
      ),

      // ── SnackBar — floating, rounded ───────────────────────────────
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: textColor,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusMd),
        ),
        contentTextStyle: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w500,
        ),
      ),

      // ── Divider — #EAEAEA like web borders ─────────────────────────
      dividerTheme: const DividerThemeData(
        color: borderColor,
        thickness: 1,
        space: 1,
      ),

      // ── Dialog — rounded 12px ──────────────────────────────────────
      dialogTheme: DialogTheme(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusLg),
        ),
        titleTextStyle: const TextStyle(
          fontFamily: fontFamily,
          fontSize: 20,
          fontWeight: FontWeight.w700,
          color: textColor,
        ),
      ),

      // ── PopupMenu ──────────────────────────────────────────────────
      popupMenuTheme: PopupMenuThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusMd),
        ),
        elevation: 4,
        surfaceTintColor: Colors.transparent,
      ),

      // ── ListTile ───────────────────────────────────────────────────
      listTileTheme: const ListTileThemeData(
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 2),
      ),

      // ── Text theme — matching web typography ───────────────────────
      textTheme: const TextTheme(
        displayLarge: TextStyle(
          fontSize: 32,
          fontWeight: FontWeight.w700,
          color: textColor,
          height: 1.2,
        ),
        headlineLarge: TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.w700,
          color: textColor,
          height: 1.2,
        ),
        headlineMedium: TextStyle(
          fontSize: 24,
          fontWeight: FontWeight.w600,
          color: textColor,
          height: 1.2,
        ),
        headlineSmall: TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: textColor,
          height: 1.3,
        ),
        titleLarge: TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: textColor,
          height: 1.3,
        ),
        titleMedium: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: textColor,
          height: 1.4,
        ),
        titleSmall: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: textColor,
          height: 1.4,
        ),
        bodyLarge: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w400,
          color: textColor,
          height: 1.6,
        ),
        bodyMedium: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w400,
          color: textColor,
          height: 1.6,
        ),
        bodySmall: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w400,
          color: textLight,
          height: 1.5,
        ),
        labelLarge: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
        labelMedium: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
          color: textLight,
        ),
        labelSmall: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: primaryColor,
          letterSpacing: 0.5,
        ),
      ),

      // ── TabBar ─────────────────────────────────────────────────────
      tabBarTheme: const TabBarTheme(
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white70,
        indicatorColor: secondaryColor,
        labelStyle: TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
      ),

      // ── ProgressIndicator ──────────────────────────────────────────
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: primaryColor,
        linearTrackColor: borderColor,
      ),
    );
  }

  // ====================================================================
  //  DARK THEME
  // ====================================================================
  static ThemeData get darkTheme {
    const darkSurface = Color(0xFF1C1B1F);
    const darkCard = Color(0xFF2B2930);
    const darkBorder = Color(0xFF48464C);
    const lightPrimary = Color(0xFFB8B3FF);

    const colorScheme = ColorScheme(
      brightness: Brightness.dark,
      primary: lightPrimary,
      onPrimary: Color(0xFF1A0060),
      primaryContainer: Color(0xFF302175),
      onPrimaryContainer: Color(0xFFE1DEFF),
      secondary: secondaryLight,
      onSecondary: Color(0xFF4A2800),
      secondaryContainer: Color(0xFF6C3C00),
      onSecondaryContainer: Color(0xFFFFDCC2),
      tertiary: Color(0xFF6BD3E5),
      onTertiary: Color(0xFF003640),
      error: Color(0xFFFFB4AB),
      onError: Color(0xFF690005),
      errorContainer: Color(0xFF93000A),
      onErrorContainer: Color(0xFFFFDAD6),
      surface: darkSurface,
      onSurface: Color(0xFFE6E1E5),
      onSurfaceVariant: Color(0xFFC9C5CA),
      outline: Color(0xFF938F99),
      outlineVariant: darkBorder,
      shadow: Colors.black,
      scrim: Colors.black,
      inverseSurface: Color(0xFFE6E1E5),
      onInverseSurface: Color(0xFF313033),
      inversePrimary: primaryColor,
      surfaceContainerHighest: Color(0xFF36343B),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: darkSurface,
      fontFamily: fontFamily,

      appBarTheme: AppBarTheme(
        backgroundColor: darkCard,
        foregroundColor: colorScheme.onSurface,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
        ),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: secondaryLight,
          foregroundColor: const Color(0xFF4A2800),
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
          elevation: 2,
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd),
          ),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: colorScheme.onSurface,
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd),
          ),
          side: BorderSide(color: darkBorder, width: 2),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: secondaryLight,
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: darkCard,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: darkBorder, width: 2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: darkBorder, width: 2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: lightPrimary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: Color(0xFFFFB4AB), width: 2),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusMd),
          borderSide: const BorderSide(color: Color(0xFFFFB4AB), width: 2),
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        labelStyle: TextStyle(
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
        ),
        hintStyle: TextStyle(color: colorScheme.onSurfaceVariant),
      ),

      cardTheme: CardTheme(
        elevation: 0,
        color: darkCard,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusLg),
          side: const BorderSide(color: darkBorder),
        ),
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
      ),

      chipTheme: ChipThemeData(
        backgroundColor: lightPrimary.withAlpha(26),
        labelStyle: const TextStyle(
          color: lightPrimary,
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.5,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusPill),
        ),
        side: BorderSide.none,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      ),

      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: secondaryColor,
        foregroundColor: Colors.white,
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
      ),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: darkCard,
        surfaceTintColor: Colors.transparent,
        elevation: 8,
        indicatorColor: lightPrimary.withAlpha(26),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: lightPrimary,
            );
          }
          return TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
            color: colorScheme.onSurfaceVariant,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(color: lightPrimary, size: 24);
          }
          return IconThemeData(
              color: colorScheme.onSurfaceVariant, size: 24);
        }),
      ),

      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        selectedItemColor: lightPrimary,
        unselectedItemColor: colorScheme.onSurfaceVariant,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
        backgroundColor: darkCard,
      ),

      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: colorScheme.inverseSurface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusMd),
        ),
      ),

      dividerTheme: const DividerThemeData(
        color: darkBorder,
        thickness: 1,
        space: 1,
      ),

      dialogTheme: DialogTheme(
        backgroundColor: darkCard,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusLg),
        ),
      ),

      popupMenuTheme: PopupMenuThemeData(
        color: darkCard,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusMd),
        ),
        elevation: 4,
        surfaceTintColor: Colors.transparent,
      ),

      listTileTheme: const ListTileThemeData(
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 2),
      ),

      textTheme: TextTheme(
        displayLarge: TextStyle(
          fontSize: 32,
          fontWeight: FontWeight.w700,
          color: colorScheme.onSurface,
          height: 1.2,
        ),
        headlineLarge: TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.w700,
          color: colorScheme.onSurface,
          height: 1.2,
        ),
        headlineMedium: TextStyle(
          fontSize: 24,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
          height: 1.2,
        ),
        headlineSmall: TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
          height: 1.3,
        ),
        titleLarge: TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
          height: 1.3,
        ),
        titleMedium: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
          height: 1.4,
        ),
        titleSmall: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
          height: 1.4,
        ),
        bodyLarge: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w400,
          color: colorScheme.onSurface,
          height: 1.6,
        ),
        bodyMedium: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w400,
          color: colorScheme.onSurface,
          height: 1.6,
        ),
        bodySmall: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w400,
          color: colorScheme.onSurfaceVariant,
          height: 1.5,
        ),
        labelLarge: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: colorScheme.onSurface,
        ),
        labelMedium: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
          color: colorScheme.onSurfaceVariant,
        ),
        labelSmall: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: lightPrimary,
          letterSpacing: 0.5,
        ),
      ),

      tabBarTheme: const TabBarTheme(
        labelColor: lightPrimary,
        unselectedLabelColor: Color(0xFFC9C5CA),
        indicatorColor: secondaryColor,
        labelStyle: TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: lightPrimary,
        linearTrackColor: darkBorder,
      ),
    );
  }

  /// Get color for order status badge
  static Color orderStatusColor(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
        return pendingColor;
      case 'CONFIRMED':
        return confirmedColor;
      case 'SHIPPED':
        return shippedColor;
      case 'DELIVERED':
        return deliveredColor;
      case 'CANCELLED':
        return cancelledColor;
      default:
        return greyColor;
    }
  }

  /// Get gradient for a status color
  static LinearGradient statusGradient(Color color) {
    return LinearGradient(
      colors: [color, color.withAlpha(200)],
    );
  }
}
