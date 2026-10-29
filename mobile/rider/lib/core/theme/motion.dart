import 'package:flutter/material.dart';

/// Shared motion system for the Rider app.
///
/// Every animation in the app derives from these values so motion feels
/// consistent: quick, subtle, and purposeful. Animations communicate
/// navigation, state change, feedback, and hierarchy — never decoration.
class AppMotion {
  AppMotion._();

  /// Page transitions (route push/pop).
  static const Duration page = Duration(milliseconds: 260);

  /// Entrance of cards, sections, and list items.
  static const Duration entrance = Duration(milliseconds: 320);

  /// Stagger step between sibling items.
  static const Duration staggerStep = Duration(milliseconds: 45);

  /// Micro-interactions (press, toggle, badge pop).
  static const Duration micro = Duration(milliseconds: 150);

  /// Standard easing: quick start, gentle settle.
  static const Curve easeOut = Curves.easeOutCubic;

  /// Snappier curve for press feedback.
  static const Curve press = Curves.easeOutQuad;

  /// Whether motion is allowed here. Honors the OS reduced-motion setting
  /// so animations never harm usability or accessibility.
  static bool enabled(BuildContext context) =>
      !MediaQuery.of(context).disableAnimations;
}
