import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/services/address_service.dart';
import 'package:invoize_rider/widgets/address_fields.dart';

const _region = 'Ilocos Region';
const _ncr = 'National Capital Region (NCR)';
const _barangay = 'Barangay San Francisco (Poblacion)';

http.Response _json(Object data, [int status = 200]) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);

http.Response _directory(http.Request request) => switch (request.url.path) {
  '/api/psgc/regions' => _json([
    {'code': '010000000', 'name': _region},
    {'code': '130000000', 'name': _ncr},
  ]),
  '/api/psgc/regions/010000000/provinces' => _json([
    {'code': '012800000', 'name': 'Ilocos Norte'},
  ]),
  '/api/psgc/provinces/012800000/cities-municipalities' => _json([
    {'code': '012812000', 'name': 'City of Laoag (Capital)'},
  ]),
  '/api/psgc/cities-municipalities/012812000/barangays' => _json([
    {'code': '012812001', 'name': _barangay},
  ]),
  '/api/psgc/regions/130000000/provinces' => _json([]),
  '/api/psgc/regions/130000000/cities' => _json([
    {'code': '137401000', 'name': 'City of Mandaluyong'},
  ]),
  '/api/psgc/cities-municipalities/137401000/barangays' => _json([
    {'code': '137401001', 'name': 'Addition Hills'},
  ]),
  _ => _json({'message': 'Unknown lookup'}, 404),
};

Finder _field(String label) => find.byWidgetPredicate(
  (widget) =>
      widget is DropdownButtonFormField<String> &&
      widget.decoration.labelText == label,
);

Future<void> _pick(WidgetTester tester, String label, String value) async {
  await tester.ensureVisible(_field(label));
  await tester.pumpAndSettle();
  await tester.tap(_field(label));
  await tester.pumpAndSettle();
  await tester.tap(find.text(value).last);
  await tester.pumpAndSettle();
}

Future<void> _complete(WidgetTester tester) async {
  await _pick(tester, 'Region *', _region);
  await _pick(tester, 'Province *', 'Ilocos Norte');
  await _pick(tester, 'City / Municipality *', 'City of Laoag (Capital)');
  await _pick(tester, 'Barangay *', _barangay);
}

Widget _form(
  ApiClient api,
  ValueChanged<AddressSelection> onChanged, {
  AddressSelection? initial,
}) => MaterialApp(
  theme: AppTheme.light(),
  home: Scaffold(
    body: SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: AddressFields(
        apiClient: api,
        onChanged: onChanged,
        initialSelection: initial,
      ),
    ),
  ),
);

