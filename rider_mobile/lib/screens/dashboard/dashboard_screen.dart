import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../core/utils/geolocation.dart';
import '../../models/delivery.dart';
import '../../providers/delivery_provider.dart';
import '../../providers/rider_provider.dart';
import '../../services/rider_service.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/dashboard_card.dart';
import '../../widgets/delivery_card.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/section_header.dart';
import '../../widgets/stagger_in.dart';
import '../deliveries/deliveries_screen.dart';
import '../deliveries/delivery_detail_screen.dart';
import '../earnings/earnings_screen.dart';

/// Rider dashboard: greeting, availability switch, stats, earnings,
/// current delivery and recent completions.
class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final riderProvider = context.watch<RiderProvider>();
    final deliveryProvider = context.watch<DeliveryProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        automaticallyImplyLeading: false,
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await riderProvider.loadDashboard();
          await deliveryProvider.load();
        },
        child: riderProvider.loading && riderProvider.dashboard == null
            ? const LoadingWidget(label: 'Loading dashboard...')
            : riderProvider.error != null && riderProvider.dashboard == null
            ? ErrorView(
                message: riderProvider.error!,
                onRetry: riderProvider.loadDashboard,
              )
            : _buildContent(context, riderProvider, deliveryProvider),
      ),
    );
  }

  Future<void> _toggleAvailability(
    BuildContext context,
    RiderProvider provider,
  ) async {
    final ok = await provider.toggleOnline();
    if (!context.mounted || ok) return;
    final message =
        provider.error ??
        'Unable to update your availability. Please try again.';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        action: SnackBarAction(
          label: 'Settings',
          onPressed: Geolocation.openAppSettings,
        ),
      ),
    );
  }

  Widget _buildContent(
    BuildContext context,
    RiderProvider riderProvider,
    DeliveryProvider deliveryProvider,
  ) {
    final dashboard = riderProvider.dashboard;
    final rider = dashboard?.rider ?? riderProvider.rider;

    if (rider == null) {
      return const LoadingWidget();
    }

    final firstName = rider.name.split(' ').first;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        StaggerIn(
          index: 0,
          child: _Header(
            name: firstName,
            greeting: _greeting(),
            isOnline: rider.isOnline,
            canGoOnline: rider.canGoOnline,
            blockedReason: rider.onlineBlockReason,
            busy: riderProvider.statusBusy,
            onToggle: () => _toggleAvailability(context, riderProvider),
          ),
        ),
        if (rider.isOnline && riderProvider.locationWarning != null) ...[
          const SizedBox(height: 12),
          StaggerIn(
            index: 1,
            child: _LocationWarning(
              message: riderProvider.locationWarning!,
              onOpenSettings: Geolocation.openAppSettings,
              onDismiss: riderProvider.dismissLocationWarning,
            ),
          ),
        ],
        const SizedBox(height: 20),
        const StaggerIn(
          index: 1,
          child: SectionHeader(title: "Today's Deliveries"),
        ),
        const SizedBox(height: 12),
        StaggerIn(
          index: 2,
          child: Row(
            children: [
              Expanded(
                child: DashboardCard(
                  label: 'Assigned',
                  value: '${dashboard?.stats['assigned'] ?? 0}',
                  icon: Icons.assignment_outlined,
                  color: const Color(0xFF1D6FE0),
                  onTap: () => _goToFiltered(context, 'new'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: DashboardCard(
                  label: 'To Pick Up',
                  value: '${dashboard?.stats['to_pick_up'] ?? 0}',
                  icon: Icons.storefront_outlined,
                  color: AppColors.secondary,
                  onTap: () => _goToFiltered(context, 'pickup'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        StaggerIn(
          index: 3,
          child: Row(
            children: [
              Expanded(
                child: DashboardCard(
                  label: 'In Transit',
                  value: '${dashboard?.stats['in_transit'] ?? 0}',
                  icon: Icons.delivery_dining_outlined,
                  color: const Color(0xFF8A4BDF),
                  onTap: () => _goToFiltered(context, 'in_transit'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: DashboardCard(
                  label: 'Completed',
                  value: '${dashboard?.stats['completed'] ?? 0}',
                  icon: Icons.check_circle_outline,
                  color: AppColors.success,
                  onTap: () => _goToFiltered(context, 'delivered'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        if (_isEmptyDashboard(dashboard)) ...[
          const StaggerIn(index: 4, child: _NoActiveDeliveries()),
          const SizedBox(height: 16),
        ],
        StaggerIn(
          index: 4,
          child: _EarningsBanner(
            amount: dashboard?.todayEarnings ?? 0,
            onTap: () => _openEarnings(context),
          ),
        ),
        const SizedBox(height: 16),
        if (dashboard?.currentDelivery != null) ...[
          const StaggerIn(
            index: 5,
            child: SectionHeader(title: 'Current Delivery'),
          ),
          const SizedBox(height: 12),
          StaggerIn(
            index: 6,
            child: DeliveryCard(
              delivery: dashboard!.currentDelivery!,
              onTap: () => _openDelivery(context, dashboard.currentDelivery!),
              trailing: const Icon(
                Icons.chevron_right,
                color: AppColors.textSecondary,
              ),
            ),
          ),
          const SizedBox(height: 8),
        ],
        if (dashboard?.upcomingPickup != null) ...[
          const StaggerIn(
            index: 7,
            child: SectionHeader(title: 'Upcoming Pickup'),
          ),
          const SizedBox(height: 12),
          StaggerIn(
            index: 8,
            child: DeliveryCard(
              delivery: dashboard!.upcomingPickup!,
              onTap: () => _openDelivery(context, dashboard.upcomingPickup!),
              trailing: const Icon(
                Icons.chevron_right,
                color: AppColors.textSecondary,
              ),
            ),
          ),
          const SizedBox(height: 8),
        ],
        if (dashboard != null && dashboard.recentCompleted.isNotEmpty) ...[
          const StaggerIn(
            index: 9,
            child: SectionHeader(title: 'Recent Completed'),
          ),
          const SizedBox(height: 12),
          ...dashboard.recentCompleted
              .take(3)
              .toList()
              .asMap()
              .entries
              .map(
                (entry) => Padding(
                  padding: const EdgeInsets.only(bottom: 0),
                  child: StaggerIn(
                    index: 10 + entry.key,
                    child: DeliveryCard(
                      delivery: entry.value,
                      onTap: () => _openDelivery(context, entry.value),
                      trailing: const Icon(
                        Icons.chevron_right,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                ),
              ),
        ],
        const SizedBox(height: 8),
        StaggerIn(
          index: 13,
          child: OutlinedButton.icon(
            onPressed: () => _goToDeliveriesTab(context),
            icon: const Icon(Icons.inventory_2_outlined),
            label: const Text('View Deliveries'),
          ),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  static String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  }

  bool _isEmptyDashboard(DashboardSummary? dashboard) {
    if (dashboard == null) return false;
    final stats = dashboard.stats;
    final anyStats = [
      'assigned',
      'to_pick_up',
      'in_transit',
      'completed',
    ].any((k) => (stats[k] ?? 0) > 0);
    return !anyStats &&
        dashboard.currentDelivery == null &&
        dashboard.upcomingPickup == null &&
        dashboard.recentCompleted.isEmpty;
  }

  void _goToDeliveriesTab(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const DeliveriesScreen()));
  }

  void _openEarnings(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const EarningsScreen()));
  }

  void _goToFiltered(BuildContext context, String filter) {
    final provider = context.read<DeliveryProvider>();
    provider.setFilter(filter);
    _goToDeliveriesTab(context);
  }

  void _openDelivery(BuildContext context, Delivery delivery) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DeliveryDetailScreen(deliveryId: delivery.id),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.name,
    required this.greeting,
    required this.isOnline,
    required this.canGoOnline,
    required this.busy,
    required this.onToggle,
    this.blockedReason,
  });

  final String name;
  final String greeting;
  final bool isOnline;
  final bool canGoOnline;
  final bool busy;
  final VoidCallback onToggle;
  final String? blockedReason;

  @override
  Widget build(BuildContext context) {
    final initial = name.isNotEmpty ? name.characters.first.toUpperCase() : '?';
    // Hero availability panel: identity, live status, and the online
    // switch grouped on a soft brand tint so the day starts here.
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.primary.withValues(alpha: 0.12),
            AppTheme.primary.withValues(alpha: 0.04),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppTheme.primary.withValues(alpha: 0.16)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppTheme.primary, AppTheme.primaryDark],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withValues(alpha: 0.35),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: Text(
                  initial,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: AppGaps.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$greeting, $name!',
                      style: AppText.title.copyWith(fontSize: 20),
                    ),
                    const SizedBox(height: 4),
                    _StatusPill(isOnline: isOnline),
                  ],
                ),
              ),
              Semantics(
                label: busy
                    ? 'Updating availability'
                    : (isOnline ? 'Go offline' : 'Go online'),
                child: Switch(
                  value: isOnline,
                  onChanged: busy || (!isOnline && !canGoOnline)
                      ? null
                      : (_) => onToggle(),
                  activeThumbColor: Colors.white,
                  activeTrackColor: AppTheme.success,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppGaps.md),
          Row(
            children: [
              Icon(
                isOnline ? Icons.bolt_outlined : Icons.bedtime_outlined,
                size: 14,
                color: AppColors.textSecondary,
              ),
              const SizedBox(width: AppGaps.sm),
              Expanded(
                child: Text(
                  !isOnline && !canGoOnline
                      ? (blockedReason ??
                            'You cannot go online until your account is cleared.')
                      : isOnline
                      ? 'Available for new assignments'
                      : 'Not taking new assignments — you can still finish current deliveries',
                  style: AppText.secondary.copyWith(fontSize: 12),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Explicit online/offline indicator: icon + text + color, so status is never
/// communicated by color alone. Broadcasts to screen readers on change.
class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.isOnline});

  final bool isOnline;

  @override
  Widget build(BuildContext context) {
    final online = isOnline;
    return Semantics(
      liveRegion: true,
      label: online ? 'You are online' : 'You are offline',
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: (online ? AppTheme.success : AppColors.textSecondary)
              .withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              online ? Icons.power_outlined : Icons.power_off_outlined,
              size: 14,
              color: online ? AppTheme.success : AppColors.textSecondary,
            ),
            const SizedBox(width: 6),
            Text(
              online ? 'ONLINE' : 'OFFLINE',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
                color: online ? AppTheme.success : AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Warning shown when the rider is online but device location is off. New
/// job offers stop until location returns, so this is actionable, not a toast.
class _LocationWarning extends StatelessWidget {
  const _LocationWarning({
    required this.message,
    required this.onOpenSettings,
    required this.onDismiss,
  });

  final String message;
  final Future<bool> Function() onOpenSettings;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppGaps.md),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.location_off_outlined,
                  color: AppColors.warning, size: 20),
              const SizedBox(width: AppGaps.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Location is off — no new offers',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(message, style: AppText.secondary.copyWith(fontSize: 12)),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Dismiss warning',
                visualDensity: VisualDensity.compact,
                onPressed: onDismiss,
                icon: const Icon(Icons.close, size: 18),
              ),
            ],
          ),
          const SizedBox(height: AppGaps.sm),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => onOpenSettings(),
                  icon: const Icon(Icons.settings_outlined, size: 18),
                  label: const Text('Open Settings'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(42),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Friendly card shown when there is nothing to deliver right now.
class _NoActiveDeliveries extends StatelessWidget {
  const _NoActiveDeliveries();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: const Row(
        children: [
          IconTile(icon: Icons.inventory_2_outlined),
          SizedBox(width: AppGaps.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('No active deliveries', style: AppText.cardTitle),
                SizedBox(height: 2),
                Text(
                  'New assignments will appear here.',
                  style: AppText.secondary,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EarningsBanner extends StatelessWidget {
  const _EarningsBanner({required this.amount, this.onTap});

  final double amount;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppTheme.primary, AppTheme.primaryDark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      "Today's Earnings",
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      FormatUtils.peso(amount),
                      style: AppText.kpi(30).copyWith(color: Colors.white),
                    ),
                    const SizedBox(height: 12),
                    const Row(
                      children: [
                        Icon(
                          Icons.account_balance_wallet_outlined,
                          color: Colors.white60,
                          size: 14,
                        ),
                        SizedBox(width: 6),
                        Text(
                          'Tap to view your earnings & delivery history',
                          style: TextStyle(color: Colors.white60, fontSize: 12),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: Colors.white70),
            ],
          ),
        ),
      ),
    );
  }
}
