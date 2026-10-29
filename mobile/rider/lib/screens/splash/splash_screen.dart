import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/deep_link_service.dart';
import '../../services/tutorial_completion_store.dart';
import '../../widgets/deep_link_broker.dart';
import '../auth/login_screen.dart';
import '../home/home_shell.dart';
import '../onboarding/rider_tutorial_screen.dart';

/// Splash screen: restores the session, then routes to login or the shell.
class SplashScreen extends StatefulWidget {
  const SplashScreen({
    super.key,
    this.startupDeepLinkGate,
    this.tutorialStore = const TutorialCompletionStore(),
  });

  final StartupDeepLinkGate? startupDeepLinkGate;
  final TutorialCompletionStore tutorialStore;

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _bootstrapStarted = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    if (_bootstrapStarted) return;
    _bootstrapStarted = true;
    final auth = context.read<AuthProvider>();
    try {
      await auth.restoreSession().timeout(const Duration(seconds: 5));
    } catch (_) {}
    if (!mounted) return;

    final tutorialCompleted = await widget.tutorialStore
        .isCompleted()
        .catchError((_) => false);
    if (!mounted) return;
    if (!tutorialCompleted) {
      await Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (_) => const RiderTutorialScreen(),
        ),
      );
    }
    if (!mounted) return;

    final target = auth.isAuthenticated ? const HomeShell() : const LoginScreen();
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        settings: auth.isAuthenticated
            ? null
            : const RouteSettings(name: DeepLinkService.loginRouteName),
        builder: (_) => target,
      ),
    );
    // Let the route observer see the real destination before queued links are
    // released, avoiding a duplicate login push during the replacement.
    await WidgetsBinding.instance.endOfFrame;
    widget.startupDeepLinkGate?.release();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.primary,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 92,
              height: 92,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
              ),
              padding: const EdgeInsets.all(12),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Image.asset(
                  'images/logo.png',
                  fit: BoxFit.contain,
                ),
              ),
            ),
            const SizedBox(height: 24),
            const Text(
              'Invoize Rider',
              style: TextStyle(
                color: Colors.white,
                fontSize: 26,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Delivering happiness, on time.',
              style: TextStyle(color: Colors.white70, fontSize: 14),
            ),
            const SizedBox(height: 40),
            const SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(
                strokeWidth: 3,
                color: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
