import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/models/hub_courier.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/providers/delivery_provider.dart';
import 'package:invoize_rider/providers/earnings_provider.dart';
import 'package:invoize_rider/providers/notification_provider.dart';
import 'package:invoize_rider/providers/rider_provider.dart';
import 'package:invoize_rider/screens/home/home_shell.dart';
import 'package:invoize_rider/services/delivery_service.dart';
import 'package:invoize_rider/services/earnings_service.dart';
import 'package:invoize_rider/services/notification_service.dart';
import 'package:invoize_rider/services/rider_service.dart';

const _dashboard = {
  'courier': {
    'id': 5,
    'name': 'Justin Barber',
    'email': 'lanime05172020@gmail.com',
    'phone': '09681249813',
    'avatar': null,
    'company': 'INVOIZ Logistics',
    'status': 'available',
    'is_online': false,
    'vehicle_arrangement': 'company',
    'verification_status': 'approved',
    'verification_notes': null,
    'has_assigned_truck': true,
    'vehicle': {
      'id': 5,
      'name': null,
      'type': 'light truck',
      'model': 'Test Isuzu Elf',
      'plate_number': 'DEV-TEST-TRK-01',
      'parcel_capacity': 100,
      'capacity_kg': 2500,
      'status': 'assigned',
      'ownership_type': 'company',
      'verification_status': 'approved',
      'is_assignable': true,
    },
  },
  'active_transfer': {
    'id': 6,
    'reference': 'HT-20261102-EA2L6H',
    'status': 'ready_for_dispatch',
    'notes': 'Automated test transfer from Calamba to Pila',
    'planned_dispatch_at': '2026-11-02T11:54:26+00:00',
    'origin_arrived_at': '2026-11-02T10:54:26+00:00',
    'destination_arrived_at': null,
    'company': 'INVOIZ Logistics',
    'origin': {'id': 5, 'name': 'calamba hub', 'address': 'calamba, laguna'},
    'destination': {'id': 6, 'name': 'pila hub', 'address': 'pila'},
    'vehicle': {
      'type': 'light truck',
      'plate_number': 'DEV-TEST-TRK-01',
      'parcel_capacity': 100,
      'capacity_kg': 2500,
    },
    'parcel_count': 1,
    'loaded_count': 1,
    'total_weight_kg': 2.5,
    'parcels': [],
    'events': [],
  },
  'available_transfers': [],
  'completed_transfers': [],
  'earnings_summary': {
    'total_earnings': 20,
    'base_earnings': 20,
    'vehicle_allowance': 0,
    'completed_trips_count': 1,
  },
  'recent_incidents': [],
};

void main() {
  // Renders the populated dashboard with the real app theme: the theme's
  // full-width button minimums must not break the Home layout.
  testWidgets('Home tab renders the dashboard under the app theme', (
    tester,
  ) async {
    // Wide surface: the Ahem test font is much wider than real fonts, and this
    // guards a width-independent layout crash, not text fitting.
    tester.view.physicalSize = const Size(2400, 2400);
    tester.view.devicePixelRatio = 2.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = ApiClient(
      client: MockClient((request) async {
        final body = request.url.path.contains('/courier/dashboard')
            ? _dashboard
            : request.url.path.contains('/courier/transfers')
            ? {'transfers': []}
            : request.url.path.contains('/courier/notifications')
            ? {'notifications': []}
            : {};
        return http.Response(
          jsonEncode(body),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    final auth = AuthProvider(api: api, storage: TokenStorage())
      ..updateCourier(
        HubCourier.fromJson(
          Map<String, dynamic>.from(_dashboard['courier'] as Map),
        ),
      );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<AuthProvider>.value(value: auth),
          ChangeNotifierProvider(create: (_) => RiderProvider(RiderService(api))),
          ChangeNotifierProvider(
            create: (_) => DeliveryProvider(DeliveryService(api)),
          ),
          ChangeNotifierProvider(
            create: (_) => NotificationProvider(NotificationService(api)),
          ),
          ChangeNotifierProvider(
            create: (_) => EarningsProvider(EarningsService(api)),
          ),
        ],
        child: MaterialApp(theme: AppTheme.light(), home: const HomeShell()),
      ),
    );
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(tester.takeException(), isNull);
    expect(find.text('Justin Barber'), findsOneWidget);
    expect(find.text('Company Fleet'), findsOneWidget);
    expect(find.text('Vehicle: DEV-TEST-TRK-01'), findsOneWidget);
    expect(find.text('ACTIVE HUB TRANSFER'), findsOneWidget);
    expect(find.text('Accident or Damage?'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Report'), findsOneWidget);

    await tester.tap(find.byTooltip('Transfers'));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.tap(find.byTooltip('Dashboard'));
    await tester.pump(const Duration(milliseconds: 100));

    expect(tester.takeException(), isNull);
    expect(find.text('Justin Barber'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 500));
  });
}
