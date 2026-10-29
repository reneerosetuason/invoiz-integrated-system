import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/stagger_in.dart';
import '../notifications/notifications_screen.dart';
import '../profile/change_password_screen.dart';
import '../profile/profile_screen.dart';
import '../onboarding/rider_tutorial_screen.dart';

/// Simple settings hub: account shortcuts, notifications entry,
/// app version and logout.
class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        automaticallyImplyLeading: false,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const StaggerIn(index: 0, child: SectionEyebrow(text: 'Account')),
          StaggerIn(
            index: 1,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 6,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    InfoTile(
                      icon: Icons.person_outline,
                      iconColor: AppColors.textSecondary,
                      title: 'Profile',
                      subtitle: 'View and edit rider profile',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const ProfileScreen(),
                        ),
                      ),
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    InfoTile(
                      icon: Icons.lock_outline,
                      iconColor: AppColors.textSecondary,
                      title: 'Change Password',
                      subtitle: 'Update your login password',
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
          const StaggerIn(index: 2, child: SectionEyebrow(text: 'General')),
          StaggerIn(
            index: 3,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 6,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    InfoTile(
                      icon: Icons.notifications_outlined,
                      iconColor: AppColors.textSecondary,
                      title: 'Notifications',
                      subtitle: 'View rider notifications',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const NotificationsScreen(),
                        ),
                      ),
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    InfoTile(
                      icon: Icons.help_outline,
                      iconColor: AppColors.textSecondary,
                      title: 'Help & Tutorial',
                      subtitle: 'Replay the rider app introduction',
                      onTap: () {
                        final rider = context.read<AuthProvider>().rider;
                        if (rider == null) return;
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                RiderTutorialScreen(riderId: rider.id),
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 4, endIndent: 4),
                    FutureBuilder<PackageInfo>(
                      future: PackageInfo.fromPlatform(),
                      builder: (context, snapshot) {
                        final info = snapshot.data;
                        return InfoTile(
                          icon: Icons.info_outline,
                          iconColor: AppColors.textSecondary,
                          title: 'App Version',
                          subtitle: info == null
                              ? 'Invoiz Rider'
                              : 'Invoiz Rider ${info.version} (${info.buildNumber})',
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),
          StaggerIn(
            index: 4,
            child: ElevatedButton.icon(
              onPressed: () =>
                  _confirmLogout(context, context.read<AuthProvider>()),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: AppTheme.danger,
                elevation: 0,
                side: BorderSide.none,
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Log Out'),
            ),
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
          'Are you sure you want to log out of Invoiz Rider?',
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
              style: TextStyle(color: AppTheme.danger),
            ),
          ),
        ],
      ),
    );
  }
}