void main() {
  test(
    'uses the shared backend, keeps leading zeroes and caches lookups',
    () async {
      final paths = <String>[];
      final api = ApiClient(
        client: MockClient((request) async {
          paths.add(request.url.path);
          expect(request.headers.containsKey('Authorization'), isFalse);
          return _directory(request);
        }),
      )..setToken('rider-token');
      final service = AddressService(api);
      final regions = await service.regions();
      expect(regions.first.code, '010000000');
      await service.provinces(regions.first.code);
      await service.provinces(regions.first.code);
      expect(paths, [
        '/api/psgc/regions',
        '/api/psgc/regions/010000000/provinces',
      ]);
    },
  );

  test('shared lookup preserves a hosted deployment subdirectory', () async {
    dotenv.testLoad(
      fileInput: 'API_BASE_URL=https://example.test/invoiz/api/logistics/',
    );
    addTearDown(dotenv.clean);
    final api = ApiClient(
      client: MockClient((request) async {
        expect(
          request.url.toString(),
          'https://example.test/invoiz/api/psgc/regions',
        );
        return _json([]);
      }),
    );
    await api.getShared('psgc/regions');
  });

  testWidgets('selects a full address and clears children on region change', (
    tester,
  ) async {
    AddressSelection? selected;
    await tester.pumpWidget(
      _form(
        ApiClient(client: MockClient((r) async => _directory(r))),
        (value) => selected = value,
      ),
    );
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'House No.'), '12');
    await tester.enterText(
      find.widgetWithText(TextField, 'Street'),
      'Test Street',
    );
    await _complete(tester);
    expect(selected!.complete, isTrue);
    expect(
      selected!.composed,
      '12, Test Street, $_barangay, City of Laoag (Capital), Ilocos Norte, $_region',
    );
    expect(selected!.province!.code, '012800000');

    await _pick(tester, 'Region *', _ncr);
    expect(selected!.complete, isFalse);
    expect(selected!.province, isNull);
    expect(selected!.municipality, isNull);
    expect(selected!.barangay, isNull);
    expect(
      tester
          .widget<DropdownButtonFormField<String>>(_field('Barangay *'))
          .initialValue,
      isNull,
    );
    await _pick(tester, 'City / Municipality *', 'City of Mandaluyong');
    await _pick(tester, 'Barangay *', 'Addition Hills');
    expect(selected!.complete, isTrue);
    expect(selected!.provinceNotApplicable, isTrue);
    expect(selected!.regionCode, '130000000');
    expect(selected!.province, isNull);
    expect(selected!.composed, contains('City of Mandaluyong, $_ncr'));
  });

  testWidgets('restores saved dropdowns and street after leaving a form step', (
    tester,
  ) async {
    AddressSelection? selected;
    final api = ApiClient(client: MockClient((r) async => _directory(r)));
    await tester.pumpWidget(_form(api, (value) => selected = value));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Street'),
      'Saved Street',
    );
    await _complete(tester);
    final saved = selected!;
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(
      _form(api, (value) => selected = value, initial: saved),
    );
    await tester.pumpAndSettle();
    expect(selected!.complete, isTrue);
    expect(selected!.composed, saved.composed);
    expect(
      tester
          .widget<TextField>(find.widgetWithText(TextField, 'Street'))
          .controller!
          .text,
      'Saved Street',
    );
    expect(
      tester
          .widget<DropdownButtonFormField<String>>(_field('Province *'))
          .initialValue,
      saved.province!.code,
    );
    expect(
      tester
          .widget<DropdownButtonFormField<String>>(_field('Barangay *'))
          .initialValue,
      saved.barangay!.code,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('lookup failures can retry without being mistaken for NCR', (
    tester,
  ) async {
    var attempts = 0;
    AddressSelection? selected;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/provinces') && ++attempts == 1) {
          return _json({'message': 'Unavailable'}, 503);
        }
        return _directory(request);
      }),
    );
    await tester.pumpWidget(_form(api, (value) => selected = value));
    await tester.pumpAndSettle();
    await _pick(tester, 'Region *', _region);
    expect(find.textContaining('Unable to load provinces.'), findsOneWidget);
    expect(selected!.provinceNotApplicable, isFalse);
    expect(selected!.complete, isFalse);
    await tester.tap(find.text('Retry address lookup'));
    await tester.pumpAndSettle();
    expect(attempts, 2);
    expect(find.text('Retry address lookup'), findsNothing);
    await _pick(tester, 'Province *', 'Ilocos Norte');
    await _pick(tester, 'City / Municipality *', 'City of Laoag (Capital)');
    await _pick(tester, 'Barangay *', _barangay);
    expect(selected!.complete, isTrue);
    expect(tester.takeException(), isNull);
  });

  for (final failOldRequest in [false, true]) {
    testWidgets(
      'ignores a stale province ${failOldRequest ? 'failure' : 'response'} after region change',
      (tester) async {
        final pending = Completer<http.Response>();
        AddressSelection? selected;
        final api = ApiClient(
          client: MockClient((request) async {
            if (request.url.path == '/api/psgc/regions/010000000/provinces') {
              return pending.future;
            }
            return _directory(request);
          }),
        );
        await tester.pumpWidget(_form(api, (value) => selected = value));
        await tester.pumpAndSettle();
        tester
            .widget<DropdownButtonFormField<String>>(_field('Region *'))
            .onChanged!('010000000');
        await tester.pump();
        tester
            .widget<DropdownButtonFormField<String>>(_field('Region *'))
            .onChanged!('130000000');
        await tester.pumpAndSettle();
        pending.complete(
          failOldRequest
              ? _json({}, 503)
              : _json([
                  {'code': '012800000', 'name': 'Ilocos Norte'},
                ]),
        );
        await tester.pumpAndSettle();
        expect(selected!.regionCode, '130000000');
        expect(selected!.provinceNotApplicable, isTrue);
        expect(find.text('Retry address lookup'), findsNothing);
        await _pick(tester, 'City / Municipality *', 'City of Mandaluyong');
        await _pick(tester, 'Barangay *', 'Addition Hills');
        expect(selected!.complete, isTrue);
        expect(tester.takeException(), isNull);
      },
    );
  }

  for (final width in [320.0, 360.0]) {
    testWidgets('long address labels remain usable at ${width.toInt()}px', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        _form(
          ApiClient(client: MockClient((r) async => _directory(r))),
          (_) {},
        ),
      );
      await tester.pumpAndSettle();
      await _complete(tester);
      await _pick(tester, 'Region *', _ncr);
      await _pick(tester, 'City / Municipality *', 'City of Mandaluyong');
      await _pick(tester, 'Barangay *', 'Addition Hills');
      expect(tester.takeException(), isNull);
    });
  }
}
