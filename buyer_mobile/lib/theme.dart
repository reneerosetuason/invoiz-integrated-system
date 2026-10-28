import 'package:flutter/material.dart';

/// Warm Sand theme — matches the design guide exactly.
class AppColors {
  AppColors._();

  // Backgrounds
  static const Color background = Color(0xFFF1EFE8);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceTint = Color(0xFFEFE4D3);

  // Neutrals
  static const Color border = Color(0xFFD3D1C7);
  static const Color textPrimary = Color(0xFF2C2C2A);
  static const Color textSecondary = Color(0xFF5F5E5A);
  static const Color textMuted = Color(0xFF888780);

  // Accent (terracotta)
  static const Color primary = Color(0xFFD85A30);
  static const Color primaryDark = Color(0xFF993C1D);
  static const Color accentOnLight = Color(0xFFFAECE7);

  // Semantic
  static const Color success = Color(0xFF639922);
  static const Color danger = Color(0xFFE24B4A);
  static const Color warning = Color(0xFFE24B4A);

  // Aliases for backward compat
  static const Color accent = surfaceTint;
  static const Color card = surface;
  static const Color gold = Color(0xFFF5A623);
  static const Color secondary = surfaceTint;
  static const Color sandLight = surfaceTint;
  static const Color sandMid = Color(0xFFE7D9C6);
  static const Color sandDark = Color(0xFFDCCBB3);
  static const Color surfaceSoft = surfaceTint;
}

class AppTheme {
  AppTheme._();

  static ThemeData get light => _build();
  static ThemeData get dark => light;

  static ThemeData _build() {
    final colorScheme = ColorScheme.light(
      primary: AppColors.primary,
      onPrimary: AppColors.accentOnLight,
      secondary: AppColors.primaryDark,
      onSecondary: AppColors.accentOnLight,
      surface: AppColors.surface,
      onSurface: AppColors.textPrimary,
      error: AppColors.danger,
      onError: Colors.white,
      outline: AppColors.border,
    );

    final baseTextTheme = ThemeData.light().textTheme;

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: AppColors.background,

      textTheme: baseTextTheme.copyWith(
        displayLarge: baseTextTheme.displayLarge?.copyWith(
          fontFamily: 'Georgia',
          fontWeight: FontWeight.w400,
          fontSize: 34,
          height: 1.25,
          color: AppColors.textPrimary,
        ),
        displaySmall: baseTextTheme.displaySmall?.copyWith(
          fontFamily: 'Georgia',
          fontWeight: FontWeight.w400,
          fontSize: 34,
          height: 1.25,
          color: AppColors.textPrimary,
        ),
        headlineMedium: baseTextTheme.headlineMedium?.copyWith(
          fontFamily: 'Georgia',
          fontWeight: FontWeight.w400,
          fontSize: 22,
          color: AppColors.textPrimary,
        ),
        headlineSmall: baseTextTheme.headlineSmall?.copyWith(
          fontFamily: 'Georgia',
          fontWeight: FontWeight.w400,
          fontSize: 20,
          color: AppColors.textPrimary,
        ),
        titleLarge: baseTextTheme.titleLarge?.copyWith(
          fontFamily: 'Georgia',
          fontWeight: FontWeight.w400,
          fontSize: 19,
          color: AppColors.textPrimary,
          letterSpacing: 0.5,
        ),
        titleMedium: baseTextTheme.titleMedium?.copyWith(
          fontFamily: 'Segoe UI',
          fontWeight: FontWeight.w500,
          fontSize: 15,
          color: AppColors.textPrimary,
        ),
        bodyLarge: baseTextTheme.bodyLarge?.copyWith(
          fontFamily: 'Segoe UI',
          fontSize: 14,
          height: 1.6,
          color: AppColors.textSecondary,
        ),
        bodyMedium: baseTextTheme.bodyMedium?.copyWith(
          fontFamily: 'Segoe UI',
          fontSize: 13,
          color: AppColors.textSecondary,
        ),
        bodySmall: baseTextTheme.bodySmall?.copyWith(
          fontFamily: 'Segoe UI',
          fontSize: 12,
          color: AppColors.textSecondary,
        ),
        labelLarge: baseTextTheme.labelLarge?.copyWith(
          fontFamily: 'Segoe UI',
          fontWeight: FontWeight.w500,
          fontSize: 13,
          color: AppColors.textPrimary,
        ),
        labelSmall: baseTextTheme.labelSmall?.copyWith(
          fontFamily: 'Segoe UI',
          fontSize: 12,
          letterSpacing: 1.2,
          color: AppColors.primary,
        ),
      ),

      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.background,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: TextStyle(
          fontFamily: 'Georgia',
          fontSize: 19,
          fontWeight: FontWeight.w400,
          color: AppColors.textPrimary,
          letterSpacing: 0.5,
        ),
      ),

      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: const BorderSide(color: AppColors.border, width: 0.5),
        ),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: AppColors.accentOnLight,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          textStyle: const TextStyle(
            fontFamily: 'Segoe UI',
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.textPrimary,
          side: const BorderSide(color: AppColors.border, width: 0.5),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surfaceTint,
        labelStyle: const TextStyle(
          fontFamily: 'Segoe UI',
          fontSize: 12,
          color: AppColors.textPrimary,
        ),
        side: BorderSide.none,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 12,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.border, width: 0.5),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.border, width: 0.5),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.primary, width: 1),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.danger, width: 0.5),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.danger, width: 1),
        ),
        hintStyle: const TextStyle(
          fontFamily: 'Segoe UI',
          fontSize: 13,
          color: AppColors.textMuted,
        ),
        labelStyle: const TextStyle(
          fontFamily: 'Segoe UI',
          fontSize: 13,
          color: AppColors.textSecondary,
        ),
        prefixIconColor: AppColors.textSecondary,
      ),

      dividerTheme: const DividerThemeData(
        color: AppColors.border,
        thickness: 0.5,
        space: 1,
      ),

      iconTheme: const IconThemeData(color: AppColors.textSecondary, size: 20),

      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.background,
        selectedItemColor: AppColors.primary,
        unselectedItemColor: AppColors.textMuted,
        elevation: 0,
        type: BottomNavigationBarType.fixed,
      ),

      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.accentOnLight,
        elevation: 0,
      ),

      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.textPrimary,
        contentTextStyle: const TextStyle(
          color: Colors.white,
          fontFamily: 'Segoe UI',
          fontSize: 13,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),

      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
      ),
    );
  }
}
