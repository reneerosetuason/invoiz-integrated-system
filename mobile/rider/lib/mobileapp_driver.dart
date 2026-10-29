import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/network/api_client.dart';
import 'core/storage/token_storage.dart';
import 'core/theme/app_theme.dart';
import 'providers/auth_provider.dart';
import 'providers/delivery_provider.dart';
import 'providers/earnings_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/remittance_provider.dart';
import 'providers/rider_provider.dart';
import 'screens/auth/forgot_password_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/splash/splash_screen.dart';
import 'services/deep_link_service.dart';
import 'services/delivery_service.dart';
import 'services/earnings_service.dart';
import 'services/notification_service.dart';
import 'services/remittance_service.dart';
import 'services/rider_service.dart';
import 'widgets/deep_link_broker.dart';

void mainFromDriver() {
  WidgetsFlutterBinding.ensureInitialized();

  final api = ApiClient();
  final storage = TokenStorage();
  final authProvider = AuthProvider(api: api, storage: storage);
  final navKey = GlobalKey<NavigatorState>();
  final routeTracker = RouteNameTracker();
  final startupDeepLinkGate = StartupDeepLinkGate();

  runApp(InvoizeRiderApp(
    authProvider: authProvider,
    navKey: navKey,
    routeTracker: routeTracker,
    startupDeepLinkGate: startupDeepLinkGate,
    deepLinks: AppLinksDeepLinkService(),
  ));
}

class InvoizeRiderApp extends StatelessWidget {
  const InvoizeRiderApp({
    super.key,
    required this.authProvider,
    required this.navKey,
    required this.routeTracker,
    required this.startupDeepLinkGate,
    this.deepLinks,
  });

  final AuthProvider authProvider;
  final GlobalKey<NavigatorState> navKey;
  final RouteNameTracker routeTracker;
  final StartupDeepLinkGate startupDeepLinkGate;
  final DeepLinkService? deepLinks;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
        ChangeNotifierProvider<RiderProvider>(
          create: (_) => RiderProvider(RiderService(authProvider.api)),
        ),
        ChangeNotifierProvider<DeliveryProvider>(
          create: (_) => DeliveryProvider(DeliveryService(authProvider.api)),
        ),
        ChangeNotifierProvider<NotificationProvider>(
          create: (_) =>
              NotificationProvider(NotificationService(authProvider.api)),
        ),
        ChangeNotifierProvider<EarningsProvider>(
          create: (_) =>
              EarningsProvider(EarningsService(authProvider.api)),
        ),
        ChangeNotifierProvider<RemittanceProvider>(
          create: (_) =>
              RemittanceProvider(RemittanceService(authProvider.api)),
        ),
      ],
      child: MaterialApp(
        title: 'Invoize Rider',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        navigatorKey: navKey,
        navigatorObservers: [routeTracker],
        onGenerateRoute: (settings) {
          if (settings.name == DeepLinkService.loginRouteName) {
            return MaterialPageRoute(
              settings: settings,
              builder: (_) => const LoginScreen(),
            );
          }
          if (settings.name == DeepLinkService.resetRouteName) {
            final args = settings.arguments;
            final map = args is Map ? args : const {};
            return MaterialPageRoute(
              settings: settings,
              builder: (_) => ForgotPasswordScreen(
                initialEmail: map['email'] as String?,
                initialToken: map['token'] as String?,
              ),
            );
          }
          return null;
        },
        builder: (context, child) => AppDeepLinkBroker(
          service: deepLinks,
          navKey: navKey,
          routeTracker: routeTracker,
          startupGate: startupDeepLinkGate,
          child: child,
        ),
        home: AppGate(startupDeepLinkGate: startupDeepLinkGate),
      ),
    );
  }
}

/// Routes to the splash, which restores the session and redirects.
class AppGate extends StatelessWidget {
  const AppGate({super.key, required this.startupDeepLinkGate});

  final StartupDeepLinkGate startupDeepLinkGate;

  @override
  Widget build(BuildContext context) {
    return SplashScreen(startupDeepLinkGate: startupDeepLinkGate);
  }
}
