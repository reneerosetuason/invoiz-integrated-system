import 'package:flutter/widgets.dart';
import '../services/auth_service.dart';

/// Lightweight InheritedWidget that exposes the [AuthService] to the
/// widget tree (avoids pulling in the provider package).
class AuthServiceProvider extends InheritedWidget {
  final AuthService authService;

  const AuthServiceProvider({
    super.key,
    required this.authService,
    required super.child,
  });

  static AuthService of(BuildContext context) {
    final provider = context
        .dependOnInheritedWidgetOfExactType<AuthServiceProvider>();
    return provider!.authService;
  }

  @override
  bool updateShouldNotify(AuthServiceProvider oldWidget) =>
      authService != oldWidget.authService;
}
