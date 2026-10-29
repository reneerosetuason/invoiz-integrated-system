import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/input_validation.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/app_ui.dart';
import '../../services/delivery_service.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../deliveries/delivery_detail_screen.dart';
import 'parcel_scan_controller.dart';

/// Scan Parcel: camera QR scanner for INVOIZ shipping labels.
///
/// Flow: permission → scan (single-shot) → validate tracking number →
/// backend lookup → existing delivery detail screen. Scanning never creates
/// or mutates data; the resolved delivery opens in [DeliveryDetailScreen]
/// so the rider continues with the existing delivery workflow.
class ScanParcelScreen extends StatefulWidget {
  const ScanParcelScreen({
    super.key,
    this.cameraEnabled = true,
    this.controller,
  });

  /// Test seam: when false the camera widget is replaced by a static
  /// preview so widget tests run without a platform camera.
  final bool cameraEnabled;

  /// Test seam: inject a preconfigured [ParcelScanController] (e.g. with
  /// fake permission handlers). Defaults to a live controller.
  final ParcelScanController? controller;

  @override
  State<ScanParcelScreen> createState() => _ScanParcelScreenState();
}

class _ScanParcelScreenState extends State<ScanParcelScreen>
    with WidgetsBindingObserver {
  late final ParcelScanController _scan;
  final _manualController = TextEditingController();
  bool _ownsScanController = true;
  MobileScannerController? _camera;
  bool _navigated = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _ownsScanController = widget.controller == null;
    _scan =
        widget.controller ??
        ParcelScanController(DeliveryService(context.read<AuthProvider>().api));
    _scan.addListener(_onScanChanged);
    if (widget.cameraEnabled) {
      _camera = MobileScannerController();
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _scan.init());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Returning from system settings: silently re-check permission so a
    // freshly granted camera starts scanning without an extra tap.
    if (state == AppLifecycleState.resumed &&
        _scan.state == ParcelScanState.permissionDenied &&
        mounted) {
      _scan.init();
    }
  }

  void _onScanChanged() {
    if (!mounted) return;
    if (_scan.state == ParcelScanState.lookingUp) {
      _camera?.stop();
    }
    if (_scan.state == ParcelScanState.found && !_navigated) {
      _navigated = true;
      final id = _scan.delivery?.id ?? 0;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => DeliveryDetailScreen(deliveryId: id)),
      );
    } else {
      setState(() {});
    }
  }

  Future<void> _scanAgain() async {
    _scan.resume();
    try {
      await _camera?.start();
    } catch (_) {
      // Camera will surface failures through errorBuilder; the scan state
      // machine already prevents duplicate lookups.
    }
    if (mounted) setState(() {});
  }

  Future<void> _enterTrackingManually() async {
    if (_scan.state == ParcelScanState.lookingUp) return;
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
                Navigator.of(dialogContext).pop(_manualController.text.trim()),
            child: const Text('Look Up'),
          ),
        ],
      ),
    );
    if (value == null || value.isEmpty || !mounted) return;
    await _scan.submitManual(value);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _scan.removeListener(_onScanChanged);
    if (_ownsScanController) _scan.dispose();
    _camera?.dispose();
    _manualController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: const Text('Scan Parcel'),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
      ),
      body: AnimatedBuilder(
        animation: _scan,
        builder: (context, _) {
          switch (_scan.state) {
            case ParcelScanState.checkingPermission:
              return const LoadingWidget(label: 'Preparing camera…');
            case ParcelScanState.permissionDenied:
              return _PermissionDenied(
                permanentlyDenied: _scan.cameraPermanentlyDenied,
                onOpenSettings: () => _scan.openSystemSettings(),
                onRetry: () => _scan.init(),
                onManualEntry: _enterTrackingManually,
              );
            case ParcelScanState.scanning:
            case ParcelScanState.lookingUp:
            case ParcelScanState.invalid:
            case ParcelScanState.notFound:
            case ParcelScanState.forbidden:
            case ParcelScanState.networkError:
              return _ScannerView(
                camera: _camera,
                busy: _scan.state == ParcelScanState.lookingUp,
                error: _scanErrorText(_scan.state),
                onDetect: _scan.handleDetection,
                onTorch: () => _camera?.toggleTorch(),
                torchListenable: _camera,
                onScanAgain: _scanAgain,
                onManualEntry: _enterTrackingManually,
              );
            case ParcelScanState.found:
              return const LoadingWidget(label: 'Opening parcel…');
          }
        },
      ),
    );
  }

  String? _scanErrorText(ParcelScanState state) {
    return switch (state) {
      ParcelScanState.invalid =>
        'Invalid parcel QR code.\nPlease scan an INVOIZ parcel label.',
      ParcelScanState.notFound => 'No parcel found for this tracking number.',
      ParcelScanState.forbidden =>
        'You are not authorized to access this parcel.',
      ParcelScanState.networkError => 'Unable to connect. Please try again.',
      _ => null,
    };
  }
}

