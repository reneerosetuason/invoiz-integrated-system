import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/providers/earnings_provider.dart';
import '../lib/screens/earnings/history_screen.dart';
import '../lib/services/earnings_service.dart';

http.Response _emptyHistory() => http.Response(
  jsonEncode({'history': []}),
  200,
  headers: {'content-type': 'application/json'},
);

void main() {
  test('history sends the date range and result type to the backend', () async {
    Map<String, String>? query;
    final api = ApiClient(
      client: MockClient((request) async {
        query = request.url.queryParameters;
        return _emptyHistory();
      }),
    );
    final provider = EarningsProvider(EarningsService(api));

    await provider.loadHistory(
      search: 'MCH',
      status: 'lost',
      from: '2026-01-01',
      to: '2026-01-31',
    );

    expect(query?['search'], 'MCH');
    expect(query?['status'], 'lost');
    expect(query?['from'], '2026-01-01');
    expect(query?['to'], '2026-01-31');
  });

  testWidgets('lost and damaged result filters are available', (tester) async {
    final queries = <Map<String, String>>[];
    final api = ApiClient(
      client: MockClient((request) async {
        queries.add(request.url.queryParameters);
        return _emptyHistory();
      }),
    );
    final provider = EarningsProvider(EarningsService(api));

    await tester.pumpWidget(
      ChangeNotifierProvider<EarningsProvider>.value(
        value: provider,
        child: const MaterialApp(home: HistoryScreen()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Lost'));
    await tester.pumpAndSettle();
    expect(queries.last['status'], 'lost');

    await tester.tap(find.text('Damaged'));
    await tester.pumpAndSettle();
    expect(queries.last['status'], 'damaged');

    expect(find.text('Date'), findsOneWidget);
  });
}
