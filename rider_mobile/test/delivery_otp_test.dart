import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/models/delivery.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/proof_of_delivery_screen.dart';
import '../lib/services/delivery_service.dart';

Map<String, dynamic> _delivery({
  String status = 'out_for_delivery',
  String statusLabel = 'Out for Delivery',
  bool otpRequired = true,
}) => {
  'id': 77,
  'tracking_number': 'TRK-OTP-77',
  'order_id': 9001,
  'status': status,
  'status_label': statusLabel,
  'shop': {'name': 'Shop'},
  'customer': {'name': 'Cust', 'address': '1 Way'},
  'items': [],
  'subtotal': 0,
  'delivery_fee': 50,
  'total': 50,
  'payment_method': 'prepaid',
  'otp_required': otpRequired,
  'pickup_pin_required': false,
};

http.Response _json(Object data, [int status = 200]) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);

MockClient _backend({
  String correctOtp = '123456',
  void Function(http.Request request)? onRequest,
}) {
  return MockClient((http.BaseRequest request) async {
    final path = request.url.path;

    if (RegExp(r'/rider/deliveries/\d+$').hasMatch(path) &&
        request.method == 'GET') {
      return _json({'delivery': _delivery()});
    }

    if (path.endsWith('/rider/deliveries/77/complete') &&
        request.method == 'POST') {
      final http.Request post = request as http.Request;
      onRequest?.call(post);

      // Proof-of-delivery completion with a photo is sent as multipart, so
      // the OTP lives in the form body rather than a JSON payload.
      final contentType = post.headers['content-type'] ?? '';
      String? otp;
      if (contentType.contains('multipart/form-data')) {
        // The photo bytes make the body non-UTF8, so decode leniently and
        // read the plain-text OTP form field.
        final body = latin1.decode(post.bodyBytes, allowInvalid: true);
        otp = RegExp(r'name="otp"\r\n\r\n([^\r\n]*)').firstMatch(body)?.group(1);
      } else {
        final body = jsonDecode(post.body) as Map<String, dynamic>;
        otp = body['otp'] as String?;
      }

      if (otp != correctOtp) {
        return _json({
          'message': 'Incorrect delivery OTP. 4 attempts remaining.',
          'errors': {
            'otp': ['Incorrect delivery OTP. 4 attempts remaining.'],
          },
        }, 422);
      }

      return _json({
        'message': 'Delivery completed successfully.',
        'earned': 50,
        'delivery': _delivery(status: 'delivered', statusLabel: 'Delivered'),
      });
    }

    return _json({'message': 'Not found.'}, 404);
  });
}

void _disableLocation() {
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(
        const MethodChannel('flutter.baseflow.com/geolocator'),
        (call) async {
          if (call.method == 'isLocationServiceEnabled') {
            return false;
          }
          return null;
        },
      );
}

/// A real 1x1 PNG so the image widget can decode it in tests while still
/// passing the proof-photo content check.
Uint8List _proofPhoto() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhf'
  'DwAChwGA60e6kgAAAABJRU5ErkJggg==',
);

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('Delivery OTP model and request contract', () {
    test('parses otp_required and falls back to order-backed deliveries', () {
      final required = Delivery.fromJson(_delivery());
      expect(required.otpRequired, isTrue);

      final optionalJson = _delivery(otpRequired: false);
      final optional = Delivery.fromJson(optionalJson);
      expect(optional.otpRequired, isFalse);

      final noOrder = Map<String, dynamic>.from(_delivery())
        ..remove('order_id')
        ..remove('otp_required');
      expect(Delivery.fromJson(noOrder).otpRequired, isFalse);
    });

    test('completion payload includes the buyer OTP', () async {
      Map<String, dynamic>? payload;
      final api = ApiClient(
        client: _backend(
          onRequest: (request) {
            payload = jsonDecode(request.body) as Map<String, dynamic>;
          },
        ),
      );

      final delivery = await DeliveryService(
        api,
      ).complete(77, proofType: 'otp', otp: '123456');

      expect(payload?['otp'], '123456');
      expect(payload?['proof_type'], 'otp');
      expect(delivery.status, 'delivered');
    });

    test('API rejection leaves the local delivery incomplete', () async {
      final api = ApiClient(client: _backend());
      final provider = DeliveryProvider(DeliveryService(api));

      await provider.loadDetail(77);
      expect(provider.selected?.status, 'out_for_delivery');

      final ok = await provider.complete(77, proofType: 'otp', otp: '000000');

      expect(ok, isFalse);
      expect(provider.selected?.status, 'out_for_delivery');
      expect(provider.error, contains('Incorrect delivery OTP'));
    });
  });

  group('ProofOfDeliveryScreen OTP flow', () {
    testWidgets('requires OTP input and blocks an empty submission', (
      tester,
    ) async {
      var requests = 0;
      final backend = _backend(onRequest: (_) => requests++);
      final api = ApiClient(client: backend);
      final provider = DeliveryProvider(DeliveryService(api));
      await provider.loadDetail(77);

      await tester.pumpWidget(
        ChangeNotifierProvider<DeliveryProvider>.value(
          value: provider,
          child: MaterialApp(
            home: ProofOfDeliveryScreen(
              deliveryId: 77,
              photoPicker: () async => _proofPhoto(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();

      final otpField = find.byKey(const Key('deliveryOtpField'));
      await tester.ensureVisible(otpField);
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();

      expect(
        find.text('Enter the 6-digit delivery OTP provided by the buyer.'),
        findsOneWidget,
      );
      expect(requests, 0);
      expect(provider.selected?.status, 'out_for_delivery');
    });

    testWidgets('server OTP rejection stays on the proof screen', (
      tester,
    ) async {
      _disableLocation();
      var requests = 0;
      final backend = _backend(onRequest: (_) => requests++);
      final api = ApiClient(client: backend);
      final provider = DeliveryProvider(DeliveryService(api));
      await provider.loadDetail(77);

      await tester.pumpWidget(
        ChangeNotifierProvider<DeliveryProvider>.value(
          value: provider,
          child: MaterialApp(
            home: ProofOfDeliveryScreen(
              deliveryId: 77,
              photoPicker: () async => _proofPhoto(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();

      final otpField = find.byKey(const Key('deliveryOtpField'));
      await tester.ensureVisible(otpField);
      await tester.enterText(otpField, '000000');
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      expect(requests, 1);
      expect(find.textContaining('Incorrect delivery OTP'), findsOneWidget);
      expect(find.text('Proof of Delivery'), findsOneWidget);
      expect(provider.selected?.status, 'out_for_delivery');
      expect(find.text('Delivered'), findsNothing);
    });

    testWidgets('successful OTP completion updates only after the server', (
      tester,
    ) async {
      _disableLocation();
      var requests = 0;
      final backend = _backend(onRequest: (_) => requests++);
      final api = ApiClient(client: backend);
      final provider = DeliveryProvider(DeliveryService(api));
      await provider.loadDetail(77);

      await tester.pumpWidget(
        ChangeNotifierProvider<DeliveryProvider>.value(
          value: provider,
          child: MaterialApp(
            home: ProofOfDeliveryScreen(
              deliveryId: 77,
              photoPicker: () async => _proofPhoto(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();

      final otpField = find.byKey(const Key('deliveryOtpField'));
      await tester.ensureVisible(otpField);
      await tester.enterText(otpField, '123456');
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      expect(requests, 1);
      expect(provider.selected?.status, 'delivered');
      await tester.pump(const Duration(milliseconds: 600));
      expect(find.byType(SnackBar), findsOneWidget);
      expect(find.text('Delivery completed. Thank you!'), findsOneWidget);
    });
  });
}
