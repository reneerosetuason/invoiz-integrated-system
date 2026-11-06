import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

/// Invoiz brand palette — single source of truth for the Rider app.
///
/// Mirrors the Invoiz web / buyer-seller ecosystem so the mobile app reads
/// as "Invoiz on mobile", not a separate courier identity. Prefer
/// `AppColors.primary` over raw `Color(0xFF…)` literals in every screen.
class AppColors {
  AppColors._();

  static const Color primary = Color(0xFF16697A);
  static const Color primaryDark = Color(0xFF0E4A57);
  static const Color secondary = Color(0xFFF0A202);
  static const Color accent = Color(0xFFEAF4F3);
  static const Color background = Color(0xFFF2F2F7);
  static const Color paper = Color(0xFFFAFAFC);
  static const Color danger = Color(0xFFB3261E);
  static const Color card = Color(0xFFFFFFFF);
  static const Color textPrimary = Color(0xFF1B1B1E);
  static const Color textSecondary = Color(0xFF56696B);
  static const Color success = Color(0xFF287660);
  static const Color warning = Color(0xFFE05A33);
  static const Color gold = Color(0xFFF5A623);
  static const Color border = Color(0xFFDCDCE1);
  static const Color surfaceSoft = Color(0xFFEDF3F1);

  /// Elevated surface for primary cards (always above [background]).
  static const Color surface = Color(0xFFFFFFFF);

  /// Soft brand tint for icon tiles, avatars, and selected states.
  static const Color tint = Color(0xFFEAF4F3);
}

/// Corner radii — one scale for the whole app.
class AppRadius {
  AppRadius._();

  static const double xs = 10;
  static const double sm = 14;
  static const double md = 16;
  static const double lg = 20;
  static const double xl = 32;
  static const double pill = 999;
}

/// Spacing scale — section rhythm uses these steps only.
class AppGaps {
  AppGaps._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 28;
}

/// Type scale — every screen draws titles and values from here so the
/// hierarchy reads the same everywhere: display > title > section >
/// card title > body > secondary > caption.
class AppText {
  AppText._();

  /// Hero numbers and greetings.
  static const TextStyle display = TextStyle(
    fontFamily: 'Inter',
    fontSize: 32,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.5,
    color: AppColors.textPrimary,
    height: 1.1,
  );

  /// Page titles.
  static const TextStyle title = TextStyle(
    fontFamily: 'Inter',
    fontSize: 24,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.3,
    color: AppColors.textPrimary,
    height: 1.2,
  );

  /// Section headings.
  static const TextStyle section = TextStyle(
    fontFamily: 'Inter',
    fontSize: 16,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.2,
    color: AppColors.textPrimary,
  );

  /// Card titles and emphasized rows.
  static const TextStyle cardTitle = TextStyle(
    fontFamily: 'Inter',
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
    height: 1.3,
  );

  /// Default body copy.
  static const TextStyle body = TextStyle(
    fontFamily: 'Inter',
    fontSize: 14,
    color: AppColors.textPrimary,
    height: 1.45,
  );

  /// Supporting descriptions and metadata.
  static const TextStyle secondary = TextStyle(
    fontFamily: 'Inter',
    fontSize: 13,
    color: AppColors.textSecondary,
    height: 1.4,
  );

  /// Eyebrow labels, captions, timestamps.
  static const TextStyle caption = TextStyle(
    fontFamily: 'Inter',
    fontSize: 11,
    fontWeight: FontWeight.w600,
    color: AppColors.textSecondary,
    height: 1.35,
  );

  /// Small caps eyebrow used above sections and KPI groups.
  static const TextStyle eyebrow = TextStyle(
    fontFamily: 'Inter',
    fontSize: 11,
    fontWeight: FontWeight.w600,
    letterSpacing: 1.1,
    color: AppColors.textSecondary,
  );

  /// Tracking/waybill numbers — tabular, high-contrast, easy to locate.
  static const TextStyle tracking = TextStyle(
    fontFamily: 'Inter',
    fontSize: 17,
    fontWeight: FontWeight.w600,
    letterSpacing: 0.2,
    color: AppColors.textPrimary,
    fontFeatures: [FontFeature.tabularFigures()],
  );

