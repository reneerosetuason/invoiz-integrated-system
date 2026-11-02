import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/home/hub_courier_home_screen.dart';

Map<String, dynamic> parcel(String tracking, String stage, String label) => {
  'id': tracking.hashCode,
  'tracking_number': tracking,
  'weight_kg': 5.0,
  'loading_status': stage == 'added' ? 'pending' : 'loaded',
  'receiving_status': stage == 'received' ? 'received' : 'pending',
  'condition': null,
  'exception_reason': null,
  'journey_status': stage,
  'journey_label': label,
};

Map<String, dynamic> transferPayload({
  required String status,
  required String statusLabel,
  int receivedCount = 0,
  required List<Map<String, dynamic>> parcels,
}) => {
  'id': 11,
  'reference': 'HT-20261102-MANIFEST',
  'status': status,
  'status_label': statusLabel,
  'notes': null,
  'planned_dispatch_at': null,
  'dispatched_at': status == 'in_transit' ? '2026-11-02T09:00:00+08:00' : null,
  'destination_arrived_at': null,
  'received_at': null,
  'origin': {
    'id': 1,
    'name': 'Calamba Hub',
    'address': 'Calamba, Laguna',
  },
  'destination': {'id': 2, 'name': 'Pila Hub', 'address': 'Pila, Laguna'},
  'vehicle': {
    'type': 'light truck',
    'model': 'Isuzu Elf',
    'plate_number': 'DEV-TEST-TRK-01',
    'parcel_capacity': 100,
    'capacity_kg': 2500.0,
  },
  'parcel_count': parcels.length,
  'loaded_count': parcels.where((p) => p['loading_status'] == 'loaded').length,
  'received_count': receivedCount,
  'missing_count': 0,
  'total_weight_kg': 15.0,
  'parcels': parcels,
  'events': [],
};

Future<void> pumpDetail(
  WidgetTester tester,
  Map<String, dynamic> payload,
) async {
  tester.view.physicalSize = const Size(1200, 2600);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final api = ApiClient(
    client: MockClient((request) async {
      expect(request.url.path, endsWith('/courier/transfers/11'));
      return http.Response(
        jsonEncode({'transfer': payload}),
        200,
        headers: {'content-type': 'application/json'},
      );
    }),
  );

  await tester.pumpWidget(
    ChangeNotifierProvider<AuthProvider>(
      create: (_) => AuthProvider(api: api, storage: TokenStorage()),
      child: const MaterialApp(
        home: HubTransferDetailScreen(transferId: 11),
      ),
    ),
  );
  await tester.pump();
  await tester.pump();

  expect(tester.takeException(), isNull);
}

void main() {
  testWidgets('one manifest lists every parcel with its own loaded stage', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      transferPayload(
        status: 'loading',
        statusLabel: 'Loading',
        parcels: [
          parcel('TRK-A-0001', 'loaded', 'Loaded / verified'),
          parcel('TRK-B-0002', 'loaded', 'Loaded / verified'),
          parcel('TRK-C-0003', 'added', 'Added to manifest'),
        ],
      ),
    );

    expect(find.text('Status: Loading'), findsOneWidget);
    expect(
      find.text('Vehicle: light truck · DEV-TEST-TRK-01'),
      findsOneWidget,
    );
    expect(
      find.text('Parcels: 3 total · 2 loaded · 0 received'),
      findsOneWidget,
    );
    expect(find.text('Combined weight: 15.00 kg'), findsOneWidget);
    expect(find.text('Manifest · 3 parcels · 15.00 kg'), findsOneWidget);

    expect(find.text('TRK-A-0001'), findsOneWidget);
    expect(find.text('TRK-B-0002'), findsOneWidget);
    expect(find.text('TRK-C-0003'), findsOneWidget);
    expect(find.text('Loaded / verified · 5.00 kg'), findsNWidgets(2));
    expect(find.text('Added to manifest · 5.00 kg'), findsOneWidget);

    // The transfer is rendered once as a manifest, not once per parcel.
    expect(find.text('HT-20261102-MANIFEST'), findsWidgets);
    expect(find.text('Calamba Hub  →  Pila Hub'), findsOneWidget);
  });

  testWidgets('dispatched transfer shows the shared journey on every parcel', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      transferPayload(
        status: 'in_transit',
        statusLabel: 'In Transit',
        parcels: [
          parcel('TRK-A-0001', 'dispatched', 'Dispatched with transfer'),
          parcel('TRK-B-0002', 'dispatched', 'Dispatched with transfer'),
          parcel('TRK-C-0003', 'dispatched', 'Dispatched with transfer'),
        ],
      ),
    );

    expect(find.text('Status: In Transit'), findsOneWidget);
    expect(
      find.text('Dispatched with transfer · 5.00 kg'),
      findsNWidgets(3),
    );
    expect(find.text('Parcels: 3 total · 3 loaded · 0 received'), findsOneWidget);
  });

  testWidgets('arrival does not mark parcels received until verified', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      transferPayload(
        status: 'arrived',
        statusLabel: 'Arrived',
        receivedCount: 1,
        parcels: [
          parcel('TRK-A-0001', 'received', 'Received and verified'),
          parcel('TRK-B-0002', 'arrived', 'Arrived with transfer'),
          parcel('TRK-C-0003', 'arrived', 'Arrived with transfer'),
        ],
      ),
    );

    expect(find.text('Status: Arrived'), findsOneWidget);
    expect(
      find.text('Parcels: 3 total · 3 loaded · 1 received'),
      findsOneWidget,
    );
    expect(find.text('Received and verified · 5.00 kg'), findsOneWidget);
    expect(find.text('Arrived with transfer · 5.00 kg'), findsNWidgets(2));
    expect(
      find.textContaining('truck arrival does not mark a parcel received'),
      findsOneWidget,
    );
  });
}
