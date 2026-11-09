import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/widgets/return_jobs_summary.dart';
import 'package:invoize_rider/screens/deliveries/return_collections_screen.dart';

void main() {
  for (final width in [320.0, 390.0]) {
    testWidgets(
      'assigned buyer pickup is visible and opens return jobs at $width',
      (tester) async {
        final api = ApiClient(
          client: MockClient((request) async {
            expect(request.url.path, endsWith('/rider/returns'));
            return http.Response(
              jsonEncode({
                'returns': [
                  {
                    'id': 5,
                    'order_id': 88,
                    'product': 'Returned speaker',
                    'quantity': 1,
                    'stage': 'pickup_assigned',
                    'center_id': 10,
                    'buyer_name': 'Buyer',
                    'collection_address': 'Buyer collection address',
                  },
                ],
              }),
              200,
            );
          }),
        );
        tester.view.physicalSize = Size(width, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.light(),
            home: Scaffold(body: ReturnJobsSummary(api: api)),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('1 return assignment'), findsOneWidget);
        expect(find.textContaining('Return #5'), findsOneWidget);
        await tester.tap(find.text('1 return assignment'));
        await tester.pumpAndSettle();
        expect(find.byType(ReturnCollectionsScreen), findsOneWidget);
        expect(find.text('Confirm collection'), findsOneWidget);
        expect(find.text('Buyer collection address'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'driver sees its return transfer and accepts it independently of forward transfers',
    (tester) async {
      var accepted = false;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(request.url.path, endsWith('/courier/returns/5'));
            expect(jsonDecode(request.body)['action'], 'accept');
            accepted = true;
            return http.Response('{}', 200);
          }
          expect(request.url.path, endsWith('/courier/returns'));
          return http.Response(
            jsonEncode({
              'returns': [
                {
                  'id': 5,
                  'order_id': 88,
                  'product': 'Returned speaker',
                  'quantity': 1,
                  'stage': 'in_transfer',
                  'center_id': 10,
                  'origin_hub': 'AILYN HUB',
                  'destination_hub': 'NorthStar',
                  'driver_accepted_at': accepted ? '2026-11-08' : null,
                },
              ],
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light(),
          home: ReturnCollectionsScreen(courier: true, api: api),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('From: AILYN HUB'), findsOneWidget);
      expect(find.text('To: NorthStar'), findsOneWidget);
      await tester.ensureVisible(find.text('Accept return transfer'));
      await tester.tap(find.text('Accept return transfer'));
      await tester.pumpAndSettle();
      expect(accepted, isTrue);
      expect(find.text('Record arrival at destination hub'), findsOneWidget);
      expect(find.text('Confirm collection'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