  /// Large KPI values.
  static TextStyle kpi([double size = 28]) => TextStyle(
    fontFamily: 'Inter',
    fontSize: size,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.5,
    color: AppColors.textPrimary,
    height: 1.05,
    fontFeatures: const [FontFeature.tabularFigures()],
  );
}

/// Invoize Rider application theme.
///
/// Uses the platform default font (Segoe UI on Windows/web, Roboto on
/// Android) — the closest Flutter equivalent to the Invoiz `system-ui`
/// stack — with no extra font dependencies.
class AppTheme {
  AppTheme._();

  static const Color primary = AppColors.primary;
  static const Color primaryDark = AppColors.primaryDark;
  static const Color secondary = AppColors.secondary;
  static const Color accent = AppColors.accent;
  static const Color success = AppColors.success;
  static const Color warning = AppColors.warning;

  /// Keep errors distinct from delivery warnings.
  static const Color danger = AppColors.danger;

  static const Color background = AppColors.background;
  static const Color textPrimary = AppColors.textPrimary;
  static const Color textSecondary = AppColors.textSecondary;
  static const Color border = AppColors.border;
  static const Color surfaceSoft = AppColors.surfaceSoft;

  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      platform: TargetPlatform.iOS,
      fontFamily: 'Inter',
      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        primary: primary,
        secondary: secondary,
        surface: AppColors.card,
        error: danger,
        onSurface: textPrimary,
        outline: border,
      ),
      scaffoldBackgroundColor: background,
    );

    return base.copyWith(
      cupertinoOverrideTheme: const CupertinoThemeData(
        brightness: Brightness.light,
        primaryColor: primary,
        scaffoldBackgroundColor: background,
        barBackgroundColor: AppColors.paper,
        textTheme: CupertinoTextThemeData(
          textStyle: TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            color: textPrimary,
          ),
          navTitleTextStyle: TextStyle(
            fontFamily: 'Inter',
            fontSize: 17,
            fontWeight: FontWeight.w600,
            color: textPrimary,
          ),
          actionTextStyle: TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            color: primary,
          ),
        ),
      ),
      splashFactory: NoSplash.splashFactory,
      highlightColor: Colors.transparent,
      visualDensity: VisualDensity.standard,
      textTheme: base.textTheme
          .apply(bodyColor: textPrimary, displayColor: primaryDark)
          .copyWith(
            headlineMedium: AppText.display,
            headlineSmall: AppText.title,
            titleLarge: AppText.title.copyWith(fontSize: 20),
            titleMedium: AppText.cardTitle,
          ),
      dividerTheme: const DividerThemeData(color: border, thickness: .6),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.paper,
        surfaceTintColor: Colors.transparent,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.paper,
        surfaceTintColor: Colors.transparent,
        scrolledUnderElevation: 0,
        foregroundColor: textPrimary,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          color: textPrimary,
          fontFamily: 'Inter',
          fontSize: 17,
          fontWeight: FontWeight.w600,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: AppColors.card,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: border),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryDark,
          foregroundColor: Colors.white,
          disabledBackgroundColor: primary.withValues(alpha: 0.5),
          minimumSize: const Size(44, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(44, 48),
          foregroundColor: primary,
          side: const BorderSide(color: border),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: primary),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primaryDark,
          foregroundColor: Colors.white,
          minimumSize: const Size(44, 48),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
          shape: const StadiumBorder(),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.card,
        hintStyle: TextStyle(
          color: textSecondary.withValues(alpha: 0.7),
          fontSize: 14,
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: danger, width: 1.6),
        ),
      ),
      chipTheme: const ChipThemeData(
        shape: StadiumBorder(side: BorderSide(color: border)),
        side: BorderSide(color: border),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.paper,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: textPrimary,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CupertinoPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.windows: CupertinoPageTransitionsBuilder(),
          TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.linux: CupertinoPageTransitionsBuilder(),
        },
      ),
    );
  }
}
