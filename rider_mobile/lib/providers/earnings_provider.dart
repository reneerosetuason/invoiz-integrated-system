import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/earning.dart';
import '../services/earnings_service.dart';

/// Earnings + history state.
class EarningsProvider extends ChangeNotifier {
  EarningsProvider(this._service);

  final EarningsService _service;

  EarningsSummary? _summary;
  List<HistoryEntry> _history = [];
  bool _loading = false;
  bool _historyLoading = false;
  String? _error;

  EarningsSummary? get summary => _summary;
  List<HistoryEntry> get history => _history;
  bool get loading => _loading;
  bool get historyLoading => _historyLoading;
  String? get error => _error;

  Future<bool> loadSummary() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      _summary = await _service.summary();
      _loading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Unable to load earnings. Please try again.';
      _loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> loadHistory({
    String? search,
    String? status,
    String? from,
    String? to,
  }) async {
    _historyLoading = true;
    notifyListeners();

    try {
      _history = await _service.history(
        search: search,
        status: status,
        from: from,
        to: to,
      );
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Unable to load history. Please try again.';
    } finally {
      _historyLoading = false;
      notifyListeners();
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}