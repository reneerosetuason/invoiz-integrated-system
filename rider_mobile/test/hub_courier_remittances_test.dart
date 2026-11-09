import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/remittances/hub_courier_remittances_screen.dart';

http.Response response(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

Map<String, dynamic> page(String status) => {
  'remittances': [
    {
      'id': 9,
      'amount': 20,
      'status': status,
      'transfer_reference': 'HT-TEST',
      'bank_reference': 'BANK-123',
    },
  ],
  'pagination': {'current_page': 1, 'last_page': 1},
};

Future<void> pumpScreen(WidgetTester tester, ApiClient api) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    ChangeNotifierProvider(
      create: (_) => AuthProvider(api: api, storage: TokenStorage()),
      child: MaterialApp(
        theme: AppTheme.light(),
        home: const HubCourierRemittancesScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (_) async => null);
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  testWidgets(
    'released payments require confirmation and reload accepted status',
    (tester) async {
      var accepted = false;
      var posts = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(
              request.url.path,
              endsWith('/remittances/incoming/order/9/accept'),
            );
            expect(jsonDecode(request.body)['confirm_received'], 1);
            posts++;
            accepted = true;
            return response({'message': 'Accepted'});
          }
          return response(page(accepted ? 'accepted' : 'remitted'));
        }),
      );
      await pumpScreen(tester, api);
      expect(
        find.text('Transfer / release reference: BANK-123'),
        findsOneWidget,
      );
      await tester.tap(find.text('Accept remittance'));
      await tester.pumpAndSettle();
      expect(posts, 0);
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

  testWidgets('held and pending remittances have no acceptance action', (
    tester,
  ) async {
    for (final status in ['held_in_escrow', 'pending_remittance']) {
      final api = ApiClient(
        client: MockClient((_) async => response(page(status))),
      );
      await pumpScreen(tester, api);
      expect(find.text('Accept remittance'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    }
  });

  testWidgets('failed acceptance does not mark a payment as received', (
    tester,
  ) async {
    final api = ApiClient(
      client: MockClient(
        (request) async => request.method == 'POST'
            ? response({'message': 'Payment is no longer released.'}, 409)
            : response(page('remitted')),
      ),
    );
    await pumpScreen(tester, api);
    await tester.tap(find.text('Accept remittance'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm receipt'));
    await tester.pumpAndSettle();
    expect(find.text('Payment is no longer released.'), findsOneWidget);
    expect(find.text('Receipt confirmed'), findsNothing);
    expect(find.text('Accept remittance'), findsOneWidget);
  });
}
