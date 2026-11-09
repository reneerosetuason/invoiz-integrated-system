import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/screens/remittances/return_remittances_screen.dart';

void main() {
  test(
    'shared remittance requests preserve authentication and pagination',
    () async {
      final api = ApiClient(
        client: MockClient((request) async {
          expect(request.url.path, '/api/remittances/incoming');
          expect(request.url.queryParameters['page'], '2');
          expect(request.headers['Authorization'], 'Bearer test-token');
          return http.Response('{}', 200);
        }),
      );
      api.setToken('test-token');
      await api.getSharedAuthenticated('remittances/incoming?page=2');
    },
  );
  testWidgets(
    'old return shortcut opens normal confirmation without separate receiving details',
    (tester) async {
      var accepted = false;
      var posts = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(
              request.url.path,
              '/api/remittances/incoming/return/1/accept',
            );
            posts++;
            accepted = true;
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'remittances': [
                {
                  'id': 1,
                  'source': 'return',
                  'return_id': 11,
                  'amount': 10,
                  'status': accepted ? 'accepted' : 'remitted',
                },
              ],
              'pagination': {'last_page': 1},
            }),
            200,
          );
        }),
      );
      tester.view.physicalSize = const Size(320, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light(),
          home: ReturnRemittancesScreen(api: api),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Remittances'), findsOneWidget);
      expect(find.text('Verify remittance account'), findsNothing);
      await tester.tap(find.text('Accept remittance'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(posts, 0);
      await tester.tap(find.text('Accept remittance'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm receipt'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(find.text('Receipt confirmed'), findsOneWidget);
      expect(find.text('Accept remittance'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
