import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/services/remittance_service.dart';

void main() {
  group('RemittanceService', () {
    test('loads rider remittances and parses the summary', () async {
      final client = MockClient((request) async {
        expect(request.method, 'GET');
        expect(request.url.path, endsWith('/rider/remittances'));

        return http.Response(
          jsonEncode({
            'remittances': [
              {
                'id': 9,
                'order_id': 44,
                'delivery_id': 77,
                'amount': 500.5,
                'status': 'pending',
                'reference': null,
                'sent_at': null,
                'confirmed_at': null,
                'created_at': '2026-10-19T10:00:00Z',
                'delivery': {
                  'tracking_number': 'INV-77',
                  'status': 'delivered',
                },
              },
            ],
            'summary': {
              'total_due': 500.5,
              'total_confirmed': 0,
              'pending_count': 1,
              'sent_count': 0,
              'confirmed_count': 0,
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = RemittanceService(ApiClient(client: client));
      final page = await service.list();

      expect(page.remittances, hasLength(1));
      expect(page.remittances.first.id, 9);
      expect(page.remittances.first.amount, 500.5);
      expect(page.remittances.first.isPending, isTrue);
      expect(page.remittances.first.trackingNumber, 'INV-77');
      expect(page.summary.totalDue, 500.5);
      expect(page.summary.pendingCount, 1);
    });

    test('marks a remittance sent and parses the updated record', () async {
      final client = MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path, endsWith('/rider/remittances/9/sent'));
        expect(jsonDecode(request.body), {'reference': 'GC-12345'});

        return http.Response(
          jsonEncode({
            'remittance': {
              'id': 9,
              'order_id': 44,
              'amount': 500.5,
              'status': 'sent',
              'reference': 'GC-12345',
              'sent_at': '2026-10-19T11:00:00Z',
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final service = RemittanceService(ApiClient(client: client));
      final remittance = await service.markSent(9, reference: ' GC-12345 ');

      expect(remittance.status, 'sent');
      expect(remittance.reference, 'GC-12345');
      expect(remittance.isSent, isTrue);
    });
  });
}
