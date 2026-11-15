import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/theme/app_theme.dart';
import '../lib/screens/deliveries/failed_parcel_returns_screen.dart';

Future<void> pumpJobs(WidgetTester tester, MockClient client) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: AppTheme.light().copyWith(splashFactory: NoSplash.splashFactory),
      home: FailedParcelReturnsScreen(api: ApiClient(client: client)),
    ),
  );
  await tester.pumpAndSettle();
}

http.Response jsonResponse(Object value) => http.Response(
  jsonEncode(value),
  200,
  headers: {'content-type': 'application/json'},
);

void main() {
  testWidgets('unfunded seller returns have no transport action', (
    tester,
  ) async {
    await pumpJobs(
      tester,
      MockClient(
        (request) async => jsonResponse({
          'cases': [
            {
              'delivery_id': 9,
              'tracking_number': 'SYNTHETIC-RETURN',
              'decision': 'return',
              'stage': 'seller_assigned',
              'funded': false,
              'is_rider': true,
            },
          ],
          'last_page': 1,
        }),
      ),
    );
    expect(find.text('Waiting for verified seller funding.'), findsOneWidget);
    expect(find.text('Confirm collection for seller'), findsNothing);
  });

  testWidgets(
    'assigned rider confirms actual collection before seller delivery',
    (tester) async {
      var collected = false;
      var posts = 0;
      await pumpJobs(
        tester,
        MockClient((request) async {
          if (request.method == 'POST') {
            expect(jsonDecode(request.body)['action'], 'rider_collect');
            posts++;
            collected = true;
            return jsonResponse({'message': 'Collected'});
          }
          return jsonResponse({
            'cases': [
              {
                'delivery_id': 9,
                'tracking_number': 'SYNTHETIC-RETURN',
                'decision': 'return',
                'stage': collected ? 'seller_delivery' : 'seller_assigned',
                'funded': true,
                'is_rider': true,
                'seller_address': 'Synthetic seller address',
              },
            ],
            'last_page': 1,
          });
        }),
      );
      await tester.tap(find.text('Confirm collection for seller'));
      await tester.pumpAndSettle();
      expect(posts, 0);
      await tester.tap(find.text('Not yet'));
      await tester.pumpAndSettle();
      expect(posts, 0);
      await tester.tap(find.text('Confirm collection for seller'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(find.text('Record seller handover photo'), findsOneWidget);
    },
  );

  testWidgets('driver must accept the funded return trip before departure', (
    tester,
  ) async {
    var accepted = false;
    await pumpJobs(
      tester,
      MockClient((request) async {
        if (request.method == 'POST') {
          expect(jsonDecode(request.body)['action'], 'driver_accept');
          accepted = true;
          return jsonResponse({'message': 'Accepted'});
        }
        return jsonResponse({
          'cases': [
            {
              'delivery_id': 10,
              'tracking_number': 'SYNTHETIC-TRIP',
              'decision': 'return',
              'stage': 'at_hub',
              'funded': true,
              'is_driver': true,
              'driver_accepted': accepted,
            },
          ],
          'last_page': 1,
        });
      }),
    );
    expect(find.text('Depart with return parcel'), findsNothing);
    await tester.tap(find.text('Accept return trip'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    expect(accepted, isTrue);
    expect(find.text('Depart with return parcel'), findsOneWidget);
  });
}
