// ignore_for_file: avoid_relative_lib_imports

import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/core/theme/app_theme.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/delivery_detail_screen.dart';
import '../lib/services/delivery_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    final directory = Platform.environment['INVOIZ_TEST_FONT_DIR'];
    if (directory == null) return;
    await (FontLoader('Roboto')..addFont(
          File(
            '$directory/roboto-regular.ttf',
          ).readAsBytes().then(ByteData.sublistView),
        ))
        .load();
    await (FontLoader('MaterialIcons')..addFont(
          File(
            '$directory/materialicons-regular.otf',
          ).readAsBytes().then(ByteData.sublistView),
        ))
        .load();
  });
  for (final stage in ['sorted', 'dispatched', 'collected']) {
    testWidgets('360px center collection: $stage has the correct next action', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      SharedPreferences.setMockInitialValues({});
      String? requestedStatus;
      final backend = MockClient((request) async {
        if (request.method == 'PATCH') {
          requestedStatus = jsonDecode(request.body)['status'] as String?;
        }
        return http.Response(
          jsonEncode({
            'delivery': {
              'id': 77,
              'tracking_number': 'TRK-20261030-TEST',
              'order_id': 9001,
              'status': requestedStatus ?? 'accepted',
              'status_label': 'Accepted',
              'fulfillment_leg': 'delivery',
              'parcel_status': stage == 'sorted' ? 'sorted' : 'dispatched',
              'sorting_center_handoff_at': '2026-10-30T10:00:00Z',
              'sorting_center_pickup_at': stage == 'collected'
                  ? '2026-10-30T11:00:00Z'
                  : null,
              'logistics_center': {'name': 'San Pablo Logistics Center'},
              'pickup_location': {
                'name': 'San Pablo Logistics Center',
                'address': '3 Center Street, San Pablo',
              },
              'shop': {'name': 'Original Seller', 'address': '1 Shop Street'},
              'customer': {'name': 'Test Buyer', 'address': '2 Buyer Street'},
              'items': [
                {
                  'id': 1,
                  'name': 'Test item',
                  'price': 100,
                  'quantity': 1,
                  'subtotal': 100,
                },
              ],
              'subtotal': 100,
              'delivery_fee': 50,
              'total': 150,
              'payment_method': 'cash_on_delivery',
              'amount_to_collect': 150,
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });
      final api = ApiClient(client: backend);
      final provider = DeliveryProvider(DeliveryService(api));
      final repaintKey = GlobalKey();
      final baseTheme = AppTheme.light();
      final previewTheme = Platform.environment['INVOIZ_TEST_FONT_DIR'] == null
          ? baseTheme
          : baseTheme.copyWith(
              textTheme: baseTheme.textTheme.apply(fontFamily: 'Roboto'),
              appBarTheme: baseTheme.appBarTheme.copyWith(
                titleTextStyle: baseTheme.appBarTheme.titleTextStyle?.copyWith(
                  fontFamily: 'Roboto',
                ),
              ),
              elevatedButtonTheme: ElevatedButtonThemeData(
                style: baseTheme.elevatedButtonTheme.style?.copyWith(
                  textStyle: const WidgetStatePropertyAll(
                    TextStyle(
                      fontFamily: 'Roboto',
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            );
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(
              value: AuthProvider(api: api, storage: TokenStorage()),
            ),
            ChangeNotifierProvider<DeliveryProvider>.value(value: provider),
          ],
          child: RepaintBoundary(
            key: repaintKey,
            child: MaterialApp(
              theme: previewTheme,
              home: const DeliveryDetailScreen(deliveryId: 77),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Head to Shop'), findsNothing);
      expect(find.text('3 Center Street, San Pablo'), findsOneWidget);
      if (stage == 'sorted') {
        expect(
          find.textContaining('Waiting for logistics staff'),
          findsOneWidget,
        );
        expect(find.text('Pick Up from Sorting Center'), findsNothing);
      } else if (stage == 'dispatched') {
        expect(find.text('Pick Up from Sorting Center'), findsOneWidget);
      } else {
        expect(find.text('Out for Delivery'), findsOneWidget);
      }
      expect(tester.takeException(), isNull);
      final directory = Platform.environment['INVOIZ_TEST_SCREENSHOTS'];
      if (directory != null) {
        final boundary =
            repaintKey.currentContext!.findRenderObject()!
                as RenderRepaintBoundary;
        await tester.runAsync(() async {
          final image = await boundary.toImage();
          final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
          await File(
            '$directory/center-$stage-360.png',
          ).writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }
      if (stage == 'collected') {
        await tester.tap(find.text('Out for Delivery'));
        await tester.pumpAndSettle();
        expect(requestedStatus, 'out_for_delivery');
      }
    });
  }
}
