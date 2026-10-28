import 'package:flutter/material.dart';
import '../theme.dart';

/// Shared Invoiz logo image. Use [logoWidget] for a rounded-tile version
/// already sized and boxed (falls back to a storefront icon if missing).
class InvoizLogo {
  static const String assetPath = 'assets/logo.png';

  static Widget logoWidget({
    double size = 48,
    double radius = 12,
    Color? background,
  }) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: background ?? Colors.white,
        borderRadius: BorderRadius.circular(radius),
      ),
      clipBehavior: Clip.antiAlias,
      child: Image.asset(
        assetPath,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) =>
            Icon(Icons.storefront, color: AppColors.primary, size: 28),
      ),
    );
  }
}
