import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/remittances/return_remittances_screen.dart';

void main() {
  test(
    'shared return requests preserve authentication and pagination',
    () async {
      final api = ApiClient(
        client: MockClient((request) async {
          expect(request.url.path, '/api/return-remittances');
          expect(request.url.queryParameters['page'], '2');
          expect(request.headers['Authorization'], 'Bearer return-test-token');
          return http.Response('{}', 200);
        }),
      );
      api.setToken('return-test-token');
      await api.getSharedAuthenticated('return-remittances?page=2');
    },
  );

  testWidgets(
    'receipt dismissal never accepts money; explicit confirmation does',
    (tester) async {
      var accepted = false;
      var posts = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer return-test-token');
          if (request.method == 'POST') {
            posts++;
            expect(jsonDecode(request.body)['action'], 'accept');
            accepted = true;
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'accepted_earnings_cents': accepted ? 1000 : 0,
              'allocations': {
                'data': [
                  {
                    'id': 1,
                    'role': 'collection_rider',
                    'amount_cents': 1000,
                    'status': accepted ? 'accepted' : 'remitted',
                    'can_accept': true,
                    'settlement': {'return_request_id': 11},
                  },
                ],
                'next_page_url': null,
              },
            }),
            200,
          );
        }),
      );
      api.setToken('return-test-token');
      final auth = AuthProvider(api: api, storage: TokenStorage());
      tester.view.physicalSize = const Size(320, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        ChangeNotifierProvider.value(
          value: auth,
          child: const MaterialApp(home: ReturnRemittancesScreen()),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        find.text('Confirmed return service earnings: ₱0.00'),
        findsOneWidget,
      );
      await tester.tap(find.text('Accept remittance received'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Not yet'));
      await tester.pumpAndSettle();
      expect(posts, 0);
      await tester.tap(find.text('Accept remittance received'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Accept receipt'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(
        find.text('Confirmed return service earnings: ₱10.00'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );
}
