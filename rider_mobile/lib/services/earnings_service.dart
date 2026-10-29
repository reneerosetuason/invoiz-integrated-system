import '../core/network/api_client.dart';
import '../models/earning.dart';

/// Earnings + delivery history service. Earnings always come from Laravel.
class EarningsService {
  EarningsService(this._api);

  final ApiClient _api;

  Future<EarningsSummary> summary() async {
    final data = await _api.get('/rider/earnings');
    return EarningsSummary.fromJson(data ?? {});
  }

  Future<List<HistoryEntry>> history({
    String? search,
    String? from,
    String? to,
    String? status,
  }) async {
    final data = await _api.get('/rider/history', query: {
      if (search != null && search.isNotEmpty) 'search': search,
      'from': ?from,
      'to': ?to,
      if (status != null && status.isNotEmpty) 'status': status,
    });

    List<HistoryEntry> list = [];
    if (data['history'] is List) {
      list = (data['history'] as List)
          .whereType<Map<String, dynamic>>()
          .map(HistoryEntry.fromJson)
          .toList();
    }
    return list;
  }
}