import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

/// The same teal, ivory and soft mist used by the INVOIZ website.
class AppColors {
  AppColors._();
  static const background = Color(0xFFF7F6F2);
  static const paper = Color(0xFFFBF9F6);
  static const paleTeal = Color(0xFFEAF4F3);
  static const cream = Color(0xFFF3F0E9);
  static const errorTint = Color(0xFFFEF2F2);
  static const surface = Colors.white;
  static const surfaceTint = Color(0xFFEDF3F1);
  static const border = Color(0xFFD9E4E1);
  static const textPrimary = Color(0xFF1B1B1E);
  static const textSecondary = Color(0xFF56696B);
  static const textMuted = Color(0xFF6E6E73);
  static const primary = Color(0xFF16697A);
  static const primaryDark = Color(0xFF0E4A57);
  static const accentOnLight = Colors.white;
  static const success = Color(0xFF287660);
  static const danger = Color(0xFFB3261E);
  static const warning = Color(0xFF8A610B);
  static const gold = Color(0xFFF0A202);
  static const accent = surfaceTint;
  static const card = surface;
  static const secondary = primaryDark;
  static const sandLight = Color(0xFFF0F2ED);
  static const sandMid = Color(0xFFE5EDE7);
  static const sandDark = Color(0xFFD6E3DD);
  static const surfaceSoft = surfaceTint;
}

/// Shared geometry for the buyer shell.
class AppSizes {
  AppSizes._();

  /// Height of the floating tab pill itself.
  static const floatingNavHeight = 62.0;

  /// Space the pill keeps above the content it floats over.
  static const floatingNavGap = 8.0;

  /// Smallest gap between the pill and the device edge.
  static const floatingNavBottomGap = 12.0;

  /// Breathing room between the last row of a page and the pill.
  static const floatingNavBreathingRoom = 16.0;

  /// Bottom padding a tab-root scroll view needs so its last row can clear
  /// the floating pill instead of resting underneath it.
  static double bottomNavClearance(BuildContext context) {
    final inset = MediaQuery.viewPaddingOf(context).bottom;
    return floatingNavHeight +
        floatingNavGap +
        (inset > floatingNavBottomGap ? inset : floatingNavBottomGap) +
        floatingNavBreathingRoom;
  }
}

