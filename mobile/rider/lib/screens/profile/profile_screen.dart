import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/geolocation.dart';
import '../../core/utils/input_validation.dart';
import '../../core/utils/vehicle_icons.dart';
import '../../models/rider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/rider_provider.dart';
import '../../services/vehicle_service.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/section_header.dart';
import '../../widgets/stagger_in.dart';
import '../../widgets/status_badge.dart';
import 'change_password_screen.dart';
import '../earnings/earnings_screen.dart';

/// Rider profile: account info, stats, edit + logout.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final riderProvider = context.watch<RiderProvider>();
    final auth = context.watch<AuthProvider>();
    final rider = riderProvider.rider ?? auth.rider;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        automaticallyImplyLeading: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            tooltip: 'Edit Profile',
            onPressed: rider == null ? null : () => _openEdit(context, rider),
          ),
        ],
      ),
      body: riderProvider.loading && rider == null
          ? const LoadingWidget(label: 'Loading profile...')
          : riderProvider.error != null && rider == null
          ? ErrorView(
              message: riderProvider.error!,
              onRetry: riderProvider.loadProfile,
            )
          : _buildContent(context, riderProvider, auth, rider),
    );
  }

  Widget _buildContent(
    BuildContext context,
    RiderProvider riderProvider,
    AuthProvider auth,
    Rider? rider,
  ) {
    if (rider == null) return const LoadingWidget();

    final name = rider.name;
    final email = rider.email;
    final phone = rider.phone;
    final vehicleType = rider.vehicleType;
    final licensePlate = rider.licensePlate;
    final isOnline = rider.isOnline;
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
              gradient: const LinearGradient(
                colors: [AppTheme.primary, AppTheme.primaryDark],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(AppRadius.lg),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.primary.withValues(alpha: 0.3),
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
                    CircleAvatar(
                      radius: 36,
                      backgroundColor: Colors.white.withValues(alpha: 0.18),
                      child: Text(
                        initials,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: Container(
                        width: 18,
                        height: 18,
                        decoration: BoxDecoration(
                          color: isOnline
                              ? AppTheme.success
                              : AppColors.textSecondary,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppTheme.primaryDark,
                            width: 3,
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
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  email,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 14, color: Colors.white70),
                ),
                const SizedBox(height: AppGaps.md),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 7,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(AppRadius.pill),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        isOnline ? Icons.bolt_outlined : Icons.bedtime_outlined,
                        size: 14,
                        color: Colors.white,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        isOnline ? 'Available for deliveries' : 'Offline',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
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
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.map_outlined,
                  label: 'Service Area',
                  value: rider.serviceArea ?? 'Not provided',
                ),
                const Divider(height: 1, indent: 56),
                _StatusRow(status: rider.status),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
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
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.confirmation_number_outlined,
                  label: 'Plate No.',
                  value: licensePlate ?? 'Not provided',
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.badge_outlined,
                  label: 'License No.',
                  value: rider.licenseNumber ?? 'Not provided',
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.event_outlined,
                  label: 'License Expiry',
                  value: rider.licenseExpiry ?? 'Not provided',
                ),
                const Divider(height: 1, indent: 56),
                _InfoRow(
                  icon: Icons.directions_car_outlined,
                  label: 'Vehicle Reg.',
                  value: rider.vehicleRegistration ?? 'Not provided',
                ),
              ],
            ),
          ),
        ),
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
          child: _DocumentsSection(documents: rider.documents),
        ),
        const SizedBox(height: 16),
        const StaggerIn(index: 3, child: SectionHeader(title: 'Earnings')),
        const SizedBox(height: 10),
        StaggerIn(
          index: 3,
          child: Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              child: InfoTile(
                icon: Icons.savings_outlined,
                iconColor: AppColors.textSecondary,
                title: 'Earnings & Delivery History',
                subtitle: 'Daily, weekly and monthly earnings report',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const EarningsScreen()),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        const StaggerIn(
          index: 3,
          child: SectionHeader(title: 'Account & Security'),
        ),
        const SizedBox(height: 10),
        StaggerIn(
          index: 4,
          child: Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              child: InfoTile(
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
            ),
          ),
        ),
        const SizedBox(height: 16),
        const StaggerIn(
          index: 4,
          child: SectionHeader(title: 'Delivery Stats'),
        ),
        const SizedBox(height: 10),
        StaggerIn(
          index: 5,
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
        const SizedBox(height: 12),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: Text(
            isOnline
                ? 'You are available for new assignments. Going offline stops new deliveries from being assigned to you.'
                : 'Offline means you will not receive new assignments, but you can still complete your current deliveries.',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12.5,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
        ),
        const SizedBox(height: 18),
        ElevatedButton.icon(
          onPressed: () => _confirmLogout(context, auth),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.white,
            foregroundColor: AppTheme.danger,
            elevation: 0,
            side: BorderSide.none,
          ),
          icon: const Icon(Icons.logout),
          label: const Text('Log Out'),
        ),
        const SizedBox(height: 24),
      ],
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

  void _confirmLogout(BuildContext context, AuthProvider auth) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text(
          'Are you sure you want to log out of Invoize Rider? '
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
              style: TextStyle(color: AppTheme.danger),
            ),
          ),
        ],
      ),
    );
  }
}

/// Prominent warning when the rider's driver's license expired or is about
/// to expire, matching the backend's assignment block.
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
                      ? 'You cannot go online or accept new deliveries until your license is renewed and re-verified by your logistics center.'
                      : 'Renew your license before${expiry == null ? ' it expires' : ' $expiry'} and have your logistics center re-verify it.',
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
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          IconTile(
            icon: icon,
            size: 38,
            iconSize: 19,
            color: AppColors.textSecondary,
          ),
          const SizedBox(width: AppGaps.md),
          Text(label, style: AppText.secondary),
          const Spacer(),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: AppText.body.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
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

/// Status row with a coloured badge.
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

/// Documents section — lists each uploaded document with a View button.
class _DocumentsSection extends StatelessWidget {
  const _DocumentsSection({required this.documents});
  final List<RiderDocument> documents;

  @override
  Widget build(BuildContext context) {
    if (documents.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Documents'),
        const SizedBox(height: 10),
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

/// Inline form to edit the rider-editable contact / vehicle details.
///
/// The backend only permits phone, vehicle type and license plate updates, so
/// name, email, license number/expiry and documents stay managed by logistics.
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({
    super.key,
    this.initialPhone = '',
    this.initialVehicle,
    this.initialPlate,
  });

  final String initialPhone;
  final String? initialVehicle;
  final String? initialPlate;

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

  /// Keep the rider's existing value selectable even if logistics has since
  /// retired that vehicle type.
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
    if (_vehicle == null || _vehicle!.isEmpty) {
      _snack('Please select your vehicle type.');
      return;
    }

    final provider = context.read<RiderProvider>();
    final auth = context.read<AuthProvider>();
    final phone = PhilippineMobile.normalize(_phoneController.text);
    final plate = LicensePlate.normalize(_plateController.text);

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
                          'Name, email, license details and documents are '
                          'managed by your logistics center. Contact them to '
                          'update those.',
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
