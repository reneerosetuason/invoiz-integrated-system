import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/models/hub_courier.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/home/hub_courier_home_screen.dart';

const _secureChannel = MethodChannel(
  'plugins.it_nomads.com/flutter_secure_storage',
);
const _hubToken = 'hub-test-token';

Map<String, dynamic> courierPayload({bool online = true}) => {
  'id': 5,
  'name': 'Justin Barber',
  'email': 'driver@test.local',
  'phone': '09681249813',
  'avatar': null,
  'company': 'INVOIZ Logistics',
  'status': 'available',
  'is_online': online,
  'vehicle_arrangement': 'company',
  'verification_status': 'approved',
  'verification_notes': null,
  'has_assigned_truck': true,
  'vehicle': {
    'id': 5,
    'name': null,
    'type': 'light truck',
    'model': 'Isuzu Elf',
    'plate_number': 'DEV-TEST-TRK-01',
    'parcel_capacity': 100,
    'capacity_kg': 2500.0,
    'status': 'assigned',
    'ownership_type': 'company',
    'verification_status': 'approved',
    'is_assignable': true,
  },
};

Map<String, dynamic> transferPayload({
  int id = 1,
  String origin = 'Manila Hub',
  String destination = 'Cebu Hub',
  String status = 'loading',
}) => {
  'id': id,
  'reference': 'HT-00000$id',
  'status': status,
  'notes': 'Handle with care.',
  'planned_dispatch_at': '2026-11-02T08:00:00+08:00',
  'origin_arrived_at': '2026-11-02T07:30:00+08:00',
  'destination_arrived_at': null,
  'company': 'INVOIZ Logistics',
  'origin': {'id': 5, 'name': origin, 'address': '100 Transport Way'},
  'destination': {'id': 6, 'name': destination, 'address': '200 Cargo Road'},
  'vehicle': {
    'type': 'light truck',
    'plate_number': 'DEV-TEST-TRK-01',
    'parcel_capacity': 100,
    'capacity_kg': 2500.0,
  },
  'parcel_count': 3,
  'loaded_count': 1,
  'total_weight_kg': 12.5,
  'parcels': [
    {
      'tracking_number': 'TRK-20261102-0001',
      'loading_status': 'loaded',
      'receiving_status': 'pending',
      'condition': null,
      'exception_reason': null,
    },
  ],
  'events': [],
};

const _absent = Object();

Map<String, dynamic> dashboardPayload({
  bool withTransfers = true,
  Map<String, dynamic>? courier,
  Object? active = _absent,
  Object? available = _absent,
  Object? completed = _absent,
  Object? earnings = _absent,
  Object? incidents = _absent,
}) => {
  'courier': courier ?? courierPayload(),
  'active_transfer': identical(active, _absent)
      ? (withTransfers
            ? transferPayload(
                id: 1,
                origin: 'Manila Hub',
                destination: 'Cebu Hub',
              )
            : null)
      : active,
  'available_transfers': identical(available, _absent)
      ? (withTransfers
            ? [
                transferPayload(
                  id: 2,
                  origin: 'Quezon Hub',
                  destination: 'Davao Hub',
                  status: 'ready_for_dispatch',
                ),
              ]
            : const [])
      : available,
  'completed_transfers': identical(completed, _absent)
      ? (withTransfers
            ? [
                transferPayload(
                  id: 3,
                  origin: 'Cebu Hub',
                  destination: 'Manila Hub',
                  status: 'received',
                ),
              ]
            : const [])
      : completed,
  'earnings_summary': identical(earnings, _absent)
      ? {
          'total_earnings': 35.0,
          'base_earnings': 20.0,
          'vehicle_allowance': 15.0,
          'completed_trips_count': 1,
        }
      : earnings,
  'recent_incidents': identical(incidents, _absent) ? const [] : incidents,
};

