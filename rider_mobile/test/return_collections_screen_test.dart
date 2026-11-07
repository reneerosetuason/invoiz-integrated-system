import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/deliveries/return_collections_screen.dart';

void main() {
  testWidgets(
    'return collection requires confirmation, collects no cash and refreshes custody',
    (tester) async {
      var collected = false;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            expect(request.url.path, endsWith('/rider/returns/7/pickup'));
            expect(
              request.headers['content-type'],
              contains('multipart/form-data'),
            );
            final multipartBody = latin1.decode(request.bodyBytes);
            expect(multipartBody, contains('name="proof"'));
            expect(multipartBody, contains('pickup'));
            collected = true;
            return http.Response(
              '{"message":"Collected"}',
              200,
              headers: {'content-type': 'application/json'},
            );
          }
          return http.Response(
            jsonEncode({
              'returns': [
                {
                  'id': 7,
                  'order_id': 44,
                  'product': 'Returned bag',
                  'quantity': 1,
                  'stage': collected ? 'collected' : 'pickup_assigned',
                  'center_id': 2,
                  'buyer_name': 'Buyer',
                  'buyer_phone': '09170000000',
                  'collection_address': 'Collection street',
                  'amount_to_collect': 0,
                },
              ],
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      tester.view.physicalSize = const Size(360, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        ChangeNotifierProvider(
          create: (_) => AuthProvider(api: api, storage: TokenStorage()),
          child: MaterialApp(
            theme: AppTheme.light().copyWith(
              splashFactory: NoSplash.splashFactory,
            ),
            home: ReturnCollectionsScreen(
              takeHandoverPhoto: () async => XFile.fromData(
                Uint8List.fromList([137, 80, 78, 71]),
                name: 'handover.png',
                mimeType: 'image/png',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Collection street'), findsOneWidget);
      expect(find.textContaining('No cash to collect'), findsOneWidget);
      await tester.tap(find.text('Confirm collection'));
      await tester.pumpAndSettle();
      expect(collected, isFalse);
      await tester.tap(
        find.widgetWithText(TextButton, 'Confirm collection').last,
      );
      await tester.pumpAndSettle();
      expect(collected, isTrue);
      expect(
        find.text('Collected. Awaiting hub staff receipt.'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );
}
