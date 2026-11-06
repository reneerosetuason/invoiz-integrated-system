import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../models/logistics_scan.dart';
import '../../providers/auth_provider.dart';
import '../../services/logistics_scan_service.dart';
import '../auth/login_screen.dart';
import '../scan/parcel_tracking.dart';

/// Scanning workspace for Logistics Admin, Company Manager, and Logistics
/// Staff accounts in the unified mobile app.
///
/// The facility shown here comes from the authenticated account on the
/// backend. The app never chooses a hub: lookup and confirm are authorized
/// per parcel by LogisticsAccess, and every confirmed scan writes the same
/// delivery status log the Logistics Web shows.
class LogisticsScanScreen extends StatefulWidget {
  const LogisticsScanScreen({super.key, this.cameraEnabled = true});

  /// Test seam: widget tests can render the workspace without a camera.
  final bool cameraEnabled;

  @override
  State<LogisticsScanScreen> createState() => _LogisticsScanScreenState();
}

class _LogisticsScanScreenState extends State<LogisticsScanScreen> {
  late final AuthProvider _auth;
  late final LogisticsScanService _service;
  final _manualController = TextEditingController();
  MobileScannerController? _camera;

  bool _loadingContext = true;
  bool _busy = false;
  LogisticsScanOutcome? _outcome;
  String? _errorText;
  String _lastTracking = '';

  @override
  void initState() {
    super.initState();
    _auth = context.read<AuthProvider>();
    _service = LogisticsScanService(_auth.api);
    _auth.addListener(_onAuthChanged);
    if (widget.cameraEnabled) {
      _camera = MobileScannerController();
    }
    _loadContext();
    _startCamera();
  }

  Future<void> _loadContext() async {
    try {
      final account = await _service.context();
      _auth.updateLogistics(account);
    } catch (_) {
      // Keep the facility from login/cache when the context refresh fails.
    }
    if (mounted) setState(() => _loadingContext = false);
  }

  void _onAuthChanged() {
    if (_auth.status == AuthStatus.unauthenticated && mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  Future<void> _startCamera() async {
    try {
      await _camera?.start();
    } catch (_) {
      // The camera error state offers manual entry.
    }
  }

  Future<void> _stopCamera() async {
    try {
      await _camera?.stop();
    } catch (_) {}
  }

  Future<void> _onDetect(String? raw) async {
    if (_busy || _outcome != null) return;
    final tracking = ParcelTracking.validated(raw);
    if (tracking == null) {
      await _stopCamera();
      if (!mounted) return;
      setState(() {
        _errorText =
            'Invalid parcel QR code. Scan an INVOIZ label or enter the tracking number.';
      });
      return;
    }
    await _lookup(tracking);
  }

  Future<void> _lookup(String tracking) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _errorText = null;
      _outcome = null;
      _lastTracking = tracking;
    });
    await _stopCamera();