class AppTheme {
  AppTheme._();
  static const cupertino = CupertinoThemeData(
    brightness: Brightness.light,
    primaryColor: AppColors.primaryDark,
    primaryContrastingColor: CupertinoColors.white,
    barBackgroundColor: AppColors.paper,
    scaffoldBackgroundColor: AppColors.background,
    textTheme: CupertinoTextThemeData(
      textStyle: TextStyle(
        fontFamily: 'Inter',
        fontSize: 14,
        height: 1.4,
        color: AppColors.textPrimary,
      ),
      actionTextStyle: TextStyle(
        fontFamily: 'Inter',
        fontSize: 14,
        fontWeight: FontWeight.w600,
        color: AppColors.primaryDark,
      ),
      navTitleTextStyle: TextStyle(
        fontFamily: 'Inter',
        fontSize: 16,
        fontWeight: FontWeight.w600,
        color: AppColors.primaryDark,
      ),
      navActionTextStyle: TextStyle(
        fontFamily: 'Inter',
        fontSize: 15,
        color: AppColors.primaryDark,
      ),
      tabLabelTextStyle: TextStyle(
        fontFamily: 'Inter',
        fontSize: 11,
        fontWeight: FontWeight.w500,
        color: AppColors.textMuted,
      ),
      dateTimePickerTextStyle: TextStyle(
        fontFamily: 'Inter',
        fontSize: 18,
        color: AppColors.textPrimary,
      ),
    ),
  );
  static ThemeData get dark => light;
  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      onPrimary: Colors.white,
      secondary: AppColors.primaryDark,
      surface: AppColors.surface,
      onSurface: AppColors.textPrimary,
      error: AppColors.danger,
      outline: AppColors.border,
    );
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: 'Inter',
    );
    return base.copyWith(
      platform: TargetPlatform.iOS,
      cupertinoOverrideTheme: cupertino,
      splashFactory: NoSplash.splashFactory,
      highlightColor: Colors.transparent,
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CupertinoPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.windows: CupertinoPageTransitionsBuilder(),
          TargetPlatform.linux: CupertinoPageTransitionsBuilder(),
        },
      ),
      scaffoldBackgroundColor: AppColors.background,
      textTheme: base.textTheme
          .apply(
            bodyColor: AppColors.textPrimary,
            displayColor: AppColors.primaryDark,
          )
          .copyWith(
            headlineMedium: const TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 26,
              height: 1.15,
              fontWeight: FontWeight.w600,
              letterSpacing: -.6,
              color: AppColors.primaryDark,
            ),
            headlineSmall: const TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 24,
              height: 1.2,
              fontWeight: FontWeight.w600,
              color: AppColors.primaryDark,
            ),
            titleLarge: const TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 20,
              fontWeight: FontWeight.w600,
              color: AppColors.primaryDark,
            ),
            titleMedium: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
            bodyLarge: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 15,
              height: 1.5,
              color: AppColors.textPrimary,
            ),
            bodyMedium: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 14,
              height: 1.45,
              color: AppColors.textSecondary,
            ),
            bodySmall: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 12,
              height: 1.4,
              color: AppColors.textSecondary,
            ),
          ),
      appBarTheme: const AppBarTheme(
        toolbarHeight: 52,
        backgroundColor: AppColors.paper,
        foregroundColor: AppColors.primaryDark,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleTextStyle: TextStyle(
          fontFamily: 'Inter',
          fontSize: 17,
          fontWeight: FontWeight.w600,
          color: AppColors.primaryDark,
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.border, width: .5),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(44, 44),
          backgroundColor: AppColors.primaryDark,
          foregroundColor: Colors.white,
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(999),
          ),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          minimumSize: const Size(44, 44),
          backgroundColor: AppColors.primaryDark,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(999),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(44, 44),
          foregroundColor: AppColors.primaryDark,
          side: const BorderSide(color: AppColors.border, width: .5),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(999),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(44, 44),
          foregroundColor: AppColors.primary,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        indicatorColor: AppColors.surfaceTint,
        height: 64,
        iconTheme: WidgetStateProperty.all(const IconThemeData(size: 22)),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w600
                : FontWeight.w400,
            color: states.contains(WidgetState.selected)
                ? AppColors.primaryDark
                : AppColors.textSecondary,
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.paper,
        selectedColor: AppColors.surfaceTint,
        side: BorderSide.none,
        labelStyle: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 13,
          color: AppColors.primaryDark,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      ),
      inputDecorationTheme: InputDecorationTheme(
        isDense: true,
        constraints: const BoxConstraints(minHeight: 44),
        filled: true,
        fillColor: AppColors.paper,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 12,
          vertical: 11,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.border, width: .5),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.border, width: .5),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.primary, width: 1),
        ),
        hintStyle: const TextStyle(fontSize: 14, color: AppColors.textMuted),
        prefixIconColor: AppColors.textSecondary,
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.border,
        thickness: 1,
        space: 1,
      ),
      iconTheme: const IconThemeData(color: AppColors.primaryDark, size: 22),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.primaryDark,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      listTileTheme: const ListTileThemeData(
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 5),
        iconColor: AppColors.primary,
        titleTextStyle: TextStyle(
          fontFamily: 'Inter',
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.primaryDark,
        ),
      ),
      tabBarTheme: const TabBarThemeData(
        labelColor: AppColors.primaryDark,
        unselectedLabelColor: AppColors.textSecondary,
        indicatorColor: AppColors.primaryDark,
        dividerColor: AppColors.border,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
      ),
    );
  }
}
