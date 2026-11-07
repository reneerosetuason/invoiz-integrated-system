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
    'shows net deduction clearly and does not offer a duplicate payment',
    (tester) async {
      ApiService.setToken('buyer-return-test');
      var posts = 0;
      final api = ApiService(
        client: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer buyer-return-test');
          if (request.method == 'POST') {
            posts++;
            final body = jsonDecode(request.body);
            expect(body['action'], 'accept');
            expect(body['version'], '3');
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'agreement': {
                'id': 1,
                'version': 3,
                'status': 'proposed',
                'resolution': 'return_refund',
                'fault': 'buyer',
                'payer': 'buyer',
                'funding_method': 'deduct_refund',
                'merchandise_cents': 100000,
                'shipping_cents': 0,
                'transport_cents': 16000,
                'compensation_cents': 0,
                'net_refund_cents': 84000,
                'quoted_at': '2026-11-06',
                'allocations': [],
                'payments': [],
              },
              'permissions': {'buyer': true},
              'budget_outstanding_cents': 16000,
            }),
            200,
          );
        }),
      );
      tester.view.physicalSize = const Size(320, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 11, api: api)),
      );
      await tester.pumpAndSettle();
      expect(find.text('You receive: ₱840.00'), findsOneWidget);
      expect(find.text('Pay return fee'), findsNothing);
      await tester.ensureVisible(find.text('Accept agreement'));
      await tester.tap(find.text('Accept agreement'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(posts, 0);
      await tester.tap(find.text('Accept agreement'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(tester.takeException(), isNull);
    },
  );
}
