import '../core/network/api_client.dart';
import '../core/network/api_exception.dart';
import '../models/logistics_account.dart';
import '../models/logistics_scan.dart';

/// Logistics scanning calls against the shared Laravel backend.
///
/// The app never sends a facility id. The backend resolves the operator's hub
/// from the authenticated account, applies the same scan transition rules as
/// the Logistics Web counter, and returns the resulting parcel history.
class LogisticsScanService {
  LogisticsScanService(this._api);

  final ApiClient _api;

  Future<LogisticsAccount> context() async {
    final data = await _api.get('/logistics/scan/context');
    return LogisticsAccount.fromJson(data);
  }

  Future<LogisticsScanOutcome> lookup(String trackingNumber) async {
    try {
      final data = await _api.post(
        '/logistics/scan/lookup',
        body: {'tracking_number': trackingNumber},
      );
      return LogisticsScanOutcome.fromJson(data, statusCode: 200);
    } on ApiException catch (e) {
      return LogisticsScanOutcome.fromJson(
        e.data ?? {'message': e.message},
        statusCode: e.statusCode,
      );
    }
  }

  /// Confirm the scan. Validation/conflict responses are returned as
  /// outcomes (the message explains why); connectivity problems still throw
  /// so the operator can retry.
  Future<LogisticsScanOutcome> confirm(String trackingNumber) async {
    try {
      final data = await _api.post(
        '/logistics/scan/confirm',
        body: {'tracking_number': trackingNumber},
      );
      return LogisticsScanOutcome.fromJson(data, statusCode: 200);
    } on ApiException catch (e) {
      if (e.data == null) rethrow;
      return LogisticsScanOutcome.fromJson(e.data!, statusCode: e.statusCode);
    }
  }
}
