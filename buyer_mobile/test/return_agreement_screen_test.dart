import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/return_agreement_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:image_picker/image_picker.dart';
import 'package:invoiz/widgets/return_fee_receipt_dialog.dart';

void main() {
  tearDown(() => ApiService.setToken(null));
  testWidgets(
    'buyer fault displays deductions and never requests a separate buyer fee',
    (tester) async {
      final api = ApiService(
        client: MockClient(
          (request) async => http.Response(
            jsonEncode({
              'agreement': {
                'id': 7,
                'version': 2,
                'workflow_policy': 'buyer_fault_direct',
                'status': 'proposed',
                'resolution': 'return_refund',
                'fault': 'buyer',
                'payer': 'seller',
                'funding_method': 'deposit',
                'merchandise_cents': 459900,
                'transport_cents': 16000,
                'compensation_cents': 56990,
                'net_refund_cents': 386910,
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
        find.textContaining('You do not pay a separate return fee'),
        findsOneWidget,
      );
      await tester.scrollUntilVisible(find.text('You receive: ₱3869.10'), 200);
      expect(
        find.text('Less original seller-funded costs: ₱569.90'),
        findsOneWidget,
      );
      expect(find.text('Upload return-fee receipt'), findsNothing);
      expect(find.text('Request admin review'), findsNothing);
      expect(find.text('Accept agreement'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'direct seller refund is completed only when buyer confirms actual receipt',
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
                'workflow_policy': 'buyer_fault_direct',
                'status': confirmed ? 'completed' : 'collection_ready',
                'resolution': 'return_refund',
                'fault': 'buyer',
                'payer': 'seller',
                'merchandise_cents': 459900,
                'transport_cents': 16000,
                'compensation_cents': 56990,
                'net_refund_cents': 386910,
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
  testWidgets(
    'return fee receipt is chosen and submitted as a pending multipart payment',
    (tester) async {
      var submitted = false;
      final api = ApiService(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(
              request.headers['content-type'],
              contains('multipart/form-data'),
            );
            final body = latin1.decode(request.bodyBytes);
            expect(body, contains('name="proof"; filename="receipt.png"'));
            expect(body, contains('return_budget'));
            expect(body, contains('160.00'));
            expect(body, contains('GCASH-RETURN-160'));
            submitted = true;
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'agreement': {
                'id': 4,
                'version': 2,
                'workflow_policy': 'direct',
                'status': 'proposed',
                'resolution': 'return_refund',
                'fault': 'buyer',
                'payer': 'buyer',
                'funding_method': 'deposit',
                'merchandise_cents': 459900,
                'transport_cents': 16000,
                'net_refund_cents': 459900,
                'quoted_at': '2026-11-08',
                'seller_accepted_at': '2026-11-08',
                'buyer_accepted_at': '2026-11-08',
                'payments': submitted
                    ? [
                        {
                          'id': 9,
                          'purpose': 'return_budget',
                          'status': 'submitted',
                          'amount_cents': 16000,
                          'reference': 'GCASH-RETURN-160',
                        },
                      ]
                    : [],
                'allocations': [],
              },
              'budget_outstanding_cents': 16000,
              'receiving_account': {
                'account_name': 'Platform',
                'account_details': 'Test wallet',
              },
            }),
            200,
          );
        }),
      );
      tester.view.physicalSize = const Size(390, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(
          home: ReturnAgreementScreen(
            returnId: 4,
            api: api,
            receiptPicker: () async => XFile.fromData(
              base64Decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII=',
              ),
              name: 'receipt.png',
              path: 'receipt.png',
              mimeType: 'image/png',
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Upload return-fee receipt'),
        250,
      );
      await tester.ensureVisible(find.text('Upload return-fee receipt'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload return-fee receipt'));
      await tester.pumpAndSettle();
      final submit = find.widgetWithText(
        TextButton,
        'Submit receipt for verification',
      );
      expect(tester.widget<TextButton>(submit).onPressed, isNull);
      await tester.enterText(find.byType(TextFormField), 'GCASH-RETURN-160');
      await tester.ensureVisible(find.text('Choose receipt screenshot'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Choose receipt screenshot'));
      await tester.pumpAndSettle();
      expect(find.text('receipt.png'), findsOneWidget);
      await tester.ensureVisible(find.byType(CheckboxListTile));
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Submit receipt for verification'));
      await tester.pumpAndSettle();
      expect(submitted, isTrue);
      expect(find.text('Upload return-fee receipt'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'cancelling receipt selection keeps submission disabled on a small phone',
    (tester) async {
      tester.view.physicalSize = const Size(320, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => TextButton(
                onPressed: () => showDialog<ReturnFeeReceiptSubmission>(
                  context: context,
                  builder: (_) => ReturnFeeReceiptDialog(
                    amount: '₱160.00',
                    accountName: 'Platform',
                    accountDetails: 'Receiving wallet',
                    pickReceipt: () async => null,
                  ),
                ),
                child: const Text('Upload'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Upload'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Choose receipt screenshot'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Choose receipt screenshot'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextButton>(
              find.widgetWithText(
                TextButton,
                'Submit receipt for verification',
              ),
            )
            .onPressed,
        isNull,
      );
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    },
  );
  for (final refreshFails in [false, true]) {
    testWidgets(
      'acceptance hides the button and shows unpaid fee with refresh failure $refreshFails',
      (tester) async {
        var accepted = false;
        final api = ApiService(
          client: MockClient((request) async {
            if (request.method == 'POST') {
              expect(jsonDecode(request.body)['action'], 'accept');
              accepted = true;
              return http.Response('{}', 200);
            }
            if (accepted && refreshFails) return http.Response('{}', 503);
            return http.Response(
              jsonEncode({
                'agreement': {
                  'id': 4,
                  'version': 2,
                  'workflow_policy': 'direct',
                  'status': 'proposed',
                  'resolution': 'return_refund',
                  'fault': 'buyer',
                  'payer': 'buyer',
                  'funding_method': 'deposit',
                  'merchandise_cents': 459900,
                  'transport_cents': 16000,
                  'net_refund_cents': 459900,
                  'quoted_at': '2026-11-08',
                  'seller_accepted_at': '2026-11-08',
                  'buyer_accepted_at': accepted ? '2026-11-08' : null,
                  'admin_decided_at': null,
                  'payments': [],
                  'allocations': [],
                },
                'budget_outstanding_cents': 16000,
                'receiving_account': {
                  'account_name': 'Platform',
                  'account_details': 'Test wallet',
                },
              }),
              200,
            );
          }),
        );
        tester.view.physicalSize = const Size(390, 1000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          MaterialApp(home: ReturnAgreementScreen(returnId: 4, api: api)),
        );
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.text('Accept agreement'));
        await tester.tap(find.text('Accept agreement'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Confirm'));
        await tester.pumpAndSettle();
        expect(accepted, isTrue);
        expect(find.text('Accept agreement'), findsNothing);
        expect(find.text('Agreement accepted'), findsOneWidget);
        expect(
          find.text('Return fee unpaid: ₱160.00. Pay it separately below.'),
          findsOneWidget,
        );
        expect(find.text('Request admin review'), findsNothing);
        await tester.scrollUntilVisible(
          find.text('Upload return-fee receipt'),
          250,
        );
        expect(find.text('Upload return-fee receipt'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'direct agreement offers payment without admin approval and supports suggestions',
    (tester) async {
      final posts = <Map<String, dynamic>>[];
      final api = ApiService(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            posts.add(
              Map<String, dynamic>.from(jsonDecode(request.body) as Map),
            );
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'agreement': {
                'id': 1,
                'version': 4,
                'workflow_policy': 'direct',
                'status': 'proposed',
                'resolution': 'return_refund',
                'fault': 'buyer',
                'payer': 'buyer',
                'funding_method': 'deposit',
                'merchandise_cents': 459900,
                'shipping_cents': 0,
                'transport_cents': 16000,
                'net_refund_cents': 459900,
                'quoted_at': '2026-11-08',
                'buyer_accepted_at': '2026-11-08',
                'seller_accepted_at': '2026-11-08',
                'admin_decided_at': null,
                'payments': [],
                'allocations': [
                  {
                    'role': 'collection_rider',
                    'amount_cents': 3000,
                    'status': 'reserved',
                  },
                ],
                'events': [
                  {
                    'actor_label': 'Seller',
                    'action': 'propose',
                    'note': 'Please return the incorrectly selected size.',
                  },
                ],
              },
              'receiving_account': {
                'account_name': 'Platform',
                'account_details': 'Test wallet',
              },
              'budget_outstanding_cents': 16000,
            }),
            200,
          );
        }),
      );
      tester.view.physicalSize = const Size(390, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(home: ReturnAgreementScreen(returnId: 3, api: api)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Accept agreement'), findsNothing);
      expect(find.text('Automatic return fees'), findsOneWidget);
      expect(find.text('Suggest changes'), findsOneWidget);
      expect(find.text('Decline proposal'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Upload return-fee receipt'),
        250,
      );
      expect(find.text('Upload return-fee receipt'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Suggest changes'), -250);
      await tester.tap(find.text('Suggest changes'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextFormField),
        'Please review the size shown in my order.',
      );
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(posts.single['action'], 'comment');
      expect(posts.single['version'], '4');
      expect(posts.single['note'], contains('review the size'));
      expect(tester.takeException(), isNull);
    },
  );
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
      expect(find.text('Upload return-fee receipt'), findsNothing);
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