    try {
      final outcome = await _service.lookup(tracking);
      if (!mounted) return;
      setState(() {
        _outcome = outcome;
        _busy = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorText = _friendlyError(e);
        _busy = false;
      });
    }
  }

  Future<void> _confirm() async {
    final outcome = _outcome;
    if (_busy || outcome == null || !outcome.eligible) return;

    setState(() => _busy = true);
    try {
      final result = await _service.confirm(_lastTracking);
      if (!mounted) return;
      setState(() {
        _outcome = result;
        _busy = false;
      });
      if (result.confirmed) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result.message),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_friendlyError(e)), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _scanAgain() async {
    setState(() {
      _outcome = null;
      _errorText = null;
      _busy = false;
    });
    if (mounted) await _startCamera();
  }

  Future<void> _enterTrackingManually() async {
    if (_busy) return;
    _manualController.clear();
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Enter tracking number'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Type the number printed under the QR code on the parcel label.',
              style: TextStyle(fontSize: 13),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _manualController,
              autofocus: true,
              textCapitalization: TextCapitalization.characters,
              textInputAction: TextInputAction.done,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z0-9-]')),
                LengthLimitingTextInputFormatter(40),
              ],
              decoration: const InputDecoration(
                labelText: 'Tracking number',
                hintText: 'TRK-YYYYMMDD-XXXX',
              ),
              onSubmitted: (v) => Navigator.of(dialogContext).pop(v.trim()),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(_manualController.text.trim()),
            child: const Text('Look Up'),
          ),
        ],
      ),
    );
    if (value == null || value.isEmpty || !mounted) return;
    final tracking = ParcelTracking.validated(value);
    if (tracking == null) {
      setState(() {
        _errorText = 'Enter a tracking number in the format TRK-YYYYMMDD-XXXX.';
      });
      return;
    }
    await _lookup(tracking);
  }

  String _friendlyError(Object error) {
    final text = error.toString();
    return text.replaceFirst('Exception: ', '');
  }

  @override
  void dispose() {
    _auth.removeListener(_onAuthChanged);
    _camera?.dispose();
    _manualController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final account = _auth.logistics;
    final canScan = account?.canScan ?? false;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: AppColors.primaryDark,
        foregroundColor: Colors.white,
        title: const Text('Logistics Scanner'),
        actions: [
          IconButton(
            tooltip: 'Log out',
            icon: const Icon(Icons.logout),
            onPressed: () => _auth.logout(),
          ),
        ],
      ),
      body: Column(
        children: [
          _FacilityHeader(loading: _loadingContext),
          if (account != null && !canScan)
            const _ReadOnlyBanner()
          else
            Expanded(
              child: _outcome != null || _errorText != null
                  ? _buildResultPanel()
                  : _buildScanner(),
            ),
        ],
      ),
    );
  }

  Widget _buildScanner() {
    return Stack(
      fit: StackFit.expand,
      children: [
        if (_camera != null)
          MobileScanner(
            controller: _camera,
            onDetect: (capture) {
              final raw = capture.barcodes.isEmpty
                  ? null
                  : capture.barcodes.first.rawValue;
              _onDetect(raw);
            },
            errorBuilder: (context, error) => _CameraUnavailable(
              onManualEntry: _enterTrackingManually,
              onRetry: _startCamera,
            ),
          )
        else
          const ColoredBox(color: Colors.black54),

        Center(
          child: Container(
            width: 240,
            height: 240,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.white, width: 4),
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
          ),
        ),
        Positioned(
          left: 24,
          right: 24,
          bottom: 28,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Scan the parcel QR code to verify receipt at your hub',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, fontSize: 14),
              ),
              const SizedBox(height: 12),
              TextButton.icon(
                onPressed: _busy ? null : _enterTrackingManually,
                style: TextButton.styleFrom(
                  foregroundColor: Colors.white,
                  backgroundColor: Colors.black54,
                ),
                icon: const Icon(Icons.keyboard_outlined, size: 18),
                label: const Text('Enter tracking number'),
              ),
            ],
          ),
        ),
        if (_busy)
          Container(
            color: Colors.black.withValues(alpha: 0.55),
            child: const Center(
              child: CircularProgressIndicator(color: Colors.white),
            ),
          ),
      ],
    );
  }

  Widget _buildResultPanel() {
    final outcome = _outcome;

    return Container(
      color: AppColors.background,
      child: ListView(
        padding: const EdgeInsets.all(AppGaps.lg),
        children: [
          if (_errorText != null)
            _MessageCard(
              icon: Icons.error_outline,
              color: AppColors.warning,
              title: 'Unable to scan',
              message: _errorText!,
            ),
          if (outcome != null) _OutcomeCard(outcome: outcome),
          const SizedBox(height: AppGaps.lg),
          if (outcome != null &&
              outcome.eligible &&
              !outcome.already &&
              outcome.parcel != null)
            FilledButton.icon(
              onPressed: _busy ? null : _confirm,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
                backgroundColor: AppTheme.primary,
              ),
              icon: _busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.check_circle_outline),
              label: Text(_busy ? 'Recording scan…' : 'Confirm Scan'),
            ),
          if (outcome != null && outcome.history.isNotEmpty) ...[
            const SizedBox(height: AppGaps.lg),
            _HistoryList(entries: outcome.history),
          ],
          const SizedBox(height: AppGaps.lg),
          OutlinedButton.icon(
            onPressed: _busy ? null : _scanAgain,
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(50),
            ),
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Scan Another Parcel'),
          ),
        ],
      ),
    );
  }
}

class _FacilityHeader extends StatelessWidget {
  const _FacilityHeader({required this.loading});

  final bool loading;