/// Camera preview with scanning frame, instructions, torch control, and
/// loading / error overlays.
class _ScannerView extends StatelessWidget {
  const _ScannerView({
    required this.camera,
    required this.busy,
    required this.error,
    required this.onDetect,
    required this.onTorch,
    required this.torchListenable,
    required this.onScanAgain,
    required this.onManualEntry,
  });

  final MobileScannerController? camera;
  final bool busy;
  final String? error;
  final ValueChanged<String?> onDetect;
  final VoidCallback onTorch;
  final ValueListenable<MobileScannerState>? torchListenable;
  final VoidCallback onScanAgain;
  final VoidCallback onManualEntry;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        if (camera != null)
          MobileScanner(
            controller: camera,
            onDetect: (capture) {
              final raw = capture.barcodes.isEmpty
                  ? null
                  : capture.barcodes.first.rawValue;
              onDetect(raw);
            },
            errorBuilder: (context, error) => _CameraError(
              message: 'Camera unavailable. Please try again.',
              onRetry: onScanAgain,
            ),
          )
        else
          const ColoredBox(
            color: Colors.black54,
            child: Center(child: _ScanFrame(active: false)),
          ),
        if (camera != null) const Center(child: _ScanFrame()),
        Positioned(
          top: 16,
          left: 24,
          right: 24,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(AppRadius.sm),
              border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Row(
                  mainAxisSize: MainAxisSize.min,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.qr_code_scanner, color: Colors.white, size: 16),
                    SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        'Scan the QR code on the parcel label',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white, fontSize: 14),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                TextButton.icon(
                  onPressed: busy ? null : onManualEntry,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.white,
                    disabledForegroundColor: Colors.white54,
                  ),
                  icon: const Icon(Icons.keyboard_outlined, size: 16),
                  label: const Text('Enter tracking number'),
                ),
              ],
            ),
          ),
        ),
        Positioned(
          bottom: 40,
          left: 0,
          right: 0,
          child: Center(
            child: torchListenable == null
                ? const SizedBox.shrink()
                : ValueListenableBuilder<MobileScannerState>(
                    valueListenable: torchListenable!,
                    builder: (context, state, _) {
                      final torch = state.torchState;
                      return IconButton.filled(
                        onPressed: torch == TorchState.unavailable
                            ? null
                            : onTorch,
                        icon: Icon(
                          torch == TorchState.on
                              ? Icons.flash_on
                              : Icons.flash_off,
                        ),
                        tooltip: 'Toggle flashlight',
                        style: IconButton.styleFrom(
                          minimumSize: const Size(56, 56),
                          backgroundColor: Colors.white,
                          foregroundColor: Colors.black,
                          disabledBackgroundColor: Colors.white24,
                        ),
                      );
                    },
                  ),
          ),
        ),
        if (busy)
          Container(
            color: Colors.black.withValues(alpha: 0.55),
            child: const LoadingWidget(label: 'Looking up parcel…'),
          ),
        if (!busy && error != null)
          Positioned(
            left: 24,
            right: 24,
            bottom: 120,
            child: Container(
              padding: const EdgeInsets.all(AppGaps.lg),
              decoration: BoxDecoration(
                color: AppColors.card,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const IconTile(
                    icon: Icons.qr_code_2_outlined,
                    size: 48,
                    iconSize: 24,
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(height: AppGaps.sm),
                  Text(
                    error!,
                    textAlign: TextAlign.center,
                    style: AppText.body,
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: onScanAgain,
                      icon: const Icon(Icons.qr_code_scanner),
                      label: const Text('Scan Again'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size(0, 48),
                        backgroundColor: AppTheme.primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

/// Corner-style scanning frame overlay with an animated scan line.
///
/// The line sweeps only while [active] is true; otherwise the frame renders
/// statically. Lifecycle is owned here (disposed with the widget), so the
/// animation can never run in the background.
class _ScanFrame extends StatefulWidget {
  const _ScanFrame({this.active = true});

  final bool active;

  @override
  State<_ScanFrame> createState() => _ScanFrameState();
}

class _ScanFrameState extends State<_ScanFrame>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _sweep;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    );
    _sweep = CurvedAnimation(parent: _controller, curve: Curves.easeInOut);
    if (widget.active) _controller.repeat(reverse: true);
  }

  @override
  void didUpdateWidget(covariant _ScanFrame oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !_controller.isAnimating) {
      _controller.repeat(reverse: true);
    } else if (!widget.active && _controller.isAnimating) {
      _controller.stop();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const side = 240.0;
    const arm = 36.0;
    const edge = BorderSide(color: Colors.white, width: 5);

    Widget corner({required bool left, required bool top}) {
      return SizedBox(
        width: arm,
        height: arm,
        child: DecoratedBox(
          decoration: BoxDecoration(
            border: Border(
              left: left ? edge : BorderSide.none,
              right: !left ? edge : BorderSide.none,
              top: top ? edge : BorderSide.none,
              bottom: !top ? edge : BorderSide.none,
            ),
          ),
        ),
      );
    }

    return SizedBox(
      width: side,
      height: side,
      child: Stack(
        children: [
          Positioned(top: 0, left: 0, child: corner(left: true, top: true)),
          Positioned(top: 0, right: 0, child: corner(left: false, top: true)),
          Positioned(bottom: 0, left: 0, child: corner(left: true, top: false)),
          Positioned(
            bottom: 0,
            right: 0,
            child: corner(left: false, top: false),
          ),
          if (widget.active)
            AnimatedBuilder(
              animation: _sweep,
              builder: (context, _) {
                return Positioned(
                  top: 12 + _sweep.value * (side - 24 - 3),
                  left: 24,
                  right: 24,
                  child: Container(
                    height: 3,
                    decoration: BoxDecoration(
                      color: AppColors.secondary,
                      borderRadius: BorderRadius.circular(2),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.secondary.withValues(alpha: 0.7),
                          blurRadius: 8,
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
        ],
      ),
    );
  }
}

class _PermissionDenied extends StatelessWidget {
  const _PermissionDenied({
    required this.permanentlyDenied,
    required this.onOpenSettings,
    required this.onRetry,
    required this.onManualEntry,
  });

  final bool permanentlyDenied;
  final VoidCallback onOpenSettings;
  final VoidCallback onRetry;
  final VoidCallback onManualEntry;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.background,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 84,
                height: 84,
                decoration: BoxDecoration(
                  color: AppTheme.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                ),
                child: const Icon(
                  Icons.videocam_off_outlined,
                  size: 38,
                  color: AppTheme.primary,
                ),
              ),
              const SizedBox(height: AppGaps.lg),
              Text(
                permanentlyDenied
                    ? 'Camera access is disabled. Enable it in system settings to scan parcels.'
                    : 'Camera access is needed to scan parcel labels.',
                textAlign: TextAlign.center,
                style: AppText.body.copyWith(fontSize: 15),
              ),
              const SizedBox(height: 20),
              if (permanentlyDenied)
                FilledButton.icon(
                  onPressed: onOpenSettings,
                  icon: const Icon(Icons.settings_outlined),
                  label: const Text('Open Settings'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(200, 48),
                    backgroundColor: AppTheme.primary,
                  ),
                ),
              if (permanentlyDenied) const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Try Again'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(200, 48),
                ),
              ),
              const SizedBox(height: 12),
              TextButton.icon(
                onPressed: onManualEntry,
                icon: const Icon(Icons.keyboard_outlined, size: 18),
                label: const Text('Enter tracking number instead'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CameraError extends StatelessWidget {
  const _CameraError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black,
      child: ErrorView(message: message, onRetry: onRetry),
    );
  }
}
