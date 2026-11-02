import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/geolocation.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/stagger_in.dart';
import '../messages/conversations_screen.dart';
import '../notifications/notifications_screen.dart';
import '../onboarding/rider_tutorial_screen.dart';
import '../profile/change_password_screen.dart';
import '../profile/profile_screen.dart';

/// Rider settings hub: account shortcuts, notification preferences,
/// privacy information, operational support, and logout.
class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final isHubCourier = auth.isHubCourier;
    final riderTitle = isHubCourier ? 'Hub Transport Driver' : 'Delivery Rider';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        automaticallyImplyLeading: false,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ── Section 1: Account & Security ──
          const StaggerIn(
            index: 0,
            child: SectionEyebrow(text: 'Account & Security'),
          ),
          StaggerIn(
            index: 1,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    InfoTile(
                      icon: Icons.person_outline,
                      iconColor: AppTheme.primary,
                      title: 'Profile Information',
                      subtitle: 'View credentials, vehicle details, and edit contact info',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const ProfileScreen(),
                        ),
                      ),
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    InfoTile(
                      icon: Icons.lock_outline,
                      iconColor: AppTheme.primary,
                      title: 'Change Password',
                      subtitle: 'Update your account login password',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const ChangePasswordScreen(),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // ── Section 2: Notification Preferences ──
          const StaggerIn(
            index: 2,
            child: SectionEyebrow(text: 'Notification Preferences'),
          ),
          StaggerIn(
            index: 3,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    InfoTile(
                      icon: Icons.notifications_outlined,
                      iconColor: AppTheme.primary,
                      title: 'Notification Center',
                      subtitle: 'View operational announcements and delivery alerts',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const NotificationsScreen(),
                        ),
                      ),
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    InfoTile(
                      icon: Icons.tune_outlined,
                      iconColor: AppTheme.primary,
                      title: 'Alert Preferences',
                      subtitle: 'Operational dispatch alerts and in-transit updates',
                      onTap: () => _showNotificationPreferences(context, isHubCourier),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // ── Section 3: Privacy Information ──
          const StaggerIn(
            index: 4,
            child: SectionEyebrow(text: 'Privacy & Security'),
          ),
          StaggerIn(
            index: 5,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    InfoTile(
                      icon: Icons.security_outlined,
                      iconColor: AppTheme.primary,
                      title: 'Privacy Policy & Data Usage',
                      subtitle: 'How GPS tracking, photos, and personal data are protected',
                      onTap: () => _showPrivacyDialog(context),
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    InfoTile(
                      icon: Icons.perm_device_information_outlined,
                      iconColor: AppTheme.primary,
                      title: 'Device Permissions',
                      subtitle: 'Location and camera access settings',
                      onTap: () => _showPermissionsDialog(context),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // ── Section 4: Help & Support ──
          const StaggerIn(
            index: 6,
            child: SectionEyebrow(text: 'Help & Support'),
          ),
          StaggerIn(
            index: 7,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    InfoTile(
                      icon: Icons.help_outline,
                      iconColor: AppColors.secondary,
                      title: 'Workflow Tutorial',
                      subtitle: 'Replay the step-by-step $riderTitle tutorial',
                      onTap: () {
                        final rider = auth.rider;
                        final workflow = auth.courier != null
                            ? 'hub_courier'
                            : 'delivery_rider';
                        if (rider == null && auth.courier == null) return;
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => RiderTutorialScreen(
                              riderId: rider?.id,
                              workflowType: workflow,
                            ),
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    InfoTile(
                      icon: Icons.headset_mic_outlined,
                      iconColor: AppColors.secondary,
                      title: 'Logistics Support Desk',
                      subtitle: 'Contact dispatchers and system administration',
                      onTap: () => _showSupportDialog(context),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // ── Section 5: App Version & Logout ──
          StaggerIn(
            index: 8,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: FutureBuilder<PackageInfo>(
                  future: PackageInfo.fromPlatform(),
                  builder: (context, snapshot) {
                    final info = snapshot.data;
                    return InfoTile(
                      icon: Icons.info_outline,
                      iconColor: AppColors.textSecondary,
                      title: 'App Version',
                      subtitle: info == null
                          ? 'Invoiz Rider'
                          : 'Invoiz Rider v${info.version} (${info.buildNumber})',
                    );
                  },
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),

          StaggerIn(
            index: 9,
            child: ElevatedButton.icon(
              onPressed: () => _confirmLogout(context, auth),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: AppTheme.danger,
                elevation: 0,
                side: const BorderSide(color: Color(0xFFFEE2E2), width: 1),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Log Out', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  void _showNotificationPreferences(BuildContext context, bool isHubCourier) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.lg)),
      ),
      builder: (bottomContext) => StatefulBuilder(
        builder: (context, setModalState) {
          return Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Notification Preferences',
                  style: AppText.title.copyWith(fontSize: 18),
                ),
                const SizedBox(height: 4),
                Text(
                  'Invoiz Rider sends real-time push alerts during your active shifts.',
                  style: AppText.secondary,
                ),
                const SizedBox(height: 16),
                _AlertPrefTile(
                  title: isHubCourier ? 'Transfer Assignments' : 'New Delivery Assignments',
                  subtitle: isHubCourier
                      ? 'Alerts when hub-to-hub cargo transfers are ready for dispatch'
                      : 'Alerts when parcels are assigned for pickup and drop-off',
                  initialValue: true,
                ),
                const Divider(height: 1),
                const _AlertPrefTile(
                  title: 'Emergency & Exception Alerts',
                  subtitle: 'Urgent notices about route changes, weather alerts, and return orders',
                  initialValue: true,
                ),
                const Divider(height: 1),
                const _AlertPrefTile(
                  title: 'Remittance & Earnings Payouts',
                  subtitle: 'Updates on cash turnover reconciliation and earnings transfers',
                  initialValue: true,
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => Navigator.of(bottomContext).pop(),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                    ),
                    child: const Text('Done'),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  void _showPrivacyDialog(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.shield_outlined, color: AppTheme.primary),
            const SizedBox(width: 8),
            const Text('Privacy & Data Usage'),
          ],
        ),
        content: const SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Location Tracking Policy',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              SizedBox(height: 4),
              Text(
                'Invoiz Rider collects precise GPS location data exclusively while you are toggled ONLINE or actively executing a delivery/transfer. Location tracking immediately stops when you go offline.',
                style: TextStyle(fontSize: 13, height: 1.4, color: AppColors.textSecondary),
              ),
              SizedBox(height: 12),
              Text(
                'Document & Photo Security',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              SizedBox(height: 4),
              Text(
                'Uploaded identification cards, driver\'s licenses, and proof of delivery photos are stored on secure private storage and protected with short-lived cryptographic signed links. Your personal data is never sold or shared with third parties.',
                style: TextStyle(fontSize: 13, height: 1.4, color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Understood', style: TextStyle(color: AppTheme.primary)),
          ),
        ],
      ),
    );
  }

  void _showPermissionsDialog(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Device Permissions'),
        content: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.location_on_outlined, color: AppTheme.primary),
              title: Text('Location Permission', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
              subtitle: Text('Required for real-time dispatch and route tracking', style: TextStyle(fontSize: 12)),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.camera_alt_outlined, color: AppTheme.primary),
              title: Text('Camera & Storage', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
              subtitle: Text('Required for Proof-of-Delivery photos and profile avatar updates', style: TextStyle(fontSize: 12)),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Close'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              Geolocation.openAppSettings();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primary,
              foregroundColor: Colors.white,
            ),
            child: const Text('Open Settings'),
          ),
        ],
      ),
    );
  }

  void _showSupportDialog(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Logistics Support'),
        content: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Need operational assistance during your delivery run?',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            const ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.support_agent, color: AppTheme.primary),
              title: Text('Central Dispatch Hotline', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
              subtitle: Text('0917-123-4567 / (02) 8123-4567', style: TextStyle(fontSize: 13)),
            ),
            const ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.email_outlined, color: AppTheme.primary),
              title: Text('Support Email', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
              subtitle: Text('logistics-support@invoiz.test', style: TextStyle(fontSize: 13)),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(dialogContext).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ConversationsScreen(),
                    ),
                  );
                },
                icon: const Icon(Icons.chat_bubble_outline),
                label: const Text('Open Support Messages'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.primary,
                  side: const BorderSide(color: AppTheme.primary),
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  void _confirmLogout(BuildContext context, AuthProvider auth) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text(
          'Are you sure you want to log out of Invoiz Rider? '
          'You will be set offline first.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              auth.logout();
            },
            child: const Text(
              'Log Out',
              style: TextStyle(color: AppTheme.danger, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

class _AlertPrefTile extends StatefulWidget {
  const _AlertPrefTile({
    required this.title,
    required this.subtitle,
    required this.initialValue,
  });

  final String title;
  final String subtitle;
  final bool initialValue;

  @override
  State<_AlertPrefTile> createState() => _AlertPrefTileState();
}

class _AlertPrefTileState extends State<_AlertPrefTile> {
  late bool _val;

  @override
  void initState() {
    super.initState();
    _val = widget.initialValue;
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.title,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 2),
                Text(
                  widget.subtitle,
                  style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
          Switch(
            value: _val,
            activeColor: AppTheme.primary,
            onChanged: (newVal) => setState(() => _val = newVal),
          ),
        ],
      ),
    );
  }
}
