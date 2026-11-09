import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/return_agreement_screen.dart';
import 'package:invoiz/services/api_service.dart';

void main() {
  tearDown(() => ApiService.setToken(null));
  testWidgets(
    'seller fault displays full refund without deductions or buyer payment',
    (tester) async {
      final api = ApiService(
        client: MockClient(
          (request) async => http.Response(
            jsonEncode({
              'agreement': {
                'id': 7,
                'version': 2,
                'workflow_policy': 'seller_fault_direct',
                'status': 'proposed',
                'resolution': 'return_refund',
                'fault': 'seller',
                'payer': 'seller',
                'funding_method': 'deposit',
                'merchandise_cents': 459900,
                'transport_cents': 16000,
                'shipping_cents': 5000,
                'compensation_cents': 0,
                'net_refund_cents': 464900,
                'quoted_at': '2026-11-08',
                'seller_accepted_at': '2026-11-08',
                'buyer_accepted_at': '2026-11-08',
                'deduction_note': 'Seller sends the net refund directly.',
                'payments': [],
                'allocations': [],
              },
              'progress': {},
              'budget_outstanding_cents': 16000,
            }),
            200,
          ),
        ),
      );
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 7, api: api)),
      );
      await tester.pumpAndSettle();
      expect(
        find.textContaining('The seller pays return delivery separately'),
        findsOneWidget,
      );
      await tester.scrollUntilVisible(find.text('You receive: ₱4649.00'), 200);
      expect(
        find.textContaining('Less original seller-funded costs'),
        findsNothing,
      );
      expect(find.text('Upload return-fee receipt'), findsNothing);
      expect(find.text('Request admin review'), findsNothing);
      expect(find.text('Accept agreement'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'seller fault full refund closes only after buyer confirms actual receipt',
    (tester) async {
      var confirmed = false;
      final api = ApiService(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(request.url.path, endsWith('/returns/7/confirm'));
            confirmed = true;
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'agreement': {
                'id': 7,
                'version': 2,
                'workflow_policy': 'seller_fault_direct',
                'status': confirmed ? 'completed' : 'collection_ready',
                'resolution': 'return_refund',
                'fault': 'seller',
                'payer': 'seller',
                'merchandise_cents': 459900,
                'transport_cents': 16000,
                'shipping_cents': 5000,
                'compensation_cents': 0,
                'net_refund_cents': 464900,
                'collection_authorized_at': '2026-11-08',
                'closed_at': confirmed ? '2026-11-08' : null,
                'buyer_accepted_at': '2026-11-08',
                'seller_accepted_at': '2026-11-08',
                'payments': [],
                'allocations': [],
              },
              'progress': {
                'refund_source': 'seller_direct',
                'refund_sent_at': '2026-11-08',
                'refund_reference': 'SELLER-3869',
                'buyer_confirmed_at': confirmed ? '2026-11-08' : null,
              },
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 7, api: api)),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Confirm refund received'),
        250,
      );
      expect(confirmed, isFalse);
      await tester.ensureVisible(find.text('Confirm refund received'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm refund received'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(TextButton, 'Confirm'));
      await tester.pumpAndSettle();
      expect(confirmed, isTrue);
      expect(find.text('Confirm refund received'), findsNothing);
      await tester.scrollUntilVisible(find.text('Refund received'), 200);
      expect(find.text('Refund received'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
