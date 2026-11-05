import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/remittance.dart';
import '../services/remittance_service.dart';

class RemittanceProvider extends ChangeNotifier {
  RemittanceProvider(this._service);

  final RemittanceService _service;

  RemittancePage? _page;
  bool _loading = false;
  bool _actionBusy = false;
  String? _error;
  List<EscrowPayout> payouts = [];
  String? payoutError;

  RemittancePage? get page => _page;
  bool get loading => _loading;
  bool get actionBusy => _actionBusy;
  String? get error => _error;

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      _page = await _service.list();
      await loadPayouts();
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Unable to load remittances. Please try again.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> loadPayouts() async {
    payoutError = null;
    try {
      payouts = await _service.payouts();
    } on ApiException catch (e) {
      payoutError = e.message;
    } catch (_) {
      payoutError = 'Unable to load incoming payouts. Pull to refresh.';
    }
    notifyListeners();
  }

  Future<bool> acceptPayout(int id) async {
    if (_actionBusy) return false;
    _actionBusy = true;
    payoutError = null;
    notifyListeners();
    try {
      await _service.acceptPayout(id);
      await loadPayouts();
      return true;
    } on ApiException catch (e) {
      payoutError = e.message;
      return false;
    } catch (_) {
      payoutError =
          'Unable to accept payout. Refresh to check its current status.';
      return false;
    } finally {
      _actionBusy = false;
      notifyListeners();
    }
  }

  Future<bool> markSent(int id, {String? reference}) async {
    _actionBusy = true;
    _error = null;
    notifyListeners();

    try {
      await _service.markSent(id, reference: reference);
      await load();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      return false;
    } catch (_) {
      _error = 'Unable to update the remittance. Please try again.';
      return false;
    } finally {
      _actionBusy = false;
      notifyListeners();
    }
  }
}
