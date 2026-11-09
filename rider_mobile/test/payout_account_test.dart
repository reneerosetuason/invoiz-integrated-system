import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/theme/app_theme.dart';
import 'package:invoize_rider/widgets/payout_account_button.dart';

void main() {
  Future<void> show(
    WidgetTester tester,
    ApiClient api, {
    double width = 390,
    double scale = 1,
    double keyboard = 0,
  }) async {
    tester.view.physicalSize = Size(width, 800);
    tester.view.devicePixelRatio = 1;
    tester.view.viewInsets = FakeViewPadding(bottom: keyboard);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetViewInsets);
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: Scaffold(
          appBar: AppBar(
            title: const Text('Remittances'),
            actions: [PayoutAccountButton(api: api)],
          ),
        ),
      ),
    );
    await tester.tap(find.text('Bank details'));
    await tester.pumpAndSettle();
  }

  Map<String, dynamic> row({
    String key = 'user-1',
    String label = 'Test worker',
    Map<String, String>? details,
  }) => {'key': key, 'label': label, 'details': details};

  testWidgets(
    'save once and reopen to edit latest details with auth and leading zeros',
    (tester) async {
      Map<String, String>? saved;
      var posts = 0;
      var gets = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          expect(request.url.path, '/api/remittances/payout-account');
          expect(request.headers['Authorization'], 'Bearer unit-test-token');
          if (request.method == 'POST') {
            posts++;
            final data = jsonDecode(request.body) as Map;
            expect(data['target'], 'user-1');
            saved = {
              for (final field in ['provider', 'holder', 'account'])
                field: data[field] as String,
            };
            return http.Response('{}', 200);
          }
          gets++;
          return http.Response(
            jsonEncode({
              'accounts': [row(details: saved)],
            }),
            200,
          );
        }),
      );
      api.setToken('unit-test-token');
      await show(tester, api);
      final fields = find.byType(TextFormField);
      await tester.enterText(fields.at(0), 'Test bank');
      await tester.enterText(fields.at(1), 'Test Holder');
      await tester.enterText(fields.at(2), '00012345678');
      await tester.ensureVisible(find.text('Save payout details'));
      await tester.tap(find.text('Save payout details'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(saved!['account'], '00012345678');
      await tester.tap(find.text('Bank details'));
      await tester.pumpAndSettle();
      expect(gets, 2);
      expect(find.text('00012345678'), findsOneWidget);
      await tester.enterText(fields.at(2), '00998877665');
      await tester.ensureVisible(find.text('Save payout details'));
      await tester.tap(find.text('Save payout details'));
      await tester.pumpAndSettle();
      expect(posts, 2);
      expect(saved!['account'], '00998877665');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'hub manager selects the receiving hub and saves its own details',
    (tester) async {
      String? submittedTarget;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            final data = jsonDecode(request.body) as Map;
            submittedTarget = data['target'] as String;
            expect(data['account'], '000SECOND');
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'accounts': [
                row(
                  key: 'hub-11',
                  label: 'First hub',
                  details: {
                    'provider': 'First bank',
                    'holder': 'First holder',
                    'account': '000FIRST',
                  },
                ),
                row(
                  key: 'hub-12',
                  label: 'Second hub',
                  details: {
                    'provider': 'Second bank',
                    'holder': 'Second holder',
                    'account': '000SECOND',
                  },
                ),
              ],
            }),
            200,
          );
        }),
      );
      await show(tester, api);
      await tester.tap(find.text('First hub'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Second hub').last);
      await tester.pumpAndSettle();
      expect(find.text('000SECOND'), findsOneWidget);
      expect(find.text('000FIRST'), findsNothing);
      await tester.ensureVisible(find.text('Save payout details'));
      await tester.tap(find.text('Save payout details'));
      await tester.pumpAndSettle();
      expect(submittedTarget, 'hub-12');
      expect(tester.takeException(), isNull);
    },
  );

  for (final width in [320.0, 390.0]) {
    testWidgets('bank form fits $width with larger text and the keyboard', (
      tester,
    ) async {
      final api = ApiClient(
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'accounts': [row()],
            }),
            200,
          ),
        ),
      );
      await show(tester, api, width: width, scale: 1.35, keyboard: 280);
      await tester.enterText(find.byType(TextFormField).at(2), '0001234');
      await tester.ensureVisible(find.text('Save payout details'));
      await tester.pumpAndSettle();
      final rect = tester.getRect(find.text('Save payout details'));
      expect(rect.right, lessThanOrEqualTo(width));
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('failed lookup retries and rejected save keeps typed details', (
    tester,
  ) async {
    var gets = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'POST') {
          return http.Response(
            '{"message":"Check the receiving account."}',
            422,
          );
        }
        gets++;
        return gets == 1
            ? http.Response('{"message":"Please retry."}', 500)
            : http.Response(
                jsonEncode({
                  'accounts': [row()],
                }),
                200,
              );
      }),
    );
    await show(tester, api);
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'Test bank');
    await tester.enterText(fields.at(1), 'Test holder');
    await tester.enterText(fields.at(2), '0001234');
    await tester.ensureVisible(find.text('Save payout details'));
    await tester.tap(find.text('Save payout details'));
    await tester.pumpAndSettle();
    expect(find.text('Check the receiving account.'), findsOneWidget);
    expect(find.text('0001234'), findsOneWidget);
    expect(find.text('Saved payout account'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('repeated taps cannot submit duplicate account saves', (
    tester,
  ) async {
    var posts = 0;
    final pending = Completer<http.Response>();
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'POST') {
          posts++;
          return pending.future;
        }
        return http.Response(
          jsonEncode({
            'accounts': [
              row(
                details: {
                  'provider': 'Bank',
                  'holder': 'Holder',
                  'account': '0001234',
                },
              ),
            ],
          }),
          200,
        );
      }),
    );
    await show(tester, api);
    await tester.ensureVisible(find.text('Save payout details'));
    await tester.tap(find.text('Save payout details'));
    await tester.pump();
    expect(find.text('Saving…'), findsOneWidget);
    await tester.tap(find.text('Saving…'));
    expect(posts, 1);
    pending.complete(http.Response('{}', 200));
    await tester.pumpAndSettle();
    expect(find.text('Saved payout account'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
