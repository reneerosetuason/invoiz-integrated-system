import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/screens/apply/application_status_screen.dart';

void main() {
  http.Client fakeClient({
    Map<String, dynamic>? statusResponse,
    int statusCode = 200,
    bool requireOtp = false,
  }) {
    return MockClient((request) async {
      final path = request.url.path;
      if (path.contains('/rider/application-status')) {
        if (requireOtp && request.headers['X-Application-Access'] == null) {
          return http.Response(
            jsonEncode({
              'message':
                  'Verify this email with a fresh code before checking application details, or sign in to its verified account.',
              'errors': {
                'email': [
                  'Verify this email with a fresh code before checking application details, or sign in to its verified account.'
                ]
              }
            }),
            422,
            headers: {'content-type': 'application/json'},
          );
        }

        if (statusCode == 404) {
          return http.Response(
            jsonEncode({'application': null, 'message': 'No application found.'}),
            404,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response(
          jsonEncode(
            statusResponse ??
                {
                  'application': {
                    'id': 5,
                    'reference_number': 'RID-2026-0005',
                    'name': 'Jose De leon',
                    'email': 'aj17abcde@gmail.com',
                    'phone': '09825479641',
                    'status': 'pending',
                    'submitted_via': 'mobile',
                    'application_type': 'delivery_rider',
                    'application_type_label': 'Delivery Rider',
                    'rider_type': 'full_time',
                    'vehicle_ownership': 'own',
                    'license_number': 'NO236527593275',
                    'license_expiry': '2027-03-31',
                    'vehicle_registration': '2089544716891',
                    'created_at': '2026-11-02T06:48:00.000000Z',
                    'reviewed_at': null,
                    'notes': null,
                    'documents': [
                      {
                        'type': 'drivers_license',
                        'name': 'Screenshot 2026-11-02 140352.png'
                      }
                    ],
                    'logs': [],
                  }
                },
          ),
          200,
          headers: {'content-type': 'application/json'},
        );
      }

      if (path.contains('/rider/email/request-code')) {
        return http.Response(
          jsonEncode({'message': 'Verification code sent to your email.'}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }

      if (path.contains('/rider/email/verify')) {
        return http.Response(
          jsonEncode({
            'message': 'Email verified successfully.',
            'application_access_token':
                'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90',
            'email': 'aj17abcde@gmail.com',
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }

      return http.Response('{"error": "not found"}', 404);
    });
  }

  group('ApplicationStatusScreen', () {
    testWidgets('validation error when email is empty', (tester) async {
      final client = ApiClient(client: fakeClient());
      await tester.pumpWidget(
        MaterialApp(
          home: ApplicationStatusScreen(apiClient: client),
        ),
      );

      await tester.tap(find.text('Check Status'));
      await tester.pumpAndSettle();

      expect(find.text('Enter your email to check status.'), findsOneWidget);
    });

    testWidgets('successful lookup displays submitted application details',
        (tester) async {
      final client = ApiClient(client: fakeClient());
      await tester.pumpWidget(
        MaterialApp(
          home: ApplicationStatusScreen(
            email: 'aj17abcde@gmail.com',
            apiClient: client,
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('PENDING'), findsOneWidget);
      expect(find.text('RID-2026-0005'), findsOneWidget);
      expect(find.text('Jose De leon'), findsOneWidget);
      expect(find.text('aj17abcde@gmail.com'), findsNWidgets(2));
      expect(find.text('Application Under Review'), findsOneWidget);
      expect(find.textContaining('DRIVERS LICENSE'), findsOneWidget);
    });

    testWidgets('handles not found response cleanly', (tester) async {
      final client = ApiClient(client: fakeClient(statusCode: 404));
      await tester.pumpWidget(
        MaterialApp(
          home: ApplicationStatusScreen(
            email: 'notfound@example.com',
            apiClient: client,
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('No application found for this email.'), findsOneWidget);
    });

    testWidgets(
        'shows inline verification flow when unverified and allows OTP verification',
        (tester) async {
      final client = ApiClient(client: fakeClient(requireOtp: true));
      await tester.pumpWidget(
        MaterialApp(
          home: ApplicationStatusScreen(
            email: 'aj17abcde@gmail.com',
            apiClient: client,
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Expect privacy gate message and OTP section
      expect(find.text('Verify Email Ownership'), findsOneWidget);
      expect(
          find.text(
              'Please verify this email with a one-time code to view full application details.'),
          findsOneWidget);

      // Scroll to Send Code and tap
      await tester.ensureVisible(find.text('Send Code'));
      await tester.tap(find.text('Send Code'));
      await tester.pumpAndSettle();

      expect(find.text('Verification code sent to your email.'), findsOneWidget);

      // Enter code and tap Verify & View
      await tester.enterText(
          find.widgetWithText(TextField, '6-digit Verification Code'), '123456');
      await tester.ensureVisible(find.text('Verify & View'));
      await tester.tap(find.text('Verify & View'));
      await tester.pumpAndSettle();

      // Verified and displayed details!
      expect(find.text('PENDING'), findsOneWidget);
      expect(find.text('RID-2026-0005'), findsOneWidget);
      expect(find.text('Jose De leon'), findsOneWidget);
    });
  });
}
