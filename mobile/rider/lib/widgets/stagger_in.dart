import 'package:flutter/material.dart';

import '../core/theme/motion.dart';

/// One-shot staggered entrance: fades in while rising slightly.
///
/// Plays once when first built (keyed by widget identity), so list refreshes
/// and provider rebuilds never replay it. [index] staggers siblings; delays
/// are capped so users never wait on content. Honors reduced motion.
class StaggerIn extends StatefulWidget {
  const StaggerIn({
    super.key,
    required this.child,
    this.index = 0,
    this.maxDelay = const Duration(milliseconds: 320),
  });

  final Widget child;
  final int index;
  final Duration maxDelay;

  @override
  State<StaggerIn> createState() => _StaggerInState();
}

class _StaggerInState extends State<StaggerIn> {
  bool _visible = false;

  Duration get _delay {
    final stepped =
        Duration(microseconds: AppMotion.staggerStep.inMicroseconds * widget.index);
    return stepped <= widget.maxDelay ? stepped : widget.maxDelay;
  }

  @override
  void initState() {
    super.initState();
    if (_delay == Duration.zero) {
      _visible = true;
      return;
    }
    Future.delayed(_delay, () {
      if (mounted) setState(() => _visible = true);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!AppMotion.enabled(context)) return widget.child;
    return AnimatedOpacity(
      opacity: _visible ? 1.0 : 0.0,
      duration: AppMotion.entrance,
      curve: AppMotion.easeOut,
      child: AnimatedSlide(
        offset: _visible ? Offset.zero : const Offset(0, 0.06),
        duration: AppMotion.entrance,
        curve: AppMotion.easeOut,
        child: widget.child,
      ),
    );
  }
}

/// One-shot scale pop for success icons and badges.
///
/// Plays once when first built; rebuilds never replay it. Honors reduced
/// motion by rendering statically.
class PopIn extends StatefulWidget {
  const PopIn({super.key, required this.child});

  final Widget child;

  @override
  State<PopIn> createState() => _PopInState();
}

class _PopInState extends State<PopIn> {
  bool _played = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) setState(() => _played = true);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!AppMotion.enabled(context)) return widget.child;
    return AnimatedScale(
      scale: _played ? 1.0 : 0.4,
      duration: const Duration(milliseconds: 280),
      curve: AppMotion.easeOut,
      child: widget.child,
    );
  }
}
