import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/add_address_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/models/address.dart';
import 'package:invoiz/services/psgc_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';

http.Response json(Object value, [int status = 200]) => http.Response(
  jsonEncode(value),
  status,
  headers: {'content-type': 'application/json'},
);

Widget app(Widget screen) => AuthServiceProvider(
  authService: AuthService(),
  child: MaterialApp(theme: AppTheme.light, home: screen),
);

void main() {
  testWidgets('new address picks region, province, city and barangay', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    Map<String, dynamic>? saved;
    final api = ApiService(
      client: MockClient((request) async {
        saved = {
          'method': request.method,
          'path': request.url.path,
          ...jsonDecode(request.body) as Map<String, dynamic>,
        };
        return json({'message': 'Address saved.'}, 201);
      }),
    );
    final psgc = PsgcService(
      client: MockClient((request) async {
        final path = request.url.path;
        if (path.endsWith('/regions/13/provinces')) return json([]);
        if (path.endsWith('/regions/13/cities')) {
          return json([
            {'code': '1339', 'name': 'City of Manila'},
          ]);
        }
        if (path.endsWith('/cities-municipalities/1339/barangays')) {
          return json([
            {'code': '133901', 'name': 'Barangay 1'},
            {'code': '133902', 'name': 'Barangay 2'},
          ]);
        }
        if (path.endsWith('/regions')) {
          return json([
            {'code': '13', 'name': 'NCR'},
            {'code': '01', 'name': 'Ilocos Region'},
          ]);
        }
        return json([]);
      }),
    );

    await tester.pumpWidget(app(AddAddressScreen(api: api, psgc: psgc)));
    await tester.pumpAndSettle();

    // No free-text province/city/barangay when the lookup works.
    expect(find.text('Select region…'), findsOneWidget);
    expect(find.text('Select province first'), findsWidgets);

    await tester.tap(find.text('Select region…'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('NCR'));
    await tester.pumpAndSettle();

    // NCR has no provinces, so the next step offers its cities.
    await tester.tap(find.text('Select city…'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('City of Manila'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Select barangay…'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Barangay 2'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(CupertinoTextField).at(0), 'Ana Cruz');
    await tester.enterText(
      find.byType(CupertinoTextField).at(1),
      '09171234567',
    );
    await tester.enterText(
      find.byType(CupertinoTextField).at(2),
      '12 Mabini Street',
    );
    await tester.pumpAndSettle();

    FocusManager.instance.primaryFocus?.unfocus();
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Save Address'),
      220,
      scrollable: find
          .descendant(
            of: find.byType(ListView).first,
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save Address'));
    await tester.pumpAndSettle();

    expect(saved, isNotNull);
    expect(saved!['region'], 'NCR');
    expect(saved!['province'], 'City of Manila');
    expect(saved!['city'], 'City of Manila');
    expect(saved!['barangay'], 'Barangay 2');
    expect(saved!['address_line'], '12 Mabini Street');
    expect(tester.takeException(), isNull);
  });

  testWidgets('editing an address restores the selected dropdowns', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    Map<String, dynamic>? saved;
    final api = ApiService(
      client: MockClient((request) async {
        saved = {
          'method': request.method,
          'path': request.url.path,
          ...jsonDecode(request.body) as Map<String, dynamic>,
        };
        return json({'message': 'Address updated.'});
      }),
    );
    final psgc = PsgcService(
      client: MockClient((request) async {
        final path = request.url.path;
        if (path.endsWith('/regions/13/provinces')) return json([]);
        if (path.endsWith('/regions/13/cities')) {
          return json([
            {'code': '1339', 'name': 'City of Manila'},
          ]);
        }
        if (path.endsWith('/cities-municipalities/1339/barangays')) {
          return json([
            {'code': '133901', 'name': 'Barangay 1'},
            {'code': '133902', 'name': 'Barangay 2'},
          ]);
        }
        if (path.endsWith('/regions')) {
          return json([
            {'code': '13', 'name': 'NCR'},
          ]);
        }
        return json([]);
      }),
    );

    await tester.pumpWidget(
      app(
        AddAddressScreen(
          api: api,
          psgc: psgc,
          address: Address.fromJson({
            'id': 2,
            'recipient_name': 'Ana Cruz',
            'phone': '09171234567',
            'address_line': '12 Mabini Street',
            'barangay': 'Barangay 2',
            'city': 'City of Manila',
            'province': 'City of Manila',
            'region': 'NCR',
            'is_default': false,
          }),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Saved values are preselected instead of reset to empty dropdowns.
    expect(find.text('NCR'), findsOneWidget);
    expect(find.text('Barangay 2'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Save Address'),
      220,
      scrollable: find
          .descendant(
            of: find.byType(ListView).first,
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save Address'));
    await tester.pumpAndSettle();

    expect(saved, isNotNull);
    expect(saved!['method'], 'PUT');
    expect(saved!['path'], endsWith('/addresses/2'));
    expect(saved!['region'], 'NCR');
    expect(saved!['province'], 'City of Manila');
    expect(saved!['city'], 'City of Manila');
    expect(saved!['barangay'], 'Barangay 2');
    expect(tester.takeException(), isNull);
  });
}
