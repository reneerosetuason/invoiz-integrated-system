import 'dart:async';
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
import '../lib/core/network/api_exception.dart';
import '../lib/models/delivery.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/proof_of_delivery_screen.dart';
import '../lib/services/delivery_service.dart';
import '../lib/services/pod_draft_store.dart';

Map<String, dynamic> _delivery({
  String status = 'out_for_delivery',
  String statusLabel = 'Out for Delivery',
  String paymentMethod = 'prepaid',
  double? amountToCollect,
}) => {
  'id': 77,
  'tracking_number': 'TRK-COMPLETE-77',
  'order_id': 9001,
  'status': status,
  'status_label': statusLabel,
  'shop': {'name': 'Shop'},
  'customer': {'name': 'Cust', 'address': '1 Way'},
  'items': [],
  'subtotal': 0,
  'delivery_fee': 50,
  'total': 50,
  'payment_method': paymentMethod,
  'amount_to_collect': amountToCollect,
  'pickup_pin_required': false,
};

http.Response _json(Object data, [int status = 200]) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);

class _Backend {
  _Backend({
    this.paymentMethod = 'prepaid',
    this.amountToCollect,
    this.reject = false,
    this.onRequest,
    this.delay,
    this.hangPost = false,
    this.markCompletedOnPost = false,
  });

  final String paymentMethod;
  final double? amountToCollect;
  final bool reject;
  final void Function(http.Request request)? onRequest;
  final Duration? delay;

  /// Never answers the completion POST, simulating a stalled upload.
  final bool hangPost;

  /// Records the delivery as completed as soon as the POST arrives, before
  /// the (lost) response. Simulates a timeout after a server commit.
  final bool markCompletedOnPost;

  int posts = 0;
  int gets = 0;
  bool completed = false;

  MockClient get client => MockClient((http.BaseRequest request) async {
    final path = request.url.path;

    if (RegExp(r'/rider/deliveries/\d+$').hasMatch(path) &&
        request.method == 'GET') {
      gets++;
      return _json({
        'delivery': _delivery(
          status: completed ? 'delivered' : 'out_for_delivery',
          statusLabel: completed ? 'Delivered' : 'Out for Delivery',
          paymentMethod: paymentMethod,
          amountToCollect: amountToCollect,
        ),
      });
    }

    if (path.endsWith('/rider/deliveries/77/complete') &&
        request.method == 'POST') {
      posts++;
      onRequest?.call(request as http.Request);

      if (markCompletedOnPost) completed = true;
      if (delay != null) await Future<void>.delayed(delay!);
      if (hangPost) return Completer<http.Response>().future;

      if (reject) {
        return _json({
          'message': 'Delivery completion failed.',
          'errors': {
            'photo': ['The delivery photo must be a valid image.'],
          },
        }, 422);
      }

      completed = true;
      return _json({
        'message': 'Delivery completed successfully.',
        'earned': 50,
        'delivery': _delivery(
          status: 'delivered',
          statusLabel: 'Delivered',
          paymentMethod: paymentMethod,
          amountToCollect: amountToCollect,
        ),
      });
    }

    return _json({'message': 'Not found.'}, 404);
  });
}

/// A real 1x1 PNG so the image widget can decode it in tests while still
/// passing the proof-photo content check.
Uint8List _proofPhoto() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhf'
  'DwAChwGA60e6kgAAAABJRU5ErkJggg==',
);

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

Future<DeliveryProvider> _load(
  _Backend backend, {
  Duration uploadTimeout = const Duration(seconds: 45),
}) async {
  final provider = DeliveryProvider(
    DeliveryService(
      ApiClient(client: backend.client, uploadTimeout: uploadTimeout),
    ),
  );
  await provider.loadDetail(77);
  return provider;
}

