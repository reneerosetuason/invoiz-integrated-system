import 'dart:async';

import 'package:flutter/material.dart';

import '../services/deep_link_service.dart';

/// Holds cold-start and live links until startup onboarding has completed.
/// Links are dispatched in arrival order once [release] is called.
class StartupDeepLinkGate {
  bool _released = false;
  final List<Uri> _pending = [];
  final Set<ValueChanged<Uri>> _listeners = {};

  void addListener(ValueChanged<Uri> listener) {
    _listeners.add(listener);
    _flush();
  }

  void removeListener(ValueChanged<Uri> listener) {
    _listeners.remove(listener);
  }

  void submit(Uri uri) {
    _pending.add(uri);
    _flush();
  }

  void release() {
    _released = true;
    _flush();
  }

  void _flush() {
    if (!_released || _listeners.isEmpty || _pending.isEmpty) return;
    final pending = List<Uri>.of(_pending);
    _pending.clear();
    for (final uri in pending) {
      for (final listener in List<ValueChanged<Uri>>.of(_listeners)) {
        listener(uri);
      }
    }
  }
}

/// Tracks the top-most route so deep links cannot stack duplicate login
/// screens on top of each other.
class RouteNameTracker extends NavigatorObserver {
  Route<dynamic>? current;

  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    current = route;
  }

  @override
  void didPop(Route<dynamic>? route, Route<dynamic>? previousRoute) {
    current = previousRoute;
  }

  @override
  void didRemove(Route<dynamic> route, Route<dynamic>? previousRoute) {
    current = previousRoute;
  }

  @override
  void didReplace({Route<dynamic>? newRoute, Route<dynamic>? oldRoute}) {
    current = newRoute;
  }
}

/// Restored deep-link handler: after the first frame it resolves the
/// cold-start link and subscribes to live links, opening the login screen
/// for every valid `invoizrider://login` link.
class AppDeepLinkBroker extends StatefulWidget {
  const AppDeepLinkBroker({
    super.key,
    required this.navKey,
    required this.routeTracker,
    this.service,
    this.startupGate,
    this.child,
  });

  final GlobalKey<NavigatorState> navKey;
  final RouteNameTracker routeTracker;
  final DeepLinkService? service;
  final StartupDeepLinkGate? startupGate;
  final Widget? child;

  @override
  State<AppDeepLinkBroker> createState() => _AppDeepLinkBrokerState();
}

class _AppDeepLinkBrokerState extends State<AppDeepLinkBroker> {
  StreamSubscription<Uri>? _linksSubscription;
  StreamSubscription<Uri>? _resetSubscription;

  @override
  void initState() {
    super.initState();
    widget.startupGate?.addListener(_handleLink);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final service = widget.service;
      if (service == null) return;
      // Subscribe before reading the cold-start link so links arriving during
      // startup are queued instead of being lost.
      _linksSubscription = service.loginLinks.listen(_receiveLink);
      _resetSubscription = service.resetLinks.listen(_receiveLink);
      final initial = await service.initialLink;
      if (mounted && initial != null) _receiveLink(initial);
      final initialReset = await service.initialResetLink;
      if (mounted && initialReset != null) _receiveLink(initialReset);
    });
  }

  void _receiveLink(Uri uri) {
    final gate = widget.startupGate;
    if (gate == null) {
      _handleLink(uri);
    } else {
      gate.submit(uri);
    }
  }

  void _handleLink(Uri uri) {
    if (DeepLinkService.isLoginLink(uri)) {
      _openLogin(uri);
    } else if (DeepLinkService.isResetLink(uri)) {
      _openReset(uri);
    }
  }

  void _openLogin(Uri uri) {
    if (!DeepLinkService.isLoginLink(uri)) return;
    if (widget.routeTracker.current?.settings.name ==
        DeepLinkService.loginRouteName) {
      return;
    }
    widget.navKey.currentState?.pushNamed(DeepLinkService.loginRouteName);
  }

  void _openReset(Uri uri) {
    final credentials = DeepLinkService.resetCredentials(uri);
    if (credentials == null) return;
    if (widget.routeTracker.current?.settings.name ==
        DeepLinkService.resetRouteName) {
      return;
    }
    widget.navKey.currentState?.pushNamed(
      DeepLinkService.resetRouteName,
      arguments: {'email': credentials.email, 'token': credentials.token},
    );
  }

  @override
  void dispose() {
    widget.startupGate?.removeListener(_handleLink);
    _linksSubscription?.cancel();
    _resetSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child ?? const SizedBox.shrink();
}
