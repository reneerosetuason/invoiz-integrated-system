import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../core/utils/input_validation.dart';
import '../../models/delivery.dart';
import '../../providers/delivery_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/status_badge.dart';
import '../scan/parcel_tracking.dart';

/// Shop pickup screen: verify the parcel, enter pickup PIN if required, and
/// confirm. Pickup is only accepted after the waybill tracking number matches
/// this job, scanned or typed (A6/M14).
class PickupScreen extends StatefulWidget {
  const PickupScreen({
    super.key,
    required this.deliveryId,
    this.cameraEnabled = true,
  });

  final int deliveryId;

  /// Test seam: when false the camera path is skipped and manual entry is
  /// offered immediately so widget tests never touch a platform camera.
  final bool cameraEnabled;

  @override
  State<PickupScreen> createState() => _PickupScreenState();
}

class _PickupScreenState extends State<PickupScreen> {
  final _pinController = TextEditingController();
  final _trackingController = TextEditingController();

  bool _verified = false;
  bool _verifying = false;
  String? _trackingError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<DeliveryProvider>();
      if (provider.selected?.id != widget.deliveryId) {
        provider.loadDetail(widget.deliveryId);
      }
    });
  }

  @override
  void dispose() {
    _pinController.dispose();
    _trackingController.dispose();
    super.dispose();
  }

  bool _alreadyPickedUp(Delivery delivery) =>
      delivery.pickedUpAt != null || delivery.status == 'picked_up';

  /// Compare a scanned/typed value with this job's tracking number. A
  /// mismatch is rejected locally and never reaches the pickup endpoint.
  bool _verifyTracking(String raw, {bool announce = true}) {
    final delivery = context.read<DeliveryProvider>().selected;
    if (delivery == null) return false;

    final value = ParcelTracking.normalize(raw);
    if (value.isEmpty) {
      setState(() {
        _verified = false;
        _trackingError =
            'Scan the waybill or type the tracking number to verify this parcel.';
      });
      if (announce) {
        _snack(_trackingError!);
      }
      return false;
    }

    final expected = ParcelTracking.normalize(delivery.trackingNumber);
    if (value != expected) {
      setState(() {
        _verified = false;
        _trackingError = 'Tracking number does not match this job.';
      });
      if (announce) {
        _snack(
          'Tracking number does not match this job. '
          'Check that you are holding ${delivery.trackingNumber}.',
        );
      }
      return false;
    }

    setState(() {
      _verified = true;
      _trackingError = null;
      _trackingController.text = value;
      _trackingController.selection = TextSelection.collapsed(
        offset: value.length,
      );
    });
    if (announce) _snack('Parcel verified. You can confirm pickup.');
    return true;
  }

  Future<void> _scanWaybill() async {
    if (_verifying) return;

    // Widget tests (and builds without a camera) go straight to manual entry.
    if (!widget.cameraEnabled) {
      await _enterTrackingManually();
      return;
    }

    setState(() => _verifying = true);
    PermissionStatus status;
    try {
      status = await Permission.camera.request();
    } catch (_) {
      status = PermissionStatus.denied;
    }
    if (!mounted) return;
    setState(() => _verifying = false);

    if (!status.isGranted && !status.isLimited) {
      _snackWithSettings(
        status.isPermanentlyDenied
            ? 'Camera access is blocked. Enable it in Settings, or type the '
                  'tracking number instead.'
            : 'Camera access is needed to scan the waybill. You can type the '
                  'tracking number instead.',
      );
      return;
    }

    final raw = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.black,
      builder: (_) => const _WaybillScannerSheet(),
    );
    if (!mounted || raw == null || raw.isEmpty) return;
    _verifyTracking(raw);
  }

  Future<void> _enterTrackingManually() async {
    final controller = TextEditingController(text: _trackingController.text);
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Enter tracking number'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Type the number printed under the QR code on the waybill.',
              style: TextStyle(fontSize: 13),
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('pickupManualTrackingField'),
              controller: controller,
              autofocus: true,
              textCapitalization: TextCapitalization.characters,
              textInputAction: TextInputAction.done,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z0-9-]')),
                UpperCaseTextFormatter(),
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
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Verify'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (!mounted || value == null || value.isEmpty) return;
    _verifyTracking(value);
  }

  Future<void> _confirmPickup() async {
    final provider = context.read<DeliveryProvider>();
    final delivery = provider.selected;
    if (delivery == null) return;

    if (_alreadyPickedUp(delivery)) {
      _snack('This parcel is already picked up. Pickup cannot be repeated.');
      return;
    }

    // Typing the correct number and tapping Confirm is enough (RID-PICK-003);
    // a wrong or empty value blocks the request (RID-PICK-004).
    if (!_verified && !_verifyTracking(_trackingController.text)) {
      return;
    }

    String? pin;
    if (delivery.pickupPinRequired) {
      pin = _pinController.text.trim();
      if (pin.length != 4) {
        _snack('Please enter the 4-digit pickup PIN.');
        return;
      }
    }

    final ok = await provider.pickup(delivery.id, pickupPin: pin);

    if (!mounted) return;

    if (ok) {
      _snack('Pickup confirmed. Proceed with the delivery.');
      Navigator.of(context).pop();
    } else {
      _snack(provider.error ?? 'Unable to confirm pickup.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<DeliveryProvider>();
    final delivery = provider.selected;

    if (provider.loading && delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Shop Pickup')),
        body: const LoadingWidget(),
      );
    }

    if (delivery == null || delivery.id != widget.deliveryId) {
      return Scaffold(
        appBar: AppBar(title: const Text('Shop Pickup')),
        body: ErrorView(
          message: provider.error ?? 'Unable to load the delivery.',
          onRetry: () => provider.loadDetail(widget.deliveryId),
        ),
      );
    }

    final alreadyPickedUp = _alreadyPickedUp(delivery);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Shop Pickup'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (alreadyPickedUp) ...[
            _AlreadyPickedUpBanner(delivery: delivery),
            const SizedBox(height: AppGaps.lg),
          ],
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppGaps.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Order', style: AppText.eyebrow),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          delivery.trackingNumber,
                          style: AppText.tracking,
                        ),
                      ),
                      StatusBadge(
                        status: delivery.status,
                        label: delivery.statusLabel,
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      const IconTile(
                        icon: Icons.storefront_outlined,
                        size: 36,
                        iconSize: 18,
                      ),
                      const SizedBox(width: AppGaps.md),
                      Expanded(
                        child: Text(
                          delivery.shop.name,
                          style: AppText.cardTitle.copyWith(fontSize: 14),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  if (delivery.shop.address != null &&
                      delivery.shop.address!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    _PickupInfoRow(
                      icon: Icons.place_outlined,
                      label: 'Pickup address',
                      value: delivery.shop.address!,
                    ),
                  ],
                  if (delivery.shop.phone != null &&
                      delivery.shop.phone!.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    _PickupInfoRow(
                      icon: Icons.call_outlined,
                      label: 'Contact number',
                      value: delivery.shop.phone!,
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: AppGaps.lg),
          const SectionEyebrow(text: 'Items to Pick Up'),
          const SizedBox(height: AppGaps.sm),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: delivery.items.isEmpty
                    ? const [
                        Text(
                          'No items listed.',
                          style: TextStyle(color: AppColors.textSecondary),
                        ),
                      ]
                    : delivery.items
                        .map(
                          (item) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 6),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.check_circle,
                                  color: AppTheme.success,
                                  size: 18,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    '${item.name} x${item.quantity}',
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                                Text(
                                  FormatUtils.peso(item.subtotal),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                        .toList(),
              ),
            ),
          ),
          const SizedBox(height: AppGaps.xl),
          const SectionEyebrow(text: 'Scan & Verify Parcel'),
          const SizedBox(height: AppGaps.xs),
          const Text(
            'Scan the waybill QR code or type the tracking number. '
            'Pickup is blocked unless it matches this job.',
            style: AppText.secondary,
          ),
          const SizedBox(height: AppGaps.md),
          OutlinedButton.icon(
            onPressed: alreadyPickedUp || _verifying ? null : _scanWaybill,
            icon: _verifying
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.qr_code_scanner_outlined),
            label: Text(_verifying ? 'Opening camera…' : 'Scan Waybill'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
            ),
          ),
          const SizedBox(height: AppGaps.md),
          TextField(
            key: const Key('pickupTrackingField'),
            controller: _trackingController,
            enabled: !alreadyPickedUp,
            textCapitalization: TextCapitalization.characters,
            textInputAction: TextInputAction.done,
            maxLength: 40,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z0-9-]')),
              UpperCaseTextFormatter(),
              LengthLimitingTextInputFormatter(40),
            ],
            decoration: InputDecoration(
              labelText: 'Tracking number',
              hintText: 'TRK-YYYYMMDD-XXXX',
              counterText: '',
              errorText: _trackingError,
            ),
            onChanged: (_) {
              if (_verified || _trackingError != null) {
                setState(() {
                  _verified = false;
                  _trackingError = null;
                });
              }
            },
            onSubmitted: alreadyPickedUp ? null : _verifyTracking,
          ),
          const SizedBox(height: AppGaps.sm),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: alreadyPickedUp
                      ? null
                      : () => _verifyTracking(_trackingController.text),
                  icon: const Icon(Icons.verified_outlined, size: 18),
                  label: const Text('Verify Parcel'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(46),
                  ),
                ),
              ),
              if (_verified) ...[
                const SizedBox(width: AppGaps.md),
                const _VerifiedChip(),
              ],
            ],
          ),
          if (delivery.pickupPinRequired) ...[
            const SizedBox(height: AppGaps.xl),
            const SectionEyebrow(text: 'Pickup Verification'),
            const Text(
              'Enter the pickup PIN provided by the shop.',
              style: AppText.secondary,
            ),
            const SizedBox(height: AppGaps.md),
            TextField(
              controller: _pinController,
              enabled: !alreadyPickedUp,
              keyboardType: TextInputType.number,
              maxLength: 4,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w800,
                letterSpacing: 12,
              ),
              decoration: const InputDecoration(
                hintText: '0000',
                counterText: '',
              ),
            ),
          ],
        ],
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
          child: ElevatedButton.icon(
            onPressed: provider.actionBusy || alreadyPickedUp
                ? null
                : _confirmPickup,
            icon: provider.actionBusy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : Icon(
                    alreadyPickedUp
                        ? Icons.check_circle_outline
                        : Icons.inventory_2_outlined,
                  ),
            label: Text(
              alreadyPickedUp
                  ? 'Already Picked Up'
                  : provider.actionBusy
                  ? 'Confirming...'
                  : 'Confirm Pickup',
            ),
          ),
        ),
      ),
    );
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  void _snackWithSettings(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        action: SnackBarAction(
          label: 'Settings',
          onPressed: () => openAppSettings(),
        ),
      ),
    );
  }
}

