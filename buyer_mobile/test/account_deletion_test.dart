import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/account_deletion_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DeletionFixture {
  Map<String, dynamic>? request;
  bool invalidPassword = false;
  final calls = <String>[];
  late final api = ApiService(
    client: MockClient((httpRequest) async {
      final path = httpRequest.url.path;
      if (httpRequest.method == 'POST') calls.add(path);
      var status = 200;
      Map<String, dynamic> data = {
        'unread_count': 0,
        'conversations': [],
        'notifications': [],
        'favorites': [],
      };
      if (path.endsWith('/account-deletion/verify')) {
        if (invalidPassword) {
          status = 422;
          data = {'message': 'Your current password is incorrect.'};
        }
      } else if (path.endsWith('/account-deletion/cancel')) {
        expect(jsonDecode(httpRequest.body)['confirm_cancellation'], true);
        request = {...request!, 'status': 'cancelled', 'can_cancel': false};
      } else if (path.endsWith('/account-deletion')) {
        if (httpRequest.method == 'POST') {
          final body = jsonDecode(httpRequest.body);
          expect(body['current_password'], 'Password123!');
          expect(body['confirm_deletion'], true);
          request = pending();
          status = 201;
        }
        data = {
          'deletion_request': request,
          'reasons': ['I no longer use INVOIZ.', 'Other (please specify).'],
          'retention_notice':
              'Transaction records are retained. Contact Support for a separate privacy review.',
        };
      }
      return http.Response(
        jsonEncode(data),
        status,
        headers: {'content-type': 'application/json'},
      );
    }),
  );

  static Map<String, dynamic> pending() => {
    'id': 1,
    'status': 'pending',
    'requested_at': '2026-11-04T12:00:00+08:00',
    'scheduled_archive_at': '2026-12-04T12:00:00+08:00',
    'remaining_days': 30,
    'can_cancel': true,
  };
}

Future<void> open(WidgetTester tester, DeletionFixture fixture) async {
  tester.view.physicalSize = const Size(390, 900);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    AuthServiceProvider(
      authService: AuthService(),
      child: MaterialApp(
        theme: AppTheme.light,
        home: AccountDeletionScreen(api: fixture.api),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> fill(
  WidgetTester tester, {
  String reason = 'I no longer use INVOIZ.',
}) async {
  await tester.ensureVisible(find.byType(DropdownButtonFormField<String>));
  await tester.tap(find.byType(DropdownButtonFormField<String>));
  await tester.pumpAndSettle();
  await tester.tap(find.text(reason).last);
  await tester.pumpAndSettle();
  await tester.enterText(find.byType(TextFormField).last, 'Password123!');
  await tester.ensureVisible(find.byType(CheckboxListTile));
  await tester.tap(find.byType(CheckboxListTile));
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.text('Submit deletion request'));
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });
  testWidgets(
    'verification precedes final confirmation and submission shows server deadline',
    (tester) async {
      final fixture = DeletionFixture();
      await open(tester, fixture);
      await fill(tester);
      await tester.tap(find.text('Submit deletion request'));
      await tester.pumpAndSettle();
      expect(fixture.calls.single, endsWith('/account-deletion/verify'));
      await tester.tap(
        find.widgetWithText(TextButton, 'Confirm Account Deletion'),
      );
      await tester.pumpAndSettle();
      expect(find.text('Account Deletion Scheduled'), findsOneWidget);
      expect(find.text('Remaining: 30 days'), findsOneWidget);
      expect(find.textContaining('2026-12-04T12:00:00+08:00'), findsOneWidget);
    },
  );
  testWidgets('Other requires explanation before verification', (tester) async {
    final fixture = DeletionFixture();
    await open(tester, fixture);
    await fill(tester, reason: 'Other (please specify).');
    await tester.tap(find.text('Submit deletion request'));
    await tester.pumpAndSettle();
    expect(find.text('Explain your reason.'), findsOneWidget);
    expect(fixture.calls, isEmpty);
  });
  testWidgets('invalid password does not show final confirmation or submit', (
    tester,
  ) async {
    final fixture = DeletionFixture()..invalidPassword = true;
    await open(tester, fixture);
    await fill(tester);
    await tester.tap(find.text('Submit deletion request'));
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
    expect(fixture.calls.length, 1);
    await tester.ensureVisible(
      find.text('Your current password is incorrect.'),
    );
    expect(find.text('Your current password is incorrect.'), findsOneWidget);
  });
  testWidgets('pending deletion can be cancelled without admin approval', (
    tester,
  ) async {
    final fixture = DeletionFixture()..request = DeletionFixture.pending();
    await open(tester, fixture);
    await tester.ensureVisible(find.text('Cancel Account Deletion'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cancel Account Deletion'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.widgetWithText(TextButton, 'Cancel Account Deletion'),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Previous request: cancelled'), findsOneWidget);
  });
  testWidgets(
    'on hold shows transaction blocker and cannot cancel after deadline',
    (tester) async {
      final fixture = DeletionFixture()
        ..request = {
          ...DeletionFixture.pending(),
          'status': 'on_hold',
          'can_cancel': false,
          'remaining_days': 0,
          'hold_reason': 'Payments are unresolved.',
        };
      await open(tester, fixture);
      expect(find.textContaining('Payments are unresolved.'), findsOneWidget);
      expect(find.text('Cancel Account Deletion'), findsNothing);
    },
  );
}
