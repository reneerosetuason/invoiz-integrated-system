import 'package:flutter/material.dart';

import '../../widgets/app_transitions.dart';

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
  static const Color background = Color(0xFFF8FAF9);
  static const Color card = Color(0xFFFFFFFF);
  static const Color textPrimary = Color(0xFF1F2933);
  static const Color textSecondary = Color(0xFF6E7B7B);
  static const Color success = Color(0xFF2E8B57);
  static const Color warning = Color(0xFFE05A33);
  static const Color gold = Color(0xFFF5A623);
  static const Color border = Color(0xFFE3E9E9);
  static const Color surfaceSoft = Color(0xFFEDF1F1);

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
  static const double md = 18;
  static const double lg = 24;
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
    fontSize: 30,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.5,
    color: AppColors.textPrimary,
    height: 1.1,
  );

  /// Page titles.
  static const TextStyle title = TextStyle(
    fontSize: 22,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.3,
    color: AppColors.textPrimary,
    height: 1.2,
  );

  /// Section headings.
  static const TextStyle section = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.2,
    color: AppColors.textPrimary,
  );

  /// Card titles and emphasized rows.
  static const TextStyle cardTitle = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
    height: 1.3,
  );

  /// Default body copy.
  static const TextStyle body = TextStyle(
    fontSize: 14,
    color: AppColors.textPrimary,
    height: 1.45,
  );

  /// Supporting descriptions and metadata.
  static const TextStyle secondary = TextStyle(
    fontSize: 13,
    color: AppColors.textSecondary,
    height: 1.4,
  );

  /// Eyebrow labels, captions, timestamps.
  static const TextStyle caption = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w600,
    color: AppColors.textSecondary,
    height: 1.35,
  );

  /// Small caps eyebrow used above sections and KPI groups.
  static const TextStyle eyebrow = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w800,
    letterSpacing: 1.1,
    color: AppColors.textSecondary,
  );

  /// Tracking/waybill numbers — tabular, high-contrast, easy to locate.
  static const TextStyle tracking = TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w800,
    letterSpacing: 0.2,
    color: AppColors.textPrimary,
    fontFeatures: [FontFeature.tabularFigures()],
  );

  /// Large KPI values.
  static TextStyle kpi([double size = 28]) => TextStyle(
        fontSize: size,
        fontWeight: FontWeight.w800,
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

  /// Errors use the brand warning tone (no arbitrary reds).
  static const Color danger = AppColors.warning;

  static const Color background = AppColors.background;
  static const Color textPrimary = AppColors.textPrimary;
  static const Color textSecondary = AppColors.textSecondary;
  static const Color border = AppColors.border;
  static const Color surfaceSoft = AppColors.surfaceSoft;

  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        primary: primary,
        secondary: secondary,
        surface: AppColors.card,
        error: warning,
      ),
      scaffoldBackgroundColor: background,
    );

    return base.copyWith(
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.card,
        foregroundColor: textPrimary,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: textPrimary,
          fontSize: 20,
          fontWeight: FontWeight.w700,
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
          backgroundColor: primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: primary.withValues(alpha: 0.5),
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          foregroundColor: primary,
          side: const BorderSide(color: primary, width: 1.5),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: primary),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surfaceSoft,
        hintStyle: TextStyle(
          color: textSecondary.withValues(alpha: 0.7),
          fontSize: 14,
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: warning),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: warning, width: 1.6),
        ),
      ),
      chipTheme: const ChipThemeData(
        shape: StadiumBorder(side: BorderSide(color: border)),
        side: BorderSide(color: border),
      ),
      dialogTheme: DialogThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: textPrimary,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: FadeSlidePageTransitionsBuilder(),
          TargetPlatform.iOS: FadeSlidePageTransitionsBuilder(),
          TargetPlatform.windows: FadeSlidePageTransitionsBuilder(),
          TargetPlatform.macOS: FadeSlidePageTransitionsBuilder(),
          TargetPlatform.linux: FadeSlidePageTransitionsBuilder(),
        },
      ),
    );
  }
}
