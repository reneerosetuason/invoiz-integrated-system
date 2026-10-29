import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../core/network/api_exception.dart';
import '../../models/delivery.dart';
import '../../services/delivery_service.dart';
import 'parcel_tracking.dart';

/// UI states of the parcel scan flow.
enum ParcelScanState {
  /// Checking camera permission (transient).
  checkingPermission,

  /// Camera permission denied — guidance + retry/settings actions.
  permissionDenied,

  /// Camera active, waiting for a QR code.
  scanning,

  /// Valid QR captured, delivery lookup in flight.
  lookingUp,

  /// Payload is not an INVOIZ tracking number — rider may scan again.
  invalid,

  /// Well-formed tracking number, no matching delivery.
  notFound,

  /// Delivery exists but this rider may not access it.
  forbidden,

  /// Connectivity / server failure.
  networkError,

  /// Terminal: delivery resolved — the screen navigates to it.
  found,
}

/// State machine for Scan Parcel: permission, single-shot detection,
/// tracking validation, backend lookup, and error mapping.
///
/// Detection is single-shot: [handleDetection] is a no-op unless the state
/// is [ParcelScanState.scanning], so one QR can never fire duplicate
/// lookups even if the camera reports it on consecutive frames.
class ParcelScanController extends ChangeNotifier {
  ParcelScanController(
    this._service, {
    Future<PermissionStatus> Function()? requestCameraPermission,
    Future<bool> Function()? isCameraPermanentlyDenied,
    Future<void> Function()? openSystemSettings,
  }) : _requestCameraPermission =
           requestCameraPermission ?? (() => Permission.camera.request()),
       _isCameraPermanentlyDenied =
           isCameraPermanentlyDenied ??
           (() async => Permission.camera.isPermanentlyDenied),
       _openSystemSettings = openSystemSettings ?? (() => openAppSettings());

  final DeliveryService _service;
  final Future<PermissionStatus> Function() _requestCameraPermission;
  final Future<bool> Function() _isCameraPermanentlyDenied;
  final Future<void> Function() _openSystemSettings;

  ParcelScanState _state = ParcelScanState.checkingPermission;
  ParcelScanState get state => _state;

  Delivery? _delivery;
  Delivery? get delivery => _delivery;

  String? _scannedTracking;
  String? get scannedTracking => _scannedTracking;

  bool _cameraPermanentlyDenied = false;
  bool get cameraPermanentlyDenied => _cameraPermanentlyDenied;

  /// Request camera permission and enter the scanning state when granted.
  Future<void> init() async {
    _transition(ParcelScanState.checkingPermission);
    final status = await _requestCameraPermission();
    if (status.isGranted || status.isLimited) {
      _transition(ParcelScanState.scanning);
    } else {
      _cameraPermanentlyDenied = await _isCameraPermanentlyDenied();
      _transition(ParcelScanState.permissionDenied);
    }
  }

  Future<void> openSystemSettings() => _openSystemSettings();

  /// Handle one decoded payload. Ignored unless currently scanning, which
  /// guarantees a single lookup per scan.
  Future<void> handleDetection(String? raw) async {
    if (_state != ParcelScanState.scanning) return;

    final tracking = ParcelTracking.validated(raw);
    if (tracking == null) {
      _transition(ParcelScanState.invalid);
      return;
    }

    await _lookup(tracking);
  }

  /// Resolve a tracking number typed by the rider (manual entry fallback for
  /// unreadable or missing labels). Behaves exactly like a scan: the same
  /// validation, single-shot guard and backend lookup are used.
  Future<void> submitManual(String? raw) async {
    if (_state == ParcelScanState.lookingUp) return;
    if (_state == ParcelScanState.found) return;

    final tracking = ParcelTracking.validated(raw);
    if (tracking == null) {
      _scannedTracking = ParcelTracking.normalize(raw);
      _transition(ParcelScanState.invalid);
      return;
    }

    await _lookup(tracking);
  }

  Future<void> _lookup(String tracking) async {
    _scannedTracking = tracking;
    _transition(ParcelScanState.lookingUp);

    try {
      _delivery = await _service.lookupByTracking(tracking);
      _transition(ParcelScanState.found);
    } on ApiException catch (e) {
      _transition(switch (e.type) {
        ApiErrorType.notFound => ParcelScanState.notFound,
        ApiErrorType.forbidden ||
        ApiErrorType.unauthorized => ParcelScanState.forbidden,
        _ => ParcelScanState.networkError,
      });
    } catch (_) {
      _transition(ParcelScanState.networkError);
    }
  }

  /// Return to scanning after an error so the rider can scan again.
  void resume() {
    _delivery = null;
    _scannedTracking = null;
    _transition(ParcelScanState.scanning);
  }

  void _transition(ParcelScanState next) {
    _state = next;
    notifyListeners();
  }
}
