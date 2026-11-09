import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/geolocation.dart';
import '../../core/utils/input_validation.dart';
import '../../core/utils/vehicle_icons.dart';
import '../../models/hub_courier.dart';
import '../../models/rider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/rider_provider.dart';
import '../../services/hub_courier_service.dart';
import '../../services/vehicle_service.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/section_header.dart';
import '../../widgets/stagger_in.dart';
import '../../widgets/status_badge.dart';
import 'change_password_screen.dart';
import 'my_documents_screen.dart';
import '../earnings/earnings_screen.dart';
import '../onboarding/rider_tutorial_screen.dart';

/// Photo picker abstraction so widget tests can drive photo selection
/// without depending on platform-native channels.
class ProfilePhotoPicker {
  const ProfilePhotoPicker();

  Future<({Uint8List bytes, String filename})?> pickPhoto() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
    );
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.first;
    final bytes =
        file.bytes ??
        (file.path != null ? await file.xFile.readAsBytes() : null);
    if (bytes == null || bytes.isEmpty) return null;
    return (bytes: bytes, filename: file.name);
  }
}

/// Unified Profile Screen supporting both Delivery Riders and Hub Transport Drivers.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    this.photoPicker = const ProfilePhotoPicker(),
  });

  final ProfilePhotoPicker photoPicker;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _uploadingPhoto = false;

  @override
  Widget build(BuildContext context) {
    final riderProvider = context.watch<RiderProvider>();
    final auth = context.watch<AuthProvider>();
    final rider = riderProvider.rider ?? auth.rider;
    final courier = auth.courier;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        automaticallyImplyLeading: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            tooltip: 'Edit Profile',
            onPressed: (rider == null && courier == null)
                ? null
                : () {
                    if (courier != null) {
                      _openCourierEdit(context, courier);
                    } else if (rider != null) {
                      _openEdit(context, rider);
                    }
                  },
          ),
        ],
      ),
      body: _buildBody(context, riderProvider, auth, rider, courier),
    );
  }

  Widget _buildBody(
    BuildContext context,
    RiderProvider riderProvider,
    AuthProvider auth,
    Rider? rider,
    HubCourier? courier,
  ) {
    if (courier != null) {
      return _buildCourierContent(context, auth, courier);
    }

    if (riderProvider.loading && rider == null) {
      return const LoadingWidget(label: 'Loading profile...');
    }

    if (riderProvider.error != null && rider == null) {
      return ErrorView(
        message: riderProvider.error!,
        onRetry: riderProvider.loadProfile,
      );
    }

    if (rider == null) {
      return const LoadingWidget();
    }

    return _buildRiderContent(context, riderProvider, auth, rider);
  }

  // ── Delivery Rider View ──
  Widget _buildRiderContent(
    BuildContext context,
    RiderProvider riderProvider,
    AuthProvider auth,
    Rider rider,
  ) {
    final name = rider.name;
    final email = rider.email;
    final phone = rider.phone;
    final vehicleType = rider.vehicleType;
    final licensePlate = rider.licensePlate;
    final isOnline = rider.isOnline;
    final avatarUrl = rider.avatar;
    final initials = name
        .split(' ')
        .where((p) => p.isNotEmpty)
        .map((p) => p[0])
        .take(2)
        .join()
        .toUpperCase();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        StaggerIn(
          index: 0,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppGaps.xl),
            decoration: BoxDecoration(
              color: AppColors.card,
              border: Border.all(color: AppColors.border),
              borderRadius: BorderRadius.circular(AppRadius.lg),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.primary.withValues(alpha: 0.02),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Column(
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    _AvatarDisplay(
                      avatarUrl: avatarUrl,
                      initials: initials,
                      isBusy: _uploadingPhoto,
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: GestureDetector(
                        onTap: _uploadingPhoto
                            ? null
                            : () => _uploadRiderPhoto(
                                context,
                                riderProvider,
                                auth,
                              ),
                        child: Container(
                          width: 28,
                          height: 28,
                          decoration: BoxDecoration(
                            color: AppColors.secondary,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: AppColors.primaryDark,
                              width: 2,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.2),
                                blurRadius: 4,
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.camera_alt,
                            size: 14,
                            color: AppColors.primaryDark,
                          ),
                        ),
                      ),
                    ),
                    Positioned(
                      left: 0,
                      bottom: 0,
                      child: Container(
                        width: 16,
                        height: 16,
                        decoration: BoxDecoration(
                          color: isOnline
                              ? AppTheme.success
                              : AppColors.textSecondary,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppTheme.primaryDark,
                            width: 2.5,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppGaps.md),
                Text(
                  name,
                  textAlign: TextAlign.center,
                  style: AppText.title.copyWith(
                    fontSize: 20,
                    color: AppColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  email,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.tint,
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.two_wheeler,
                            size: 13,
                            color: AppColors.primaryDark,
                          ),
                          SizedBox(width: 4),
                          Text(
                            'Delivery Rider',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.tint,
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            isOnline
                                ? Icons.bolt_outlined
                                : Icons.bedtime_outlined,
                            size: 13,
                            color: AppColors.primaryDark,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            isOnline ? 'Available' : 'Offline',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Personal Information
        StaggerIn(
          index: 1,
          child: Card(
            child: Column(
              children: [
                _InfoRow(
                  icon: Icons.phone_outlined,
                  label: 'Phone',
                  value: phone.isEmpty ? 'Not provided' : phone,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.email_outlined,
                  label: 'Email',
                  value: email.isEmpty ? 'Not provided' : email,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.business_outlined,
                  label: 'Company',
                  value: rider.company ?? 'Not provided',
                  isProtected: true,
                ),
                if (rider.hubName != null) ...[
                  const Divider(height: 1, indent: 56),
                  _InfoRow(
                    icon: Icons.warehouse_outlined,
                    label: 'Assigned Hub',
                    value: rider.hubName!,
                    isProtected: true,
                  ),
                ],
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.map_outlined,
                  label: 'Service Area',
                  value: rider.serviceArea ?? 'Not provided',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _StatusRow(status: rider.status),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Vehicle & License Information (Approval-controlled)
        const StaggerIn(
          index: 2,
          child: SectionHeader(title: 'Vehicle & License'),
        ),
        const SizedBox(height: 10),
        StaggerIn(
          index: 2,
          child: Card(
            child: Column(
              children: [
                _InfoRow(
                  icon: vehicleIconFor(vehicleType),
                  label: 'Vehicle',
                  value: vehicleType ?? 'Not provided',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.confirmation_number_outlined,
                  label: 'Plate No.',
                  value: licensePlate ?? 'Not provided',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.badge_outlined,
                  label: 'License No.',
                  value: rider.licenseNumber ?? 'Not provided',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.event_outlined,
                  label: 'License Expiry',
                  value: rider.licenseExpiry ?? 'Not provided',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.directions_car_outlined,
                  label: 'Vehicle Reg.',
                  value: rider.vehicleRegistration ?? 'Not provided',
                  isProtected: true,
                ),
              ],
            ),
          ),
        ),

        // Admin-Controlled Protection Notice
        const SizedBox(height: 12),
        const _ProtectedNotice(),

        if (rider.licenseExpired || rider.licenseExpiringSoon) ...[
          const SizedBox(height: 16),
          StaggerIn(
            index: 2,
            child: _LicenseNotice(
              expired: rider.licenseExpired,
              expiry: rider.licenseExpiry,
            ),
          ),
        ],

        const SizedBox(height: 16),
        StaggerIn(
          index: 2,
          child: _DocumentsSection(
            documents: rider.documents,
            onManage: () => _openMyDocuments(context, riderProvider),
          ),
        ),
        const SizedBox(height: 16),

        const StaggerIn(
          index: 3,
          child: SectionHeader(title: 'Delivery Stats'),
        ),
        const SizedBox(height: 10),
        StaggerIn(
          index: 3,
          child: Row(
            children: [
              Expanded(
                child: _StatCard(
                  label: 'Total',
                  value: '${rider.totalDeliveries}',
                  color: const Color(0xFF1D6FE0),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatCard(
                  label: 'Completed',
                  value: '${rider.completedDeliveries}',
                  color: AppTheme.success,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatCard(
                  label: 'Failed',
                  value: '${rider.failedDeliveries}',
                  color: AppTheme.danger,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),

        OutlinedButton.icon(
          onPressed:
              riderProvider.statusBusy || (!isOnline && !rider.canGoOnline)
              ? null
              : () => _toggleAvailability(context, riderProvider),
          style: OutlinedButton.styleFrom(
            foregroundColor: isOnline ? AppTheme.danger : AppTheme.success,
            side: BorderSide(
              color: isOnline ? AppTheme.danger : AppTheme.success,
              width: 1.5,
            ),
          ),
          icon: riderProvider.statusBusy
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Icon(
                  isOnline
                      ? Icons.power_settings_new
                      : Icons.play_circle_outline,
                ),
          label: Text(isOnline ? 'Go Offline' : 'Go Online'),
        ),
        const SizedBox(height: 16),
      ],
    );
  }

  // ── Hub Transport Driver View ──
  Widget _buildCourierContent(
    BuildContext context,
    AuthProvider auth,
    HubCourier courier,
  ) {
    final name = courier.name;
    final email = courier.email;
    final phone = courier.phone;
    final isOnline = courier.online;
    final avatarUrl = courier.avatar;
    final vehicle = courier.vehicle;
    final initials = name
        .split(' ')
        .where((p) => p.isNotEmpty)
        .map((p) => p[0])
        .take(2)
        .join()
        .toUpperCase();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        StaggerIn(
          index: 0,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppGaps.xl),
            decoration: BoxDecoration(
              color: AppColors.card,
              border: Border.all(color: AppColors.border),
              borderRadius: BorderRadius.circular(AppRadius.lg),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.primary.withValues(alpha: 0.02),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Column(
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    _AvatarDisplay(
                      avatarUrl: avatarUrl,
                      initials: initials,
                      isBusy: _uploadingPhoto,
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: GestureDetector(
                        onTap: _uploadingPhoto
                            ? null
                            : () => _uploadCourierPhoto(context, auth),
                        child: Container(
                          width: 28,
                          height: 28,
                          decoration: BoxDecoration(
                            color: AppColors.secondary,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: AppColors.primaryDark,
                              width: 2,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.2),
                                blurRadius: 4,
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.camera_alt,
                            size: 14,
                            color: AppColors.primaryDark,
                          ),
                        ),
                      ),
                    ),
                    Positioned(
                      left: 0,
                      bottom: 0,
                      child: Container(
                        width: 16,
                        height: 16,
                        decoration: BoxDecoration(
                          color: isOnline
                              ? AppTheme.success
                              : AppColors.textSecondary,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppTheme.primaryDark,
                            width: 2.5,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppGaps.md),
                Text(
                  name,
                  textAlign: TextAlign.center,
                  style: AppText.title.copyWith(
                    fontSize: 20,
                    color: AppColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  email,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.tint,
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.local_shipping_outlined,
                            size: 13,
                            color: AppColors.primaryDark,
                          ),
                          SizedBox(width: 4),
                          Text(
                            'Hub Transport Driver',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.tint,
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            isOnline
                                ? Icons.bolt_outlined
                                : Icons.bedtime_outlined,
                            size: 13,
                            color: AppColors.primaryDark,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            isOnline ? 'Online' : 'Offline',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: AppColors.primaryDark,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Personal Information Card
        StaggerIn(
          index: 1,
          child: Card(
            child: Column(
              children: [
                _InfoRow(
                  icon: Icons.phone_outlined,
                  label: 'Phone',
                  value: phone.isEmpty ? 'Not provided' : phone,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.email_outlined,
                  label: 'Email',
                  value: email.isEmpty ? 'Not provided' : email,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.business_outlined,
                  label: 'Company',
                  value: courier.company.isEmpty
                      ? 'Not provided'
                      : courier.company,
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.warehouse_outlined,
                  label: 'Home Hub',
                  value: courier.homeHub?['name']?.toString() ?? 'Not assigned',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.local_shipping_outlined,
                  label: 'Arrangement',
                  value: courier.isDriverOwned
                      ? 'Driver-Owned Truck'
                      : 'Company Fleet',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _StatusRow(
                  status: courier.status.isEmpty ? 'active' : courier.status,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Vehicle & Freight Capacity
        const StaggerIn(
          index: 2,
          child: SectionHeader(title: 'Freight Vehicle & Compliance'),
        ),
        const SizedBox(height: 10),
        StaggerIn(
          index: 2,
          child: Card(
            child: Column(
              children: [
                _InfoRow(
                  icon: Icons.local_shipping,
                  label: 'Vehicle',
                  value:
                      vehicle?['name']?.toString() ??
                      (courier.isDriverOwned
                          ? 'Driver-Owned Freight Truck'
                          : 'Company Truck'),
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.confirmation_number_outlined,
                  label: 'Plate No.',
                  value:
                      vehicle?['plate_number']?.toString() ??
                      'Pending Assignment',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.badge_outlined,
                  label: 'License No.',
                  value: courier.licenseNumber ?? 'Not provided',
                  isProtected: true,
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.event_outlined,
                  label: 'License Expiry',
                  value: courier.licenseExpiry == null
                      ? 'Not provided'
                      : (courier.licenseValid
                            ? courier.licenseExpiry!
                            : '${courier.licenseExpiry} (expired)'),
                  isProtected: true,
                ),
                if (vehicle != null) ...[
                  const Divider(height: 1, indent: 56),
                  _InfoRow(
                    icon: Icons.inventory_2_outlined,
                    label: 'Capacity',
                    value:
                        '${vehicle['parcel_capacity'] ?? 0} parcels (${vehicle['capacity_kg'] ?? 0} kg)',
                    isProtected: true,
                  ),
                ],
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.verified_outlined,
                  label: 'Verification',
                  value: courier.verificationStatus == 'approved'
                      ? 'Approved'
                      : (courier.verificationStatus == 'rejected'
                            ? 'Rejected'
                            : 'Pending Review'),
                  isProtected: true,
                ),
              ],
            ),
          ),
        ),

        if (courier.documents.isNotEmpty)
          _DocumentsSection(documents: courier.documents),

        // Admin-Controlled Protection Notice
        const SizedBox(height: 12),
        const _ProtectedNotice(),
        const SizedBox(height: 24),
      ],
    );
  }

  Future<void> _uploadRiderPhoto(
    BuildContext context,
    RiderProvider provider,
    AuthProvider auth,
  ) async {
    setState(() => _uploadingPhoto = true);
    try {
      final picked = await widget.photoPicker.pickPhoto();
      if (picked == null) return;

      final ok = await provider.uploadPhoto(
        fileBytes: picked.bytes,
        filename: picked.filename,
      );

      if (!context.mounted) return;
      if (ok && provider.rider != null) {
        auth.updateRider(provider.rider!);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile photo updated successfully.')),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(provider.error ?? 'Unable to upload photo.')),
        );
      }
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to upload photo. Please try again.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
  }

  Future<void> _uploadCourierPhoto(
    BuildContext context,
    AuthProvider auth,
  ) async {
    setState(() => _uploadingPhoto = true);
    try {
      final picked = await widget.photoPicker.pickPhoto();
      if (picked == null) return;

      final updated = await HubCourierService(
        auth.api,
      ).uploadPhoto(fileBytes: picked.bytes, filename: picked.filename);

      if (!context.mounted) return;
      auth.updateCourier(updated);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profile photo updated successfully.')),
      );
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to upload photo. Please try again.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
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

  void _openEdit(BuildContext context, Rider rider) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => EditProfileScreen(
          initialPhone: rider.phone,
          initialVehicle: rider.vehicleType,
          initialPlate: rider.licensePlate,
        ),
      ),
    );
  }

  Future<void> _openMyDocuments(
    BuildContext context,
    RiderProvider riderProvider,
  ) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const MyDocumentsScreen()),
    );
    if (mounted) await riderProvider.loadProfile();
  }

  void _openCourierEdit(BuildContext context, HubCourier courier) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            EditProfileScreen(initialPhone: courier.phone, isCourier: true),
      ),
    );
  }
}

class _AvatarDisplay extends StatelessWidget {
  const _AvatarDisplay({
    required this.avatarUrl,
    required this.initials,
    required this.isBusy,
  });

  final String? avatarUrl;
  final String initials;
  final bool isBusy;

  @override
  Widget build(BuildContext context) {
    if (isBusy) {
      return const CircleAvatar(
        radius: 36,
        backgroundColor: AppColors.tint,
        child: CircularProgressIndicator(
          color: AppColors.primaryDark,
          strokeWidth: 2.5,
        ),
      );
    }

    if (avatarUrl != null && avatarUrl!.isNotEmpty) {
      return CircleAvatar(
        radius: 36,
        backgroundColor: AppColors.tint,
        child: ClipOval(
          child: Image.network(
            avatarUrl!,
            width: 72,
            height: 72,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => _initialsWidget(),
          ),
        ),
      );
    }

    return _initialsWidget();
  }

  Widget _initialsWidget() {
    return CircleAvatar(
      radius: 36,
      backgroundColor: AppColors.tint,
      child: Text(
        initials,
        style: const TextStyle(
          fontFamily: 'Inter',
          color: AppColors.primaryDark,
          fontSize: 26,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _ProtectedNotice extends StatelessWidget {
  const _ProtectedNotice();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      color: AppColors.surfaceSoft,
      child: const Padding(
        padding: EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.lock_outline, size: 18, color: AppColors.textSecondary),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'Company, center assignments, and vehicle approval status are managed by Logistics Administration and cannot be changed directly.',
                style: TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LicenseNotice extends StatelessWidget {
  const _LicenseNotice({required this.expired, this.expiry});

  final bool expired;
  final String? expiry;

  @override
  Widget build(BuildContext context) {
    final color = expired ? AppTheme.danger : AppColors.secondary;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppGaps.md),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            expired ? Icons.error_outline : Icons.warning_amber_outlined,
            color: color,
          ),
          const SizedBox(width: AppGaps.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  expired
                      ? 'Driver\'s license expired'
                      : 'Driver\'s license expiring soon',
                  style: AppText.body.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(
                  expired
                      ? 'You cannot go online or accept new deliveries until your license is renewed and re-verified by your Logistics Hub.'
                      : 'Renew your license before${expiry == null ? ' it expires' : ' $expiry'} and have your Logistics Hub re-verify it.',
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

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.isProtected = false,
  });
  final IconData icon;
  final String label;
  final String value;
  final bool isProtected;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        IconTile(
          icon: icon,
          size: 38,
          iconSize: 19,
          color: AppColors.textSecondary,
        ),
        const SizedBox(width: AppGaps.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(label, style: AppText.caption)),
                  if (isProtected)
                    const Padding(
                      padding: EdgeInsets.only(left: 6),
                      child: Icon(
                        Icons.lock_outline,
                        size: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 3),
              Text(
                value,
                style: AppText.body.copyWith(fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        child: Column(
          children: [
            Text(value, style: AppText.kpi(22).copyWith(color: color)),
            const SizedBox(height: 2),
            Text(label, style: AppText.caption),
          ],
        ),
      ),
    );
  }
}

class _StatusRow extends StatelessWidget {
  const _StatusRow({this.status});
  final String? status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          IconTile(
            icon: Icons.verified_user_outlined,
            size: 38,
            iconSize: 19,
            color: AppColors.textSecondary,
          ),
          const SizedBox(width: AppGaps.md),
          Text('Status', style: AppText.secondary),
          const Spacer(),
          StatusBadge(status: status ?? 'unknown'),
        ],
      ),
    );
  }
}

class _DocumentsSection extends StatelessWidget {
  const _DocumentsSection({required this.documents, this.onManage});

  final List<RiderDocument> documents;

  /// Riders open the My Documents screen from here. Hub Transport Drivers
  /// keep the read-only list they had before.
  final VoidCallback? onManage;

  @override
  Widget build(BuildContext context) {
    if (documents.isEmpty && onManage == null) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(
          title: 'Documents',
          action: onManage == null
              ? null
              : TextButton(
                  onPressed: onManage,
                  child: const Text('My Documents'),
                ),
        ),
        const SizedBox(height: 10),
        if (documents.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'No approved documents are on file.',
                style: AppText.secondary,
              ),
            ),
          )
        else
          Card(
            child: Column(
              children: [
                for (int i = 0; i < documents.length; i++) ...[
                  if (i > 0) const Divider(height: 1, indent: 16),
                  _DocumentRow(doc: documents[i]),
                ],
              ],
            ),
          ),
        const SizedBox(height: 16),
      ],
    );
  }
}

class _DocumentRow extends StatefulWidget {
  const _DocumentRow({required this.doc});
  final RiderDocument doc;

  @override
  State<_DocumentRow> createState() => _DocumentRowState();
}

class _DocumentRowState extends State<_DocumentRow> {
  bool _opening = false;

  Future<void> _open() async {
    final url = widget.doc.url;
    if (url == null || url.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Document URL is not available.')),
      );
      return;
    }
    final uri = Uri.tryParse(url);
    if (uri == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Invalid document URL.')));
      return;
    }
    setState(() => _opening = true);
    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open the document.')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to open the document.')),
        );
      }
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final doc = widget.doc;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          IconTile(
            icon: doc.isImage
                ? Icons.image_outlined
                : Icons.description_outlined,
            size: 38,
            iconSize: 19,
            color: AppColors.textSecondary,
          ),
          const SizedBox(width: AppGaps.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  doc.label,
                  style: AppText.body.copyWith(fontWeight: FontWeight.w600),
                ),
                Text(
                  doc.name.isEmpty ? 'Unknown file' : doc.name,
                  style: AppText.secondary,
                  overflow: TextOverflow.ellipsis,
                ),
                if (doc.size != null) Text(doc.size!, style: AppText.caption),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _opening
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : TextButton(
                  onPressed: doc.url != null ? _open : null,
                  child: const Text('View'),
                ),
        ],
      ),
    );
  }
}

/// Form to edit permitted rider personal information (e.g. phone number).
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({
    super.key,
    this.initialPhone = '',
    this.initialVehicle,
    this.initialPlate,
    this.isCourier = false,
  });

  final String initialPhone;
  final String? initialVehicle;
  final String? initialPlate;
  final bool isCourier;

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _phoneController;
  late final TextEditingController _plateController;
  String? _vehicle;
  bool _loadingVehicles = true;
  bool _saved = false;
  List<VehicleType> _vehicles = _fallbackVehicles;

  static const _fallbackVehicles = [
    VehicleType(name: 'motorcycle', label: 'Motorcycle', capacityKg: 30),
    VehicleType(name: 'bicycle', label: 'Bicycle', capacityKg: 10),
    VehicleType(name: 'car', label: 'Car / Sedan', capacityKg: 100),
    VehicleType(name: 'van', label: 'Van', capacityKg: 300),
    VehicleType(name: 'truck', label: 'Truck', capacityKg: 500),
    VehicleType(name: 'tricycle', label: 'Tricycle', capacityKg: 50),
  ];

  @override
  void initState() {
    super.initState();
    _phoneController = TextEditingController(text: widget.initialPhone);
    _plateController = TextEditingController(
      text: LicensePlate.normalize(widget.initialPlate),
    );
    _vehicle = widget.initialVehicle;
    _vehicles = _withCurrent(_fallbackVehicles);
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadVehicles());
  }

  Future<void> _loadVehicles() async {
    try {
      final api = context.read<AuthProvider>().api;
      final list = await VehicleService(api).getActive();
      if (!mounted) return;
      final usable = list.isEmpty ? _fallbackVehicles : list;
      setState(() {
        _vehicles = _withCurrent(usable);
        _loadingVehicles = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _vehicles = _withCurrent(_fallbackVehicles);
        _loadingVehicles = false;
      });
    }
  }

  List<VehicleType> _withCurrent(List<VehicleType> list) {
    final current = _vehicle;
    if (current == null || current.isEmpty) return list;
    if (list.any((v) => v.name == current)) return list;
    return [...list, VehicleType(name: current, label: current, capacityKg: 0)];
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _plateController.dispose();
    super.dispose();
  }

  bool get _dirty =>
      _phoneController.text.trim() != widget.initialPhone.trim() ||
      _plateController.text.trim() !=
          LicensePlate.normalize(widget.initialPlate) ||
      _vehicle != widget.initialVehicle;

  String? _validatePhone(String? value) => PhilippineMobile.validate(value);

  String? _validatePlate(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    return LicensePlate.validate(value);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (!widget.isCourier && (_vehicle == null || _vehicle!.isEmpty)) {
      _snack('Please select your vehicle type.');
      return;
    }

    final provider = context.read<RiderProvider>();
    final auth = context.read<AuthProvider>();
    final phone = PhilippineMobile.normalize(_phoneController.text);
    final plate = LicensePlate.normalize(_plateController.text);

    if (widget.isCourier || auth.isHubCourier) {
      try {
        final updated = await HubCourierService(
          auth.api,
        ).updateProfile(phone: phone);
        if (!mounted) return;
        _saved = true;
        auth.updateCourier(updated);
        _snack('Profile updated.');
        Navigator.of(context).pop();
        return;
      } catch (_) {
        if (!mounted) return;
        _snack('Unable to update profile.');
        return;
      }
    }

    final ok = await provider.updateProfile(
      phone: phone,
      vehicleType: _vehicle,
      licensePlate: plate.isEmpty ? null : plate,
    );

    if (!mounted) return;

    if (ok) {
      _saved = true;
      final updated = provider.rider;
      if (updated != null) auth.updateRider(updated);
      _snack('Profile updated.');
      Navigator.of(context).pop();
    } else {
      _snack(provider.error ?? 'Unable to update profile.');
    }
  }

  Future<bool> _confirmDiscard() async {
    final discard = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Discard changes?'),
        content: const Text(
          'You have unsaved profile changes. Leave without saving?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Keep Editing'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Discard'),
          ),
        ],
      ),
    );
    return discard ?? false;
  }

  void _snack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RiderProvider>();

    return PopScope(
      canPop: !_dirty || _saved,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop || !mounted) return;
        if (await _confirmDiscard() && context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Edit Profile')),
        body: Form(
          key: _formKey,
          autovalidateMode: AutovalidateMode.onUserInteraction,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.telephoneNumber],
                maxLength: 16,
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9+\s()-]')),
                ],
                decoration: const InputDecoration(
                  labelText: 'Phone Number',
                  hintText: '09171234567',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
                validator: _validatePhone,
              ),
              const SizedBox(height: 8),
              if (!widget.isCourier) ...[
                DropdownButtonFormField<String>(
                  initialValue: _vehicle,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: 'Vehicle Type',
                    prefixIcon: Icon(vehicleIconFor(_vehicle)),
                    helperText: _loadingVehicles
                        ? 'Loading available vehicle types…'
                        : null,
                  ),
                  items: _vehicles
                      .map(
                        (v) => DropdownMenuItem(
                          value: v.name,
                          child: Text(
                            v.capacityKg > 0
                                ? '${v.label} (${v.capacityKg.toStringAsFixed(0)} kg)'
                                : v.label,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: _loadingVehicles
                      ? null
                      : (v) => setState(() => _vehicle = v),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _plateController,
                  textCapitalization: TextCapitalization.characters,
                  maxLength: 20,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z0-9 -]')),
                    UpperCaseTextFormatter(),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'License Plate',
                    hintText: 'ABC 1234',
                    prefixIcon: Icon(Icons.confirmation_number_outlined),
                  ),
                  validator: _validatePlate,
                ),
                const SizedBox(height: 8),
              ],
              Card(
                elevation: 0,
                color: AppColors.surfaceSoft,
                child: const Padding(
                  padding: EdgeInsets.all(12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.info_outline,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Name, email, company, and facility assignments are managed by your Logistics Hub administration. Contact them to request administrative updates.',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
            child: ElevatedButton.icon(
              onPressed: provider.statusBusy ? null : _save,
              icon: provider.statusBusy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.save_outlined),
              label: Text(provider.statusBusy ? 'Saving...' : 'Save Changes'),
            ),
          ),
        ),
      ),
    );
  }
}