  @override
  Widget build(BuildContext context) {
    final account = context.watch<AuthProvider>().logistics;

    return Container(
      width: double.infinity,
      color: AppColors.primaryDark,
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
      child: Row(
        children: [
          const Icon(Icons.warehouse_outlined, color: Colors.white70),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  loading
                      ? 'Resolving your hub…'
                      : (account?.facilityName.isNotEmpty == true
                            ? account!.facilityName
                            : account?.scopeLabel ?? 'No hub assigned'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  [
                    account?.roleLabel ?? 'Logistics',
                    if (account?.facilityCity.isNotEmpty == true)
                      account!.facilityCity,
                    if (account?.scope != 'hub') account?.scopeLabel ?? '',
                  ].where((part) => part.isNotEmpty).join(' • '),
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ReadOnlyBanner extends StatelessWidget {
  const _ReadOnlyBanner();

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        color: AppColors.background,
        padding: const EdgeInsets.all(24),
        child: Center(
          child: _MessageCard(
            icon: Icons.visibility_outlined,
            color: AppColors.warning,
            title: 'Read-only oversight',
            message:
                'Platform administrators can review parcels in Logistics Web but cannot perform counter scanning. Sign in with a Logistics Admin or Logistics Staff account assigned to a hub.',
          ),
        ),
      ),
    );
  }
}

class _CameraUnavailable extends StatelessWidget {
  const _CameraUnavailable({
    required this.onManualEntry,
    required this.onRetry,
  });

  final VoidCallback onManualEntry;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black,
      padding: const EdgeInsets.all(24),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.videocam_off_outlined,
                color: Colors.white70, size: 42),
            const SizedBox(height: 12),
            const Text(
              'Camera unavailable. Check camera permission or enter the tracking number manually.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: onRetry,
              style: OutlinedButton.styleFrom(foregroundColor: Colors.white),
              child: const Text('Retry camera'),
            ),
            TextButton(
              onPressed: onManualEntry,
              style: TextButton.styleFrom(foregroundColor: Colors.white),
              child: const Text('Enter tracking number'),
            ),
          ],
        ),
      ),
    );
  }
}

class _OutcomeCard extends StatelessWidget {
  const _OutcomeCard({required this.outcome});

  final LogisticsScanOutcome outcome;

  @override
  Widget build(BuildContext context) {
    final parcel = outcome.parcel;
    final success = outcome.confirmed;
    final color = success
        ? AppColors.success
        : outcome.eligible
        ? AppTheme.primary
        : outcome.already
        ? AppColors.gold
        : AppColors.warning;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _MessageCard(
          icon: success
              ? Icons.check_circle
              : outcome.eligible
              ? Icons.inventory_2_outlined
              : outcome.already
              ? Icons.history
              : Icons.info_outline,
          color: color,
          title: success
              ? 'Scan recorded'
              : outcome.eligible
              ? 'Parcel ready to scan'
              : outcome.already
              ? 'Already scanned'
              : 'Not eligible',
          message: outcome.message,
        ),
        if (parcel != null) ...[
          const SizedBox(height: AppGaps.md),
          Container(
            padding: const EdgeInsets.all(AppGaps.lg),
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  parcel.trackingNumber,
                  style: AppText.title.copyWith(fontSize: 18),
                ),
                const SizedBox(height: 10),
                _DetailRow(label: 'Recipient', value: parcel.recipientName),
                _DetailRow(
                  label: 'Parcel status',
                  value: parcel.statusLabel,
                ),
                _DetailRow(
                  label: 'Current hub',
                  value: parcel.currentHub.isEmpty ? '—' : parcel.currentHub,
                ),
                if (parcel.destinationHub.isNotEmpty)
                  _DetailRow(
                    label: 'Destination',
                    value: parcel.destinationHub,
                  ),
                if (parcel.serviceArea.isNotEmpty)
                  _DetailRow(label: 'Service area', value: parcel.serviceArea),
                if (parcel.weight.isNotEmpty)
                  _DetailRow(label: 'Weight', value: '${parcel.weight} kg'),
                if (parcel.scannedAt != null)
                  _DetailRow(label: 'Scanned at', value: parcel.scannedAt!),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 13,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '—' : value,
              style: const TextStyle(
                color: AppColors.textPrimary,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  style: const TextStyle(
                    color: AppColors.textPrimary,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryList extends StatelessWidget {
  const _HistoryList({required this.entries});

  final List<ScanHistoryEntry> entries;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Tracking history',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
          ),
          const SizedBox(height: 10),
          for (final entry in entries.take(5))
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    entry.status.replaceAll('_', ' ').toUpperCase(),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                  Text(
                    [
                      if (entry.location.isNotEmpty) entry.location,
                      if (entry.actor.isNotEmpty) entry.actor,
                      if (entry.createdAt != null)
                        entry.createdAt!.replaceFirst('T', ' ').split('.').first,
                    ].join(' • '),
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                  if (entry.notes.isNotEmpty)
                    Text(
                      entry.notes,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
