import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/home/hub_transfers_screen.dart';

Map<String, dynamic> job(int id, String reference, String status) => {
  'id': id,
  'reference': reference,
  'status': status,
  'notes': null,
  'planned_dispatch_at': null,
  'origin': {'id': 1, 'name': 'Manila Hub', 'address': 'Manila'},
  'destination': {'id': 2, 'name': 'Cebu Hub', 'address': 'Cebu'},
  'vehicle': {'type': 'truck', 'plate_number': 'TRK-1001'},
  'parcel_count': 3,
  'loaded_count': 1,
  'parcels': [],
  'events': [],
};

http.Response jsonResponse(Object body) => http.Response(
  jsonEncode(body),
  200,
  headers: {'content-type': 'application/json'},
);

void main() {
  testWidgets('filter chips refetch with the matching server-side filter', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1800, 1600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final pending = job(1, 'PEND-001', 'pending');
    final received = job(2, 'DONE-001', 'received');
    final requestedFilters = <String>[];

    final api = ApiClient(
      client: MockClient((request) async {
        expect(request.url.path, endsWith('/courier/transfers'));
        final filter = request.url.queryParameters['filter'] ?? 'all';
        requestedFilters.add(filter);
        final transfers = switch (filter) {
          'all' => [pending, received],
          'active' => [pending],
          'pending' => [pending],
          'completed' => [received],
          _ => const <Map<String, dynamic>>[],
        };
        return jsonResponse({'filter': filter, 'transfers': transfers});
      }),
    );

    await tester.pumpWidget(
      ChangeNotifierProvider<AuthProvider>(
        create: (_) => AuthProvider(api: api, storage: TokenStorage()),
        child: const MaterialApp(home: HubTransfersScreen()),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(requestedFilters, ['all']);
    expect(find.text('PEND-001'), findsOneWidget);
    expect(find.text('DONE-001'), findsOneWidget);

    await tester.tap(find.text('Completed'));
    await tester.pump();
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(requestedFilters.last, 'completed');
    expect(find.text('DONE-001'), findsOneWidget);
    expect(find.text('PEND-001'), findsNothing);

    await tester.tap(find.text('Active Trips'));
    await tester.pump();
    await tester.pump();

    expect(requestedFilters.last, 'active');
    expect(find.text('PEND-001'), findsOneWidget);
    expect(find.text('DONE-001'), findsNothing);

    await tester.tap(find.text('Pending'));
    await tester.pump();
    await tester.pump();

    expect(requestedFilters.last, 'pending');
    expect(find.text('PEND-001'), findsOneWidget);

    await tester.tap(find.text('Ready for Dispatch'));
    await tester.pump();
    await tester.pump();

    expect(requestedFilters.last, 'ready');
    expect(find.text('No transfers match this filter.'), findsOneWidget);
    expect(find.text('PEND-001'), findsNothing);
    expect(find.text('DONE-001'), findsNothing);

    await tester.tap(find.text('All Transfers'));
    await tester.pump();
    await tester.pump();

    expect(requestedFilters.last, 'all');
    expect(find.text('PEND-001'), findsOneWidget);
    expect(find.text('DONE-001'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 500));
  });
}
