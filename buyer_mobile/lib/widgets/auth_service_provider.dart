import 'package:flutter/widgets.dart';
import '../services/auth_service.dart';

/// Rebuild account-aware widgets when login, profile or token state changes.
class AuthServiceProvider extends InheritedNotifier<AuthService> {
  final AuthService authService;
  const AuthServiceProvider({
    super.key,
    required this.authService,
    required super.child,
  }) : super(notifier: authService);

  static AuthService of(BuildContext context) => context
      .dependOnInheritedWidgetOfExactType<AuthServiceProvider>()!
      .authService;
}
