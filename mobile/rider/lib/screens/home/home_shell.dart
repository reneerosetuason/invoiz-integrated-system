import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_constants.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/delivery_provider.dart';
import '../../providers/earnings_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/rider_provider.dart';
import '../../services/location_service.dart';
import '../auth/login_screen.dart';
import '../dashboard/dashboard_screen.dart';
import '../deliveries/deliveries_screen.dart';
import '../messages/conversations_screen.dart';
import '../notifications/notifications_screen.dart';
import '../profile/profile_screen.dart';
import '../scan/scan_parcel_screen.dart';
import '../settings/settings_screen.dart';
import '../../widgets/liquid_nav_bar.dart';

/// Bottom-navigation shell for the rider app.
///
/// The floating liquid-glass dock holds Home, Deliveries, a prominent Scan
/// action and Settings. Scan opens as a pushed route (never a tab) so the
/// scanner keeps its single-shot pushReplacement flow. Messages lives in
/// the top bar, left of the notifications icon.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> with WidgetsBindingObserver {
  /// Active dock slot: 0 = Home, 1 = Deliveries, 3 = Settings, 4 = Profile.
  /// Slot 2 (Scan) is an action and never becomes the selection.
  int _tab = 0;
  Timer? _locationTimer;
  bool _appResumed = true;

  static const _screens = [
    DashboardScreen(),
    DeliveriesScreen(),
    SettingsScreen(),
    ProfileScreen(),
  ];

  int get _screenIndex => _tab == 3 ? 2 : (_tab == 4 ? 3 : _tab);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _preload();
    _startLocationHeartbeat();
    _redirectOnLogout();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appResumed = state == AppLifecycleState.resumed;
    if (_appResumed) {
      _startLocationHeartbeat();
    } else {
      // The OS may keep the process alive; stop GPS pings while backgrounded.
      _locationTimer?.cancel();
      _locationTimer = null;
    }
  }

  void _redirectOnLogout() {
    context.read<AuthProvider>().addListener(_onAuthChanged);
  }

  void _onAuthChanged() {
    final auth = context.read<AuthProvider>();
    if (auth.status == AuthStatus.unauthenticated && mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    context.read<AuthProvider>().removeListener(_onAuthChanged);
    _locationTimer?.cancel();
    super.dispose();
  }

  void _preload() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      context.read<RiderProvider>().loadDashboard();
      context.read<DeliveryProvider>().load();
      context.read<NotificationProvider>().load();
      context.read<EarningsProvider>().loadSummary();
      context.read<AuthProvider>().clearError();
    });
  }

  void _startLocationHeartbeat() {
    if (!_appResumed) return;
    _locationTimer?.cancel();
    _locationTimer = Timer.periodic(
      const Duration(seconds: AppConstants.locationUpdateInterval),
      (_) => _heartbeat(),
    );
    _heartbeat();
  }

  Future<void> _heartbeat() async {
    if (!mounted || !_appResumed) return;
    final rider = context.read<RiderProvider>();
    if (!rider.isOnline) return;

    // Detect location switched off mid-shift so the rider is warned that new
    // offers have stopped (M13). Never prompts for permission.
    await rider.refreshLocationWarning();
    if (!mounted || !_appResumed) return;

    try {
      final auth = context.read<AuthProvider>();
      final svc = LocationService(auth.api);
      int? deliveryId;
      try {
        deliveryId = context.read<DeliveryProvider>().selected?.id;
      } catch (_) {}
      await svc.report(deliveryId: deliveryId);
    } catch (_) {}
  }

  void _onDockTap(int slot) {
    if (slot == 2) {
      // Scan is an action: open the scanner without changing tabs so its
      // single-shot flow (pushReplacement to delivery detail) is preserved.
      Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => const ScanParcelScreen()));
      return;
    }
    if (slot == 1) context.read<DeliveryProvider>().load();
    setState(() => _tab = slot);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      appBar: AppBar(
        title: const Text('Invoiz Rider'),
        actions: [
          IconButton(
            icon: const Icon(Icons.chat_bubble_outline),
            tooltip: 'Messages',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ConversationsScreen()),
            ),
          ),
          Consumer<NotificationProvider>(
            builder: (_, p, _) => Stack(
              children: [
                IconButton(
                  icon: const Icon(Icons.notifications_outlined),
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const NotificationsScreen(),
                    ),
                  ),
                ),
                if (p.unread > 0)
                  Positioned(
                    right: 6,
                    top: 6,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: AppColors.warning,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        '${p.unread}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
      body: Padding(
        // Clearance so tab content never hides under the floating dock.
        padding: const EdgeInsets.only(bottom: 92),
        child: IndexedStack(index: _screenIndex, children: _screens),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: LiquidNavBar(selectedIndex: _tab, onTap: _onDockTap),
      ),
    );
  }
}