Future<void> _pump(
  WidgetTester tester,
  DeliveryProvider provider, {
  Future<Uint8List?> Function()? photoPicker,
  Future<DeliveryLocation?> Function()? locationReader,
  PodDraftStore? draftStore,
}) async {
  await tester.pumpWidget(
    ChangeNotifierProvider<DeliveryProvider>.value(
      value: provider,
      child: MaterialApp(
        home: ProofOfDeliveryScreen(
          deliveryId: 77,
          photoPicker: photoPicker ?? () async => _proofPhoto(),
          locationReader: locationReader,
          draftStore: draftStore,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    _disableLocation();
  });

  test('delivery model reads payment type without OTP metadata', () {
    final prepaid = Delivery.fromJson(_delivery());
    expect(prepaid.isCashOnDelivery, isFalse);

    final cod = Delivery.fromJson(
      _delivery(paymentMethod: 'cash_on_delivery', amountToCollect: 500),
    );
    expect(cod.isCashOnDelivery, isTrue);
    expect(cod.amountToCollect, 500);
  });

  test(
    'completion request is multipart photo proof with no OTP field',
    () async {
      http.Request? request;
      final backend = _Backend(onRequest: (value) => request = value);
      final service = DeliveryService(ApiClient(client: backend.client));

      final delivery = await service.complete(
        77,
        photoBytes: _proofPhoto(),
        photoFilename: 'proof.png',
        signatureName: 'Cust',
      );

      expect(delivery.status, 'delivered');
      expect(request, isNotNull);
      final body = latin1.decode(request!.bodyBytes, allowInvalid: true);
      expect(body, contains('name="photo"'));
      expect(body, contains('name="signature_name"'));
      expect(body, isNot(contains('name="otp"')));
      expect(body, isNot(contains('proof_type')));
    },
  );

  test('API rejection leaves the local delivery incomplete', () async {
    final backend = _Backend(reject: true);
    final provider = await _load(backend);

    final ok = await provider.complete(77, photoBytes: _proofPhoto());

    expect(ok, isFalse);
    expect(provider.selected?.status, 'out_for_delivery');
    expect(provider.error, contains('Delivery completion failed'));
  });

  group('ProofOfDeliveryScreen', () {
    testWidgets('prepaid completion needs only the photo', (tester) async {
      final backend = _Backend();
      final provider = await _load(backend);
      await _pump(tester, provider);

      expect(find.textContaining('Payment already received'), findsOneWidget);
      expect(find.byKey(const Key('codAmountField')), findsNothing);
      expect(find.byKey(const Key('deliveryOtpField')), findsNothing);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      expect(backend.posts, 1);
      expect(provider.selected?.status, 'delivered');
      expect(find.text('Delivery completed. Thank you!'), findsOneWidget);
    });

    testWidgets('COD shows the amount to collect and blocks a missing amount', (
      tester,
    ) async {
      final backend = _Backend(
        paymentMethod: 'cash_on_delivery',
        amountToCollect: 500,
      );
      final provider = await _load(backend);
      await _pump(tester, provider);

      expect(find.text('Amount to Collect'), findsOneWidget);
      expect(find.text('₱500.00'), findsOneWidget);
      expect(find.textContaining('Payment already received'), findsNothing);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();

      expect(
        find.text('Enter a valid amount received from the customer.'),
        findsOneWidget,
      );
      expect(backend.posts, 0);
      expect(provider.selected?.status, 'out_for_delivery');
    });

    testWidgets('COD confirmation sends the collected cash with the photo', (
      tester,
    ) async {
      http.Request? request;
      final backend = _Backend(
        paymentMethod: 'cash_on_delivery',
        amountToCollect: 500,
        onRequest: (value) => request = value,
      );
      final provider = await _load(backend);
      await _pump(tester, provider);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('codAmountField')), '500');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      expect(backend.posts, 1);
      final body = latin1.decode(request!.bodyBytes, allowInvalid: true);
      expect(body, contains('name="photo"'));
      expect(body, contains('name="amount_received"'));
      expect(body, contains('500.00'));
      expect(body, isNot(contains('name="otp"')));
      expect(provider.selected?.status, 'delivered');
    });
  });

  group('submission resilience', () {
    test(
      'a stalled multipart response body times out instead of hanging',
      () async {
        final controller = StreamController<List<int>>();
        addTearDown(controller.close);
        final client = MockClient.streaming(
          (request, bodyStream) async => http.StreamedResponse(
            controller.stream,
            200,
            headers: {'content-type': 'application/json'},
          ),
        );
        final api = ApiClient(
          client: client,
          uploadTimeout: const Duration(milliseconds: 100),
        );

        await expectLater(
          DeliveryService(api).complete(77, photoBytes: _proofPhoto()),
          throwsA(
            isA<ApiException>().having(
              (e) => e.type,
              'type',
              ApiErrorType.timeout,
            ),
          ),
        );
      },
    );

    testWidgets('a hung GPS lookup cannot block completion', (tester) async {
      final backend = _Backend();
      final provider = await _load(backend);
      final gps = Completer<DeliveryLocation?>();
      await _pump(tester, provider, locationReader: () => gps.future);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();

      expect(backend.posts, 0);
      expect(find.text('Getting location...'), findsOneWidget);

      await tester.pump(const Duration(seconds: 6));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      await tester.pumpAndSettle();

      expect(backend.posts, 1);
      expect(find.text('Delivery completed. Thank you!'), findsOneWidget);
    });

    testWidgets('double-tapping Complete submits the photo once', (
      tester,
    ) async {
      final backend = _Backend(delay: const Duration(milliseconds: 300));
      final provider = await _load(backend);
      await _pump(tester, provider);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Complete Delivery'));
      await tester.tap(find.text('Complete Delivery'), warnIfMissed: false);
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();

      expect(backend.posts, 1);
      expect(provider.selected?.status, 'delivered');
    });

    testWidgets('an upload timeout clears loading and preserves the proof', (
      tester,
    ) async {
      final backend = _Backend(
        paymentMethod: 'cash_on_delivery',
        amountToCollect: 500,
        hangPost: true,
      );
      final provider = await _load(
        backend,
        uploadTimeout: const Duration(milliseconds: 100),
      );
      final store = PodDraftStore();
      await _pump(tester, provider, draftStore: store);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('codAmountField')), '500');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Complete Delivery'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150));
      await tester.pump();
      await tester.pumpAndSettle();

      expect(backend.posts, 1);
      expect(find.textContaining('Delivery not sent'), findsOneWidget);
      expect(find.text('Submitting...'), findsNothing);
      expect(
        tester
            .widget<TextField>(find.byKey(const Key('codAmountField')))
            .controller
            ?.text,
        '500',
      );
      expect(find.text('Retake Photo'), findsOneWidget);
      expect((await store.load(77))?.amount, '500');
    });

    testWidgets(
      'a lost response after a server completion resolves as success',
      (tester) async {
        final backend = _Backend(markCompletedOnPost: true, hangPost: true);
        final provider = await _load(
          backend,
          uploadTimeout: const Duration(milliseconds: 100),
        );
        final store = PodDraftStore();
        await _pump(tester, provider, draftStore: store);

        await tester.tap(find.text('Take Photo'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Complete Delivery'));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 150));
        await tester.pump();
        await tester.pumpAndSettle();

        expect(backend.posts, 1);
        expect(provider.selected?.status, 'delivered');
        expect(find.text('Delivery completed. Thank you!'), findsOneWidget);
        expect(await store.load(77), isNull);
      },
    );

    testWidgets('an oversized photo is rejected before any upload', (
      tester,
    ) async {
      final backend = _Backend();
      final provider = await _load(backend);
      final oversized = Uint8List(6 * 1024 * 1024);
      oversized[0] = 0xFF;
      oversized[1] = 0xD8;
      oversized[2] = 0xFF;
      await _pump(tester, provider, photoPicker: () async => oversized);

      await tester.tap(find.text('Take Photo'));
      await tester.pumpAndSettle();

      expect(find.textContaining('over 5 MB'), findsOneWidget);
      expect(find.text('Take Photo'), findsOneWidget);
      expect(backend.posts, 0);
    });
  });
}
