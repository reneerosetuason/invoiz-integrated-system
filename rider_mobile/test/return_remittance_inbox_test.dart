import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/providers/remittance_provider.dart';
import 'package:invoize_rider/services/remittance_service.dart';
import 'package:invoize_rider/screens/remittances/remittances_screen.dart';
import 'package:invoize_rider/screens/remittances/hub_courier_remittances_screen.dart';

void main() {
  testWidgets(
    'resuming the normal remittance screen fetches newly released return pay',
    (tester) async {
      var released = false;
      var posts = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'POST') posts++;
          return http.Response(
            jsonEncode({
              'remittances': [
                {
                  'id': 3,
                  'source': 'return',
                  'return_id': 5,
                  'amount': 30,
                  'status': released ? 'remitted' : 'pending_remittance',
                },
              ],
              'pagination': {'last_page': 1},
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light(),
          home: HubCourierRemittancesScreen(api: api),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Accept remittance'), findsNothing);
      released = true;
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(find.text('Accept remittance'), findsOneWidget);
      expect(posts, 0);
    },
  );
  for (final courier in [false, true]) {
    for (final width in [320.0, 390.0]) {
      testWidgets(
        'orders and returns share ${courier ? 'driver' : 'rider'} remittances and same numeric IDs remain separate at $width',
        (tester) async {
          var accepted = false;
          var posts = 0;
          final api = ApiClient(
            client: MockClient((request) async {
              expect(request.headers['Authorization'], 'Bearer unified-test');
              Map<String, dynamic> payload;
              if (request.method == 'POST') {
                expect(
                  request.url.path,
                  '/api/remittances/incoming/return/11/accept',
                );
                expect(jsonDecode(request.body), {'confirm_received': 1});
                posts++;
                accepted = true;
                payload = {'message': 'Confirmed'};
              } else if (request.url.path.endsWith('/remittances/incoming')) {
                payload = {
                  'remittances': [
                    {
                      'id': 11,
                      'source': 'return',
                      'return_id': 5,
                      'amount': 30,
                      'status': accepted ? 'accepted' : 'remitted',
                      'order_number': 'ORDER-88',
                      'role_label': 'Return pickup rider',
                      'bank_reference': 'RETURN-RELEASE-11',
                    },
                    {
                      'id': 11,
                      'source': 'order',
                      'amount': 50,
                      'status': 'remitted',
                      'order_number': 'ORDER-88',
                      'role_label': 'Original delivery rider',
                      'bank_reference': 'ORDER-TRANSFER-11',
                    },
                  ],
                  'pagination': {'current_page': 1, 'last_page': 1},
                };
              } else if (request.url.path.endsWith('/rider/remittances')) {
                payload = {'remittances': [], 'summary': {}};
              } else {
                throw StateError('Unexpected endpoint ${request.url.path}');
              }
              return http.Response(
                jsonEncode(payload),
                200,
                headers: {'content-type': 'application/json'},
              );
            }),
          );
          api.setToken('unified-test');
          tester.view.physicalSize = Size(width, 900);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          await tester.pumpWidget(
            MultiProvider(
              providers: [
                ChangeNotifierProvider(
                  create: (_) =>
                      AuthProvider(api: api, storage: TokenStorage()),
                ),
                ChangeNotifierProvider(
                  create: (_) => RemittanceProvider(RemittanceService(api)),
                ),
              ],
              child: MaterialApp(
                theme: AppTheme.light(),
                home: courier
                    ? const HubCourierRemittancesScreen()
                    : const RemittancesScreen(),
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(find.textContaining('Return #5'), findsOneWidget);
          expect(find.text('Return-delivery remittances'), findsNothing);
          expect(find.text('Verify remittance account'), findsNothing);
          final returnCard = find.ancestor(
            of: find.textContaining('Return #5'),
            matching: find.byType(Card),
          );
          final button = find.descendant(
            of: returnCard,
            matching: find.text(
              courier ? 'Accept remittance' : 'Confirm payout received',
            ),
          );
          await tester.ensureVisible(button);
          await tester.tap(button);
          await tester.pumpAndSettle();
          await tester.tap(find.text(courier ? 'Cancel' : 'Not yet'));
          await tester.pumpAndSettle();
          expect(posts, 0);
          await tester.tap(button);
          await tester.pumpAndSettle();
          await tester.tap(
            find.text(courier ? 'Confirm receipt' : 'Yes, received'),
          );
          await tester.pumpAndSettle();
          expect(posts, 1);
          expect(accepted, isTrue);
          expect(
            find.text(
              courier
                  ? 'Receipt confirmed'
                  : 'Receipt accepted · credited to earnings',
            ),
            findsOneWidget,
          );
          expect(
            find.text(
              courier ? 'Accept remittance' : 'Confirm payout received',
            ),
            findsOneWidget,
          );
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
  testWidgets('failed confirmation leaves a return payment awaiting receipt', (
    tester,
  ) async {
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'POST') {
          return http.Response('{"message":"Payment is not released."}', 422);
        }
        return http.Response(
          jsonEncode({
            'remittances': [
              {
                'id': 11,
                'source': 'return',
                'return_id': 5,
                'amount': 30,
                'status': 'remitted',
              },
            ],
            'pagination': {'last_page': 1},
          }),
          200,
        );
      }),
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: HubCourierRemittancesScreen(api: api),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Accept remittance'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm receipt'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Payment is not released.'), findsOneWidget);
    expect(find.text('Receipt confirmed'), findsNothing);
    expect(find.text('Accept remittance'), findsOneWidget);
  });
}
