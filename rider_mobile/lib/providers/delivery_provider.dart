import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/delivery.dart';
import '../services/delivery_service.dart';

/// Delivery list + workflow state.
class DeliveryProvider extends ChangeNotifier {
  DeliveryProvider(this._service);

  final DeliveryService _service;

  List<Delivery> _deliveries = [];
  Delivery? _selected;
  String _filter = 'all';
  bool _loading = false;
  bool _actionBusy = false;
  String? _error;

  List<Delivery> get deliveries => _deliveries;
  Delivery? get selected => _selected;
  String get filter => _filter;
  bool get loading => _loading;
  bool get actionBusy => _actionBusy;
  String? get error => _error;

  static const Map<String, String> filters = {
    'all': 'All',
    'new': 'New',
    'accepted': 'Accepted',
    'pickup': 'Pickup',
    'in_transit': 'In Transit',
    'delivered': 'Delivered',
    'failed': 'Failed',
  };

  void setFilter(String filter) {
    if (_filter == filter) return;
    _filter = filter;
    load();
  }

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      final page = await _service.list(filter: _filter);
      _deliveries = page.deliveries;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      // Don't mask the underlying failure with a static string: expose the
      // real exception so a network/parse/type error is diagnosable.
      final detail = e.toString();
      _error = (detail.isNotEmpty && detail != 'Exception')
          ? 'Unable to load deliveries ($detail).'
          : 'Unable to load deliveries. Please try again.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> loadDetail(int id) async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      _selected = await _service.detail(id);
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Unable to load delivery details. Please try again.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  /// Replaces [selected] with the updated delivery returned by the backend.
  void _applyUpdated(Delivery updated) {
    _selected = updated;
    final index = _deliveries.indexWhere((d) => d.id == updated.id);
    if (index != -1) {
      _deliveries[index] = updated;
    }
    notifyListeners();
  }

  Future<bool> accept(int id) => _runAction(
        'accept',
        () async => _applyUpdated(await _service.accept(id)),
      );

  Future<bool> updateStatus(int id, String status) => _runAction(
        'updateStatus',
        () async => _applyUpdated(await _service.updateStatus(id, status)),
      );

  Future<bool> pickup(int id, {String? pickupPin}) => _runAction(
        'pickup',
        () async => _applyUpdated(await _service.pickup(id, pickupPin: pickupPin)),
      );

  Future<bool> complete(
    int id, {
    String proofType = 'signature',
    Uint8List? photoBytes,
    String? photoFilename,
    String? signatureName,
    String? otp,
    double? amountReceived,
    double? latitude,
    double? longitude,
  }) =>
      _runAction('complete', () async {
        final updated = await _service.complete(
          id,
          proofType: proofType,
          photoBytes: photoBytes,
          photoFilename: photoFilename,
          signatureName: signatureName,
          otp: otp,
          amountReceived: amountReceived,
          latitude: latitude,
          longitude: longitude,
        );
        _applyUpdated(updated);
      });

  Future<bool> failed(int id, {required String reason, String? notes}) =>
      _runAction('failed', () async {
        final updated = await _service.failed(id, reason: reason, notes: notes);
        _applyUpdated(updated);
      });

  Future<bool> requestReattempt(int id) => _runAction(
        'requestReattempt',
        () async => _applyUpdated(await _service.requestReattempt(id)),
      );

  Future<bool> returnToCenter(int id) => _runAction(
        'returnToCenter',
        () async => _applyUpdated(await _service.returnToCenter(id)),
      );

  Future<bool> sortingCenterHandoff(int id) => _runAction(
        'sortingCenterHandoff',
        () async => _applyUpdated(await _service.sortingCenterHandoff(id)),
      );

  Future<bool> sortingCenterPickup(int id) => _runAction(
        'sortingCenterPickup',
        () async => _applyUpdated(await _service.sortingCenterPickup(id)),
      );

  Future<bool> _runAction(String name, Future<void> Function() action) async {
    _actionBusy = true;
    _error = null;
    notifyListeners();

    try {
      await action();
      _actionBusy = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _actionBusy = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Unable to complete that action. Please try again.';
      _actionBusy = false;
      notifyListeners();
      return false;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearSelected() {
    _selected = null;
  }
}