class _AlreadyPickedUpBanner extends StatelessWidget {
  const _AlreadyPickedUpBanner({required this.delivery});

  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    final at = delivery.pickedUpAt;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppGaps.md),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.inventory_2_outlined,
              color: AppColors.warning, size: 20),
          const SizedBox(width: AppGaps.sm),
          Expanded(
            child: Text(
              at != null
                  ? 'This parcel was already picked up at ${FormatUtils.dateTime(at)}. '
                        'A second pickup scan is not allowed.'
                  : 'This parcel is already picked up. A second pickup scan is not allowed.',
              style: AppText.secondary.copyWith(fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }
}

class _PickupInfoRow extends StatelessWidget {
  const _PickupInfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: AppColors.textSecondary),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: AppText.secondary.copyWith(fontSize: 12),
              ),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _VerifiedChip extends StatelessWidget {
  const _VerifiedChip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: AppTheme.success.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.check_circle_outline, size: 15, color: AppTheme.success),
          SizedBox(width: 4),
          Text(
            'Verified',
            style: TextStyle(
              color: AppTheme.success,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

/// Full-height camera sheet used only to capture a waybill QR/barcode.
class _WaybillScannerSheet extends StatefulWidget {
  const _WaybillScannerSheet();

  @override
  State<_WaybillScannerSheet> createState() => _WaybillScannerSheetState();
}

class _WaybillScannerSheetState extends State<_WaybillScannerSheet> {
  final _camera = MobileScannerController();
  bool _handled = false;

  @override
  void dispose() {
    _camera.dispose();
    super.dispose();
  }

  void _complete(String raw) {
    if (_handled) return;
    _handled = true;
    Navigator.of(context).pop(raw);
  }

  Future<void> _manual() async {
    final controller = TextEditingController();
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Enter tracking number'),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.characters,
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z0-9-]')),
            UpperCaseTextFormatter(),
            LengthLimitingTextInputFormatter(40),
          ],
          decoration: const InputDecoration(
            labelText: 'Tracking number',
            hintText: 'TRK-YYYYMMDD-XXXX',
          ),
          onSubmitted: (v) => Navigator.of(dialogContext).pop(v.trim()),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Verify'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (!mounted || value == null || value.isEmpty) return;
    _complete(value);
  }

  @override
  Widget build(BuildContext context) {
    final height = MediaQuery.sizeOf(context).height * 0.65;
    return SafeArea(
      child: SizedBox(
        height: height,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 8, 8),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Scan the waybill barcode',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  ValueListenableBuilder<MobileScannerState>(
                    valueListenable: _camera,
                    builder: (context, state, _) {
                      final torch = state.torchState;
                      return IconButton(
                        tooltip: 'Toggle flashlight',
                        onPressed: torch == TorchState.unavailable
                            ? null
                            : () => _camera.toggleTorch(),
                        icon: Icon(
                          torch == TorchState.on
                              ? Icons.flash_on
                              : Icons.flash_off,
                          color: Colors.white,
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            Expanded(
              child: MobileScanner(
                controller: _camera,
                onDetect: (capture) {
                  final raw = capture.barcodes.isEmpty
                      ? null
                      : capture.barcodes.first.rawValue;
                  if (raw != null) _complete(raw);
                },
                errorBuilder: (context, error) => Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      'Camera unavailable. Type the tracking number instead.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.85)),
                    ),
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: OutlinedButton.icon(
                onPressed: _manual,
                icon: const Icon(Icons.keyboard_outlined),
                label: const Text('Enter tracking number'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                  foregroundColor: Colors.white,
                  side: BorderSide(color: Colors.white.withValues(alpha: 0.4)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
