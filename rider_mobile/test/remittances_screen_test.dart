import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/theme/app_theme.dart';
import '../lib/providers/remittance_provider.dart';
import '../lib/screens/remittances/remittances_screen.dart';
import '../lib/services/remittance_service.dart';

void main() {
  testWidgets('shows COD remittances and marks one as sent', (tester) async {
    var sent = false;

    final client = MockClient((request) async {
      if (request.method == 'POST') {
        sent = true;
        return http.Response(
          jsonEncode({
            'remittance': {
              'id': 9,
              'order_id': 44,
              'amount': 500.5,
              'status': 'sent',
              'reference': 'GC-1',
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }

      return http.Response(
        jsonEncode({
          'remittances': [
            {
              'id': 9,
              'order_id': 44,
              'amount': 500.5,
              'status': sent ? 'sent' : 'pending',
              'reference': sent ? 'GC-1' : null,
            },
          ],
          'summary': {
            'total_due': sent ? 0 : 500.5,
            'total_confirmed': 0,
            'pending_count': sent ? 0 : 1,
            'sent_count': sent ? 1 : 0,
            'confirmed_count': 0,
          },
        }),
        200,
        headers: {'content-type': 'application/json'},
      );
    });

    final provider = RemittanceProvider(
      RemittanceService(ApiClient(client: client)),
    );

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: MaterialApp(
          theme: AppTheme.light(),
          home: const RemittancesScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('To Remit'), findsOneWidget);
    expect(find.text('Mark as Sent'), findsOneWidget);

    await tester.tap(find.text('Mark as Sent'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();

    expect(find.text('Remittance marked as sent.'), findsOneWidget);
    expect(find.text('Waiting for logistics to confirm receipt.'),
        findsOneWidget);
  });
}