http.Response jsonResponse(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

Widget harness(ApiClient api, AuthProvider auth) => MultiProvider(
  providers: [ChangeNotifierProvider<AuthProvider>.value(value: auth)],
  child: const MaterialApp(
    home: Scaffold(body: HubCourierHomeScreen(embedded: true)),
  ),
);

AuthProvider courierAuth(ApiClient api) {
  api.setToken(_hubToken);
  return AuthProvider(api: api, storage: TokenStorage())
    ..updateCourier(HubCourier.fromJson(courierPayload()));
}

Future<void> pumpDashboard(
  WidgetTester tester,
  ApiClient api,
  AuthProvider auth,
) async {
  // Tall surface so every dashboard section is laid out, not just the rows
  // above the ListView fold.
  tester.view.physicalSize = const Size(1400, 2800);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  await tester.pumpWidget(harness(api, auth));
  await tester.pump();
}

Future<void> tearDownDashboard(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(milliseconds: 500));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final secureStore = <String, String>{};

  setUp(() {
    secureStore.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_secureChannel, (call) async {
          final args =
              (call.arguments as Map?)?.cast<String, dynamic>() ??
              const <String, dynamic>{};
          final key = args['key'] as String?;
          switch (call.method) {
            case 'read':
              return secureStore[key];
            case 'write':
              secureStore[key!] = args['value'] as String;
              return null;
            case 'delete':
              secureStore.remove(key);
              return null;
            case 'deleteAll':
              secureStore.clear();
              return null;
            case 'readAll':
              return Map<String, String>.from(secureStore);
            case 'containsKey':
              return secureStore.containsKey(key);
          }
          return null;
        });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_secureChannel, null);
  });

  testWidgets('loads the hub courier dashboard with the hub token', (
    tester,
  ) async {
    Uri? requested;
    String? authorization;
    final api = ApiClient(
      client: MockClient((request) async {
        requested = request.url;
        authorization = request.headers['Authorization'];
        return jsonResponse(dashboardPayload());
      }),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(tester.takeException(), isNull);
    expect(requested?.path, endsWith('/courier/dashboard'));
    expect(authorization, 'Bearer $_hubToken');

    expect(find.text('Justin Barber'), findsOneWidget);
    expect(find.text('Vehicle: DEV-TEST-TRK-01'), findsOneWidget);
    expect(find.text('ACTIVE HUB TRANSFER'), findsOneWidget);
    expect(find.text('Manila Hub  →  Cebu Hub'), findsOneWidget);
    expect(find.text('Quezon Hub  →  Davao Hub'), findsOneWidget);
    expect(find.text('Cebu Hub  →  Manila Hub'), findsOneWidget);
    expect(find.text('HUB-TO-HUB TRANSPORT'), findsNWidgets(2));
    expect(find.text('₱35.00'), findsOneWidget);
    expect(find.text('1 trips'), findsOneWidget);
    expect(find.text('Vehicle Allowance'), findsNothing);
    expect(find.textContaining('Includes separate vehicle-use allowance'), findsNothing);
    expect(find.text('View remittances'), findsOneWidget);
    expect(find.text('Assigned Transfers'), findsOneWidget);
    expect(find.text('Completed Transfers'), findsOneWidget);

    await tearDownDashboard(tester);
  });

  testWidgets('shows explicit empty states when no transfers are assigned', (
    tester,
  ) async {
    final api = ApiClient(
      client: MockClient(
        (_) async => jsonResponse(
          dashboardPayload(
            withTransfers: false,
            earnings: {
              'total_earnings': 0,
              'base_earnings': 0,
              'vehicle_allowance': 0,
              'completed_trips_count': 0,
            },
          ),
        ),
      ),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(tester.takeException(), isNull);
    expect(find.text('Justin Barber'), findsOneWidget);
    expect(
      find.text(
        'No assigned transfer jobs. Your dispatcher will assign work here.',
      ),
      findsOneWidget,
    );
    expect(find.text('No completed transfers yet.'), findsOneWidget);
    expect(find.text('ACTIVE HUB TRANSFER'), findsNothing);

    await tearDownDashboard(tester);
  });

  testWidgets('shows a useful error state when the dashboard request fails', (
    tester,
  ) async {
    final api = ApiClient(
      client: MockClient(
        (_) async => jsonResponse({
          'message': 'Hub transport driver account is not active.',
        }, 403),
      ),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(tester.takeException(), isNull);
    expect(find.text('Could not load transfer jobs.'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    expect(
      find.textContaining('Hub transport driver account is not active.'),
      findsOneWidget,
    );

    await tearDownDashboard(tester);
  });

  testWidgets('shows the error state instead of blanking on a drifted payload', (
    tester,
  ) async {
    final api = ApiClient(
      client: MockClient(
        (_) async => jsonResponse({
          'courier': ['unexpected', 'shape'],
          'active_transfer': 'not-a-map',
          'available_transfers': {'id': 1},
          'completed_transfers': null,
          'earnings_summary': [],
          'recent_incidents': ['not-a-map'],
        }),
      ),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(tester.takeException(), isNull);
    expect(find.text('Could not load transfer jobs.'), findsOneWidget);
    expect(find.textContaining('driver profile'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);

    await tearDownDashboard(tester);
  });

  testWidgets('renders tolerated types without throwing', (tester) async {
    final api = ApiClient(
      client: MockClient(
        (_) async => jsonResponse(
          dashboardPayload(
            courier: {
              'id': '5',
              'name': 'Justin Barber',
              'company': null,
              'email': null,
              'is_online': 0,
              'vehicle_arrangement': null,
              'vehicle': [],
            },
            active: null,
            available: [
              {
                'id': '2',
                'reference': 9001,
                'status': 'loading',
                'origin': {'id': '5', 'name': 'Manila Hub'},
                'destination': {'id': '6', 'name': 'Cebu Hub'},
                'parcel_count': '3',
                'loaded_count': '1',
                'parcels': 'not-a-list',
                'planned_dispatch_at': null,
                'notes': null,
              },
              'not-a-transfer',
            ],
            completed: [],
            earnings: {
              'total_earnings': '35.50',
              'base_earnings': '20.25',
              'vehicle_allowance': '15.25',
              'completed_trips_count': '1',
            },
            incidents: [],
          ),
        ),
      ),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(tester.takeException(), isNull);
    expect(find.text('Justin Barber'), findsOneWidget);
    expect(find.text('Vehicle: Not Assigned'), findsOneWidget);
    expect(find.text('Manila Hub  →  Cebu Hub'), findsOneWidget);
    expect(find.text('3 parcels · 1 loaded · loading'), findsOneWidget);
    expect(find.text('₱35.50'), findsOneWidget);
    expect(find.text('1 trips'), findsOneWidget);
    expect(find.text('No completed transfers yet.'), findsOneWidget);

    await tearDownDashboard(tester);
  });

  testWidgets('shows a loading state while the dashboard request is pending', (
    tester,
  ) async {
    final completer = Completer<http.Response>();
    final api = ApiClient(client: MockClient((_) => completer.future));
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.text('Loading transfer jobs…'), findsOneWidget);

    completer.complete(jsonResponse(dashboardPayload()));
    await tester.pump();
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(find.text('Justin Barber'), findsOneWidget);

    await tearDownDashboard(tester);
  });

  testWidgets('status switch persists through the hub courier endpoint', (
    tester,
  ) async {
    var online = false;
    var dashboardRequests = 0;
    final statusPatches = <String>[];
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/courier/status')) {
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          statusPatches.add(body['status'] as String);
          online = body['status'] == 'online';
          return jsonResponse({
            'status': body['status'],
            'courier': courierPayload(online: online),
          });
        }
        dashboardRequests++;
        return jsonResponse(
          dashboardPayload(
            withTransfers: false,
            courier: courierPayload(online: online),
          ),
        );
      }),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    expect(tester.takeException(), isNull);
    expect(find.text('Offline'), findsOneWidget);
    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
      isFalse,
    );
    expect(dashboardRequests, 1);

    await tester.tap(find.byType(SwitchListTile));
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(tester.takeException(), isNull);
    expect(statusPatches, ['online']);
    expect(dashboardRequests, 2);
    expect(find.text('Online for assigned transfers'), findsOneWidget);
    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
      isTrue,
    );

    await tester.tap(find.byType(SwitchListTile));
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(tester.takeException(), isNull);
    expect(statusPatches, ['online', 'offline']);
    expect(dashboardRequests, 3);
    expect(find.text('Offline'), findsOneWidget);
    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
      isFalse,
    );

    await tearDownDashboard(tester);
  });

  testWidgets('failed status change keeps the previous state and reports it', (
    tester,
  ) async {
    var dashboardRequests = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/courier/status')) {
          return jsonResponse({
            'message': 'Your assigned vehicle is unavailable or missing capacity.',
          }, 422);
        }
        dashboardRequests++;
        return jsonResponse(
          dashboardPayload(
            withTransfers: false,
            courier: courierPayload(online: false),
          ),
        );
      }),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    await tester.tap(find.byType(SwitchListTile));
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(tester.takeException(), isNull);
    expect(
      find.textContaining('Could not change status:'),
      findsOneWidget,
    );
    expect(
      find.textContaining('assigned vehicle is unavailable'),
      findsOneWidget,
    );
    expect(find.text('Offline'), findsOneWidget);
    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
      isFalse,
    );
    // A failed update must not trigger a dashboard reload.
    expect(dashboardRequests, 1);

    await tearDownDashboard(tester);
  });

  testWidgets('status switch ignores repeated taps while saving', (
    tester,
  ) async {
    var online = false;
    var statusRequests = 0;
    final statusCompleter = Completer<http.Response>();
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/courier/status')) {
          statusRequests++;
          return statusCompleter.future;
        }
        return jsonResponse(
          dashboardPayload(
            withTransfers: false,
            courier: courierPayload(online: online),
          ),
        );
      }),
    );
    final auth = courierAuth(api);

    await pumpDashboard(tester, api, auth);

    await tester.tap(find.byType(SwitchListTile));
    await tester.pump();

    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).onChanged,
      isNull,
    );
    await tester.tap(find.byType(SwitchListTile), warnIfMissed: false);
    await tester.pump();
    expect(statusRequests, 1);

    online = true;
    statusCompleter.complete(
      jsonResponse({
        'status': 'online',
        'courier': courierPayload(online: true),
      }),
    );
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(tester.takeException(), isNull);
    expect(statusRequests, 1);
    expect(
      tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
      isTrue,
    );

    await tearDownDashboard(tester);
  });
}
