import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/return_agreement_screen.dart';
import 'package:invoiz/services/api_service.dart';

void main() {
  Map<String, dynamic> payload({
    bool confirmed = false,
    bool declined = false,
    bool sent = true,
    bool partial = false,
    int revision = 1,
  }) => {
    'case': {'id': 6},
    'agreement': {
      'id': 4,
      'version': 2,
      'workflow_policy': 'seller_direct_keep_item',
      'resolution': partial ? 'partial_refund' : 'refund_only',
      'status': confirmed
          ? 'refunded_return_waived'
          : (declined ? 'refund_disputed' : 'refund_sent'),
      'fault': 'seller',
      'payer': 'seller',
      'merchandise_cents': partial ? 120000 : 459900,
      'shipping_cents': partial ? 0 : 5000,
      'transport_cents': 0,
      'compensation_cents': 0,
      'net_refund_cents': partial ? 120000 : 464900,
      'buyer_accepted_at': '2026-11-09',
      'seller_accepted_at': '2026-11-09',
      'closed_at': confirmed ? '2026-11-09' : null,
      'payments': [],
      'allocations': [],
      'events': [
        if (declined)
          {
            'actor_label': 'Buyer',
            'action': 'refund_receipt_declined',
            'note': 'The transfer has not arrived in my bank.',
          },
      ],
    },
    'progress': {
      'refund_sent_at': sent ? '2026-11-09' : null,
      'refund_reference': 'SELLER-DIRECT-4649',
      'buyer_confirmed_at': confirmed ? '2026-11-09' : null,
    },
    'refund_receipt': sent
        ? {
            'id': 7,
            'version': revision,
            'status': declined ? 'declined' : 'pending',
            'buyer_note': declined
                ? 'The transfer has not arrived in my bank.'
                : null,
          }
        : null,
    'permissions': {'buyer': true},
    'budget_outstanding_cents': 0,
  };

  testWidgets(
    'partial refund displays the agreed smaller amount and uses buyer confirmation without admin',
    (tester) async {
      var confirmed = false;
      final api = ApiService(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(request.url.path, endsWith('/returns/6/confirm'));
            expect(jsonDecode(request.body), {
              'receipt_id': 7,
              'receipt_version': 1,
            });
            confirmed = true;
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode(payload(partial: true, confirmed: confirmed)),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 6, api: api)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Resolution: partial refund'), findsOneWidget);
      expect(find.text('Request admin review'), findsNothing);
      expect(find.text('Upload return-fee receipt'), findsNothing);
      await tester.scrollUntilVisible(find.text('You receive: ₱1200.00'), 200);
      expect(find.text('Item refund: ₱1200.00'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Confirm refund received'),
        200,
      );
      await tester.ensureVisible(find.text('Confirm refund received'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm refund received'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(TextButton, 'Confirm'));
      await tester.pumpAndSettle();
      expect(confirmed, isTrue);
      expect(find.text('Confirm refund received'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'reopened keep-item agreement shows receipt and confirms its exact version',
    (tester) async {
      var confirmed = false;
      var proofViews = 0;
      final api = ApiService(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/proof')) {
            expect(request.url.queryParameters, {
              'receipt': '7',
              'revision': '2',
            });
            proofViews++;
            return http.Response.bytes(
              base64Decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII=',
              ),
              200,
              headers: {'content-type': 'image/png'},
            );
          }
          if (request.method == 'POST') {
            expect(request.url.path, endsWith('/returns/6/confirm'));
            expect(jsonDecode(request.body), {
              'receipt_id': 7,
              'receipt_version': 2,
            });
            confirmed = true;
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode(payload(confirmed: confirmed, revision: 2)),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 6, api: api)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Request admin review'), findsNothing);
      expect(find.text('Upload return-fee receipt'), findsNothing);
      await tester.scrollUntilVisible(
        find.text('View seller refund receipt'),
        250,
      );
      await tester.ensureVisible(find.text('View seller refund receipt'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('View seller refund receipt'));
      await tester.pumpAndSettle();
      expect(proofViews, 1);
      await tester.tap(find.byTooltip('Close image preview'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Confirm refund received'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm refund received'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(TextButton, 'Confirm'));
      await tester.pumpAndSettle();
      expect(confirmed, isTrue);
      expect(find.text('Confirm refund received'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'buyer reports failed payment and sends suggestions without resetting accepted terms',
    (tester) async {
      var declined = false;
      var comments = 0;
      final api = ApiService(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            final body = jsonDecode(request.body) as Map;
            expect(body['receipt_id'], '7');
            expect(body['receipt_version'], '1');
            if (body['action'] == 'decline') declined = true;
            if (body['action'] == 'comment') comments++;
            return http.Response('{}', 200);
          }
          return http.Response(jsonEncode(payload(declined: declined)), 200);
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 6, api: api)),
      );
      await tester.pumpAndSettle();
      for (final label in [
        'Decline receipt / payment not received',
        'Suggest changes / send message',
      ]) {
        await tester.scrollUntilVisible(find.text(label), 200);
        await tester.ensureVisible(find.text(label));
        await tester.pumpAndSettle();
        await tester.tap(find.text(label));
        await tester.pumpAndSettle();
        await tester.enterText(
          find.byType(TextFormField),
          'The transfer has not arrived in my bank.',
        );
        await tester.tap(find.byType(CheckboxListTile));
        await tester.pumpAndSettle();
        await tester.tap(find.widgetWithText(TextButton, 'Confirm'));
        await tester.pumpAndSettle();
      }
      expect(declined, isTrue);
      expect(comments, 1);
      expect(find.text('Accept agreement'), findsNothing);
      expect(find.text('Confirm refund received'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  for (final width in [320.0, 390.0]) {
    testWidgets('keep-item refund account and receipt controls fit $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 850);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = ApiService(
        client: MockClient(
          (_) async => http.Response(jsonEncode(payload(declined: true)), 200),
        ),
      );
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 6, api: api)),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Decline receipt / payment not received'),
        250,
      );
      expect(tester.takeException(), isNull);
      await tester.scrollUntilVisible(find.text('Verify refund account'), -250);
      expect(find.text('Verify refund account'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
