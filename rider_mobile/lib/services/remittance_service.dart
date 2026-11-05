import '../core/network/api_client.dart';
import '../models/remittance.dart';

/// Rider-side Cash-on-Delivery remittance ledger.
class RemittanceService {
  RemittanceService(this._api);

  final ApiClient _api;

  Future<List<EscrowPayout>> payouts() async {
    final data = await _api.get('/rider/remittances/earnings-payouts');
    final rows = (data as Map)['payouts'] as List? ?? [];
    return rows
        .whereType<Map>()
        .map((row) => EscrowPayout.fromJson(Map<String, dynamic>.from(row)))
        .toList();
  }

  Future<void> acceptPayout(int id) async {
    await _api.post('/rider/remittances/$id/accept-payout', body: {});
  }

  Future<RemittancePage> list() async {
    final data = await _api.get('/rider/remittances');
    final map = Map<String, dynamic>.from(data as Map? ?? {});

    final raw = map['remittances'];
    final remittances = raw is List
        ? raw
              .whereType<Map>()
              .map(
                (item) => Remittance.fromJson(Map<String, dynamic>.from(item)),
              )
              .toList()
        : <Remittance>[];

    return RemittancePage(
      remittances: remittances,
      summary: RemittanceSummary.fromJson(
        map['summary'] is Map
            ? Map<String, dynamic>.from(map['summary'] as Map)
            : null,
      ),
    );
  }

  Future<Remittance> markSent(int id, {String? reference}) async {
    final data = await _api.post(
      '/rider/remittances/$id/sent',
      body: {
        if (reference != null && reference.trim().isNotEmpty)
          'reference': reference.trim(),
      },
    );

    final map = Map<String, dynamic>.from(data as Map? ?? {});
    final remittance = map['remittance'];

    if (remittance is! Map) {
      throw const FormatException('Missing remittance in response.');
    }

    return Remittance.fromJson(Map<String, dynamic>.from(remittance));
  }
}
