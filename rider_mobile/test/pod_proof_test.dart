import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/proof_of_delivery_screen.dart';
import '../lib/services/delivery_service.dart';
import '../lib/services/pod_draft_store.dart';

Uint8List _photo() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhf'
  'DwAChwGA60e6kgAAAABJRU5ErkJggg==',
);

Map<String, dynamic> _delivery({String status = 'out_for_delivery'}) => {
  'id': 77,
  'tracking_number': 'TRK-20261029-POD1',
  'order_id': 9001,
  'status': status,
  'status_label': status == 'delivered' ? 'Delivered' : 'Out for Delivery',
  'shop': {'name': 'Shop'},
  'customer': {'name': 'Cust', 'address': '1 Way'},
  'items': const [],
  'subtotal': 450,
  'delivery_fee': 50,
  'total': 500,
  'payment_method': 'cash_on_delivery',
  'amount_to_collect': 500,
  'otp_required': true,
  'pickup_pin_required': false,
};

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

class _Backend {
  _Backend() {
    client = MockClient((request) async {
      final path = request.url.path;
      if (request.method == 'GET' && path.endsWith('/rider/deliveries/77')) {
        return _json({'delivery': _delivery()});
      }
      if (request.method == 'POST' && path.endsWith('/complete')) {
        posts++;
        return _json({
          'message': 'Delivery completed successfully.',
          'delivery': _delivery(status: 'delivered'),
        });
      }
      return _json({'message': 'Not found.'}, 404);
    });
  }

  late final MockClient client;
  int posts = 0;
}

void _disableLocation() {
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(
        const MethodChannel('flutter.baseflow.com/geolocator'),
        (call) async => call.method == 'isLocationServiceEnabled' ? false : null,
      );
}

Future<DeliveryProvider> _load(_Backend backend) async {
  final provider = DeliveryProvider(DeliveryService(ApiClient(client: backend.client)));
  await provider.loadDetail(77);
  return provider;
}

Future<void> _pump(
  WidgetTester tester,
  DeliveryProvider provider, {
  PodDraftStore? store,
  Future<Uint8List?> Function()? photoPicker,
}) async {
  await tester.pumpWidget(
    ChangeNotifierProvider<DeliveryProvider>.value(
      value: provider,
      child: MaterialApp(
        home: ProofOfDeliveryScreen(
          deliveryId: 77,
          draftStore: store,
          photoPicker: photoPicker ?? () async => _photo(),
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

  testWidgets('completion is blocked without a live proof photo', (
    tester,
  ) async {
    final backend = _Backend();
    final provider = await _load(backend);
    await _pump(tester, provider, photoPicker: () async => null);

    await tester.enterText(find.byKey(const Key('deliveryOtpField')), '123456');
    await tester.enterText(find.byKey(const Key('codAmountField')), '500');
    await tester.tap(find.text('Complete Delivery'));
    await tester.pump();

    expect(
      find.textContaining('Take a live delivery photo'),
      findsOneWidget,
    );
    expect(backend.posts, 0);
    expect(provider.selected?.status, 'out_for_delivery');
  });

  testWidgets('unsent OTP, amount and name survive an app restart', (
    tester,
  ) async {
    final backend = _Backend();
    final provider = await _load(backend);
    await _pump(tester, provider);

    await tester.enterText(find.byKey(const Key('deliveryOtpField')), '123456');
    await tester.enterText(find.byKey(const Key('codAmountField')), '500');
    await tester.enterText(
      find.byKey(const Key('signatureNameField')),
      'Juan Dela Cruz',
    );
    await tester.pumpAndSettle();

    // Simulate the app being closed and reopened on the same phone.
    await tester.pumpWidget(const SizedBox.shrink());
    final restarted = await _load(backend);
    await _pump(tester, restarted);
    await tester.pumpAndSettle();

    expect(find.textContaining('Restored your unsent proof'), findsOneWidget);
    expect(
      tester
          .widget<TextField>(find.byKey(const Key('deliveryOtpField')))
          .controller
          ?.text,
      '123456',
    );
    expect(
      tester
          .widget<TextField>(find.byKey(const Key('codAmountField')))
          .controller
          ?.text,
      '500',
    );
    expect(
      tester
          .widget<TextField>(find.byKey(const Key('signatureNameField')))
          .controller
          ?.text,
      'Juan Dela Cruz',
    );
  });

  test('a stored camera photo path is restored with the draft', () async {
    final dir = await Directory.systemTemp.createTemp('pod-draft');
    final file = File('${dir.path}${Platform.pathSeparator}pod.jpg');
    await file.writeAsBytes(_photo());

    final store = PodDraftStore();
    await store.save(
      77,
      PodDraft(photoPath: file.path, otp: '123456', amount: '500'),
    );

    final draft = await store.load(77);
    expect(draft?.photoPath, file.path);
    expect(File(draft!.photoPath!).existsSync(), isTrue);

    await store.clear(77);
    expect(await store.load(77), isNull);
    await dir.delete(recursive: true);
  });

  testWidgets('a successful completion clears the saved draft', (
    tester,
  ) async {
    final backend = _Backend();
    final provider = await _load(backend);
    final store = PodDraftStore();
    await _pump(tester, provider, store: store);

    await tester.tap(find.text('Take Photo'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('deliveryOtpField')), '123456');
    await tester.enterText(find.byKey(const Key('codAmountField')), '500');
    await tester.pumpAndSettle();

    expect(await store.load(77), isNotNull);

    await tester.tap(find.text('Complete Delivery'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(backend.posts, 1);
    expect(provider.selected?.status, 'delivered');
    expect(await store.load(77), isNull);
  });
}
