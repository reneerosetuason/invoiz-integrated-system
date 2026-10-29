import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'screens/splash_screen.dart';
import 'screens/login_screen.dart';
import 'theme.dart';
import 'services/auth_service.dart';
import 'widgets/auth_service_provider.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final authService = AuthService();
  await authService.loadFromStorage();
  runApp(InvoizApp(authService: authService));
}

class InvoizApp extends StatefulWidget {
  final AuthService authService;

  const InvoizApp({super.key, required this.authService});

  @override
  State<InvoizApp> createState() => _InvoizAppState();
}

class _InvoizAppState extends State<InvoizApp> {
  final _navigator = GlobalKey<NavigatorState>();
  late bool _signedIn;
  @override
  void initState() {
    super.initState();
    _signedIn = widget.authService.isLoggedIn;
    widget.authService.addListener(_authChanged);
  }

  void _authChanged() {
    final signedIn = widget.authService.isLoggedIn;
    if (_signedIn && !signedIn) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && !widget.authService.isLoggedIn)
          _navigator.currentState?.pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const LoginScreen()),
            (_) => false,
          );
      });
    }
    _signedIn = signedIn;
  }

  @override
  void dispose() {
    widget.authService.removeListener(_authChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AuthServiceProvider(
      authService: widget.authService,
      child: CupertinoApp(
        navigatorKey: _navigator,
        title: 'Invoiz',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.cupertino,
        localizationsDelegates: const [DefaultMaterialLocalizations.delegate],
        builder: (context, child) => Theme(
          data: AppTheme.light,
          child: ScaffoldMessenger(child: child!),
        ),
        home: const SplashScreen(),
      ),
    );
  }
}
