import 'package:flutter/material.dart';

import '../core/theme/motion.dart';

/// Subtle fade + rise used for every route push/pop.
///
/// Wired globally through [AppTheme] so all `MaterialPageRoute`s share it
/// without touching individual `Navigator.push` calls.
class FadeSlidePageTransitionsBuilder extends PageTransitionsBuilder {
  const FadeSlidePageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    if (!AppMotion.enabled(context)) return child;
    final fade = CurvedAnimation(
      parent: animation,
      curve: Interval(0.0, 1.0, curve: AppMotion.easeOut),
    );
    final slide = Tween<Offset>(
      begin: const Offset(0, 0.035),
      end: Offset.zero,
    ).animate(fade);
    return FadeTransition(
      opacity: fade,
      child: SlideTransition(position: slide, child: child),
    );
  }
}
