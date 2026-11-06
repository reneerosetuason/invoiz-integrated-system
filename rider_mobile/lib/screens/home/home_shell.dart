import 'dart:async';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_constants.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/delivery_provider.dart';
import '../../providers/earnings_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/rider_provider.dart';
import '../../services/hub_courier_service.dart';
import '../../services/location_service.dart';
import '../../services/tutorial_completion_store.dart';
import '../auth/login_screen.dart';
import '../dashboard/dashboard_screen.dart';
import '../deliveries/deliveries_screen.dart';
import '../messages/conversations_screen.dart';
import '../notifications/notifications_screen.dart';
import '../profile/profile_screen.dart';
import '../scan/scan_parcel_screen.dart';
import '../settings/settings_screen.dart';
import '../onboarding/rider_tutorial_screen.dart';
import 'hub_courier_home_screen.dart';
import 'hub_transfers_screen.dart';
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
  /// Active dock slot: 0 = Home/Dashboard, 1 = Deliveries/Transfers, 3 = Settings, 4 = Profile.
  /// Slot 2 (Scan) is an action and never becomes the selection.
  int _tab = 0;
  Timer? _locationTimer;
  bool _appResumed = true;
  bool _tutorialChecked = false;
  late final AuthProvider _auth;

  static const _riderScreens = [
    DashboardScreen(),
    DeliveriesScreen(),
    SettingsScreen(),
    ProfileScreen(),
  ];

  static const _courierScreens = [
    HubCourierHomeScreen(embedded: true),
    HubTransfersScreen(),
    SettingsScreen(),
    ProfileScreen(),
  ];

  static const _courierSlots = [
    LiquidNavSlot(Icons.dashboard_outlined, Icons.dashboard, 'Dashboard', 0),
    LiquidNavSlot(
      Icons.local_shipping_outlined,
      Icons.local_shipping,
      'Transfers',
      1,
    ),
    LiquidNavSlot(Icons.settings_outlined, Icons.settings, 'Settings', 3),
    LiquidNavSlot(Icons.person_outline, Icons.person, 'Profile', 4),
  ];

  int get _screenIndex => _tab == 3 ? 2 : (_tab == 4 ? 3 : _tab);

  @override
  void initState() {
    super.initState();
    _auth = context.read<AuthProvider>();
    WidgetsBinding.instance.addObserver(this);
    _preload();
    if (_auth.courier == null) _startLocationHeartbeat();
    _redirectOnLogout();
    WidgetsBinding.instance.addPostFrameCallback((_) => _maybeShowTutorial());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appResumed = state == AppLifecycleState.resumed;
    if (_appResumed) {
      if (_auth.courier == null) {
        _startLocationHeartbeat();
      }
    } else {
      // The OS may keep the process alive; stop GPS pings while backgrounded.
      _locationTimer?.cancel();
      _locationTimer = null;
    }
  }

  void _redirectOnLogout() {
    _auth.addListener(_onAuthChanged);
  }

  void _onAuthChanged() {
    if (_auth.status == AuthStatus.unauthenticated && mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _auth.removeListener(_onAuthChanged);
    _locationTimer?.cancel();
    super.dispose();
  }

  void _preload() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final auth = context.read<AuthProvider>();
      if (auth.isHubCourier) {
        context.read<NotificationProvider>().load(isCourier: true);
        auth.clearError();
        return;
      }
      context.read<RiderProvider>().loadDashboard();
      context.read<DeliveryProvider>().load();
      context.read<NotificationProvider>().load(isCourier: false);
      context.read<EarningsProvider>().loadSummary();
      auth.clearError();
    });
  }

  Future<void> _maybeShowTutorial() async {
    if (_tutorialChecked || !mounted) return;
    _tutorialChecked = true;
    final auth = context.read<AuthProvider>();
    final workflow = auth.courier != null ? 'hub_courier' : 'delivery_rider';
    if (await const TutorialCompletionStore().isCompleted(workflow: workflow) ||
        !mounted) {
      return;
    }
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => RiderTutorialScreen(
          workflowType: workflow,
          riderId: auth.rider?.id,
        ),
      ),
    );
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

  Future<void> _showCourierScanDialog() async {
    final auth = context.read<AuthProvider>();
    final svc = HubCourierService(auth.api);
    final manual = TextEditingController();
    String? seen;
    await showDialog<void>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Scan Manifest Parcel'),
          content: SizedBox(
            width: 360,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  height: 220,
                  child: MobileScanner(
                    onDetect: (capture) {
                      if (seen != null) return;
                      final value = capture.barcodes.firstOrNull?.rawValue;
                      if (value != null && value.isNotEmpty) {
                        seen = value;
                        Navigator.pop(dialog);
                      }
                    },
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: manual,
                  decoration: const InputDecoration(
                    labelText: 'Or enter tracking number',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                seen = manual.text.trim();
                Navigator.pop(dialog);
              },
              child: const Text('Submit Scan'),
            ),
          ],
        ),
      ),
    );

    final tracking = seen?.trim();
    if (tracking == null || tracking.isEmpty || !mounted) return;

    try {
      final dash = await svc.dashboard();
      final active = dash['active_transfer'];
      if (active == null) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'No active transfer in progress. Please check your assigned transfers.',
            ),
          ),
        );
        return;
      }

      final transferId = active['id'] as int;
      final status = (active['status'] ?? '').toString();
      if (status != 'loading') {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Transfer #$transferId is currently in status "$status". Parcels can only be scanned during Loading.',
            ),
          ),
        );
        return;
      }

      await svc.action(transferId, 'load', body: {'tracking_number': tracking});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Parcel $tracking successfully loaded into transfer #$transferId!',
          ),
          backgroundColor: Colors.green,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Colors.red),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Scan failed: $e'), backgroundColor: Colors.red),
      );
    }
  }

  void _onDockTap(int slot) {
    if (slot == 2) {
      if (context.read<AuthProvider>().isHubCourier) {
        _showCourierScanDialog();
      } else {
        // Scan is an action: open the scanner without changing tabs so its
        // single-shot flow (pushReplacement to delivery detail) is preserved.
        Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const ScanParcelScreen()));
      }
      return;
    }
    if (slot == 1 && !context.read<AuthProvider>().isHubCourier) {
      context.read<DeliveryProvider>().load();
    }
    setState(() => _tab = slot);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final isCourier = auth.isHubCourier;
    final screens = isCourier ? _courierScreens : _riderScreens;

    return Scaffold(
      extendBody: true,
      appBar: AppBar(
        title: Text(isCourier ? 'Invoiz Hub Transport' : 'Invoiz Rider'),
        actions: [
          if (!isCourier)
            IconButton(
              icon: const Icon(Icons.chat_bubble_outline),
              tooltip: 'Messages',
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const ConversationsScreen()),
              ),
            ),
          if (isCourier)
            IconButton(
              icon: const Icon(Icons.help_outline),
              tooltip: 'Help & tutorial',
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) =>
                      const RiderTutorialScreen(workflowType: 'hub_courier'),
                ),
              ),
            ),
          Consumer<NotificationProvider>(
            builder: (_, p, _) => Stack(
              children: [
                IconButton(
                  icon: const Icon(Icons.notifications_outlined),
                  tooltip: 'Notifications',
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
        padding: EdgeInsets.only(
          bottom:
              LiquidNavBar.heightFor(context) +
              12 +
              MediaQuery.viewPaddingOf(context).bottom,
        ),
        child: IndexedStack(index: _screenIndex, children: screens),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: LiquidNavBar(
          selectedIndex: _tab,
          onTap: _onDockTap,
          slots: isCourier ? _courierSlots : null,
          scanLabel: 'Scan',
          scanTooltip: isCourier ? 'Scan Manifest Parcel' : 'Scan Parcel',
        ),
      ),
    );
  }
}
