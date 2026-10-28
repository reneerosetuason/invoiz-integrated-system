import 'package:flutter/material.dart';
import 'screens/splash_screen.dart';
import 'theme.dart';
import 'services/auth_service.dart';
import 'widgets/auth_service_provider.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final authService = AuthService();
  await authService.loadFromStorage();
  runApp(InvoizApp(authService: authService));
}

class InvoizApp extends StatelessWidget {
  final AuthService authService;

  const InvoizApp({super.key, required this.authService});

  @override
  Widget build(BuildContext context) {
    return AuthServiceProvider(
      authService: authService,
      child: MaterialApp(
        title: 'Invoiz',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        home: const SplashScreen(),
      ),
    );
  }
}
