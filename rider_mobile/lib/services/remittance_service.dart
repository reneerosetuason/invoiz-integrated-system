import '../core/network/api_client.dart';
import '../models/remittance.dart';

/// Rider-side Cash-on-Delivery remittance ledger.
class RemittanceService {
  RemittanceService(this._api);

  final ApiClient _api;
  ApiClient get api => _api;

  Future<List<EscrowPayout>> payouts() async {
    final rows = <String, EscrowPayout>{};
    var page = 1;
    while (true) {
      final data =
          await _api.getSharedAuthenticated(
                'remittances/incoming?per_page=100&page=$page',
              )
              as Map;
      for (final row in (data['remittances'] as List? ?? []).whereType<Map>()) {
        final payout = EscrowPayout.fromJson(Map<String, dynamic>.from(row));
        rows['${payout.source}-${payout.id}'] = payout;
      }
      final last =
          ((data['pagination'] as Map?)?['last_page'] as num?)?.toInt() ?? 1;
      if (page >= last) break;
      page++;
    }
    return rows.values.toList();
  }

  Future<void> acceptPayout(int id, {String source = 'order'}) async {
    await _api.postSharedAuthenticated(
      'remittances/incoming/$source/$id/accept',
      {'confirm_received': 1},
    );
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
