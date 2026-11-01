import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_theme.dart';
import 'application_status_screen.dart';
import 'apply_screen.dart';
import 'hub_transport_driver_apply_screen.dart';

/// Screen allowing applicants to choose between:
/// 1. Delivery Rider (motorcycle, 2-wheel customer & seller deliveries)
/// 2. Hub Transport Driver (four-wheel trucks, inter-hub linehaul transport)
class ApplicationTypeSelectScreen extends StatelessWidget {
  const ApplicationTypeSelectScreen({
    super.key,
    this.apiClient,
    this.picker = const DocFilePicker(),
  });

  final ApiClient? apiClient;
  final DocFilePicker picker;

  void _navigateToApply(BuildContext context, String applicationType) {
    if (applicationType == 'hub_transport_driver') {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => HubTransportDriverApplyScreen(
            apiClient: apiClient,
            picker: picker,
          ),
        ),
      );
    } else {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ApplyScreen(
            initialApplicationType: 'delivery_rider',
            apiClient: apiClient,
            picker: picker,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Apply as a Rider'),
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Choose Application Type',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.primary,
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Join the INVOIZ Fleet',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Select the logistics role that matches your vehicle and preferred service type.',
                style: TextStyle(
                  fontSize: 14,
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 24),

              // Option 1: Delivery Rider
              _RoleCard(
                title: 'Delivery Rider',
                subtitle: 'Customer Deliveries & Seller Pickups',
                description:
                    'Deliver parcels directly to customers and collect shipments from sellers within your designated service area using your 2-wheel motorcycle or vehicle.',
                badge: '2-Wheel / Motorcycle',
                badgeColor: AppTheme.primary,
                icon: Icons.two_wheeler_outlined,
                features: const [
                  'Handles seller pickups.',
                  'Handles last-mile deliveries to buyers.',
                  'Uses the Delivery Rider application and workflow.',
                  'Use your personal motorcycle / vehicle',
                ],
                onTap: () => _navigateToApply(context, 'delivery_rider'),
              ),

              const SizedBox(height: 18),

              // Option 2: Hub Transport Driver
              _RoleCard(
                title: 'Hub Transport Driver',
                subtitle: 'Inter-Hub Linehaul Transport',
                description:
                    'Transport bulk parcel consignments between INVOIZ Logistics Hubs using logistics trucks or approved vehicles.',
                badge: 'Four-Wheel Fleet Truck',
                badgeColor: const Color(0xFF0E4A57),
                icon: Icons.local_shipping_outlined,
                features: const [
                  'Handles inter-hub parcel transfers.',
                  'Uses logistics trucks or approved vehicles.',
                  'Uses the Hub Transport Driver application and workflow.',
                  'Company-provided or driver-owned truck options',
                ],
                onTap: () => _navigateToApply(context, 'hub_transport_driver'),
              ),

              const SizedBox(height: 28),

              // Status Check Link
              Center(
                child: TextButton.icon(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const ApplicationStatusScreen(),
                      ),
                    );
                  },
                  icon: const Icon(Icons.search, size: 18),
                  label: const Text(
                    'Already applied? Check your application status',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  style: TextButton.styleFrom(
                    foregroundColor: AppTheme.primary,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoleCard extends StatelessWidget {
  const _RoleCard({
    required this.title,
    required this.subtitle,
    required this.description,
    required this.badge,
    required this.badgeColor,
    required this.icon,
    required this.features,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final String description;
  final String badge;
  final Color badgeColor;
  final IconData icon;
  final List<String> features;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      elevation: 0,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border, width: 1.2),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: badgeColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(icon, color: badgeColor, size: 28),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                title,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          subtitle,
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: badgeColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: badgeColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: badgeColor.withValues(alpha: 0.2)),
                ),
                child: Text(
                  badge,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: badgeColor,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                description,
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 14),
              const Divider(height: 1, color: AppColors.border),
              const SizedBox(height: 12),
              for (final feature in features) ...[
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.check_circle_rounded,
                        size: 16,
                        color: badgeColor,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          feature,
                          style: const TextStyle(
                            fontSize: 12.5,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: onTap,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: badgeColor,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Flexible(
                        child: Text(
                          'Apply as $title',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Icon(Icons.arrow_forward, size: 16),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
