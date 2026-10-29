import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/rider_provider.dart';
import '../lib/screens/profile/profile_screen.dart';
import '../lib/services/rider_service.dart';

class _MemoryTokenStorage extends TokenStorage {
  String? token;
  String? user;

  @override
  Future<void> saveToken(String value) async => token = value;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> deleteToken() async => token = null;

  @override
  Future<void> saveUser(String value) async => user = value;

  @override
  Future<String?> readUser() async => user;

  @override
  Future<void> deleteUser() async => user = null;

  @override
  Future<void> clear() async {
    token = null;
    user = null;
  }
}

Map<String, dynamic> _rider({String phone = '09171234567'}) => {
  'id': 5,
  'name': 'Test Rider',
  'email': 'rider@example.com',
  'phone': phone,
  'vehicle_type': 'motorcycle',
  'license_plate': 'ABC 1234',
  'status': 'offline',
  'is_online': false,
  'can_go_online': true,
};

void main() {
  testWidgets('edit profile blocks an invalid mobile number locally', (
    tester,
  ) async {
    var patchCalls = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'PATCH') {
          patchCalls++;
          return http.Response(
            jsonEncode({'rider': _rider()}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response(
          jsonEncode({
            'vehicle_types': [
              {'name': 'motorcycle', 'label': 'Motorcycle', 'capacity_kg': 30},
            ],
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(
            create: (_) => RiderProvider(RiderService(api)),
          ),
          ChangeNotifierProvider(
            create: (_) =>
                AuthProvider(api: api, storage: _MemoryTokenStorage()),
          ),
        ],
        child: const MaterialApp(
          home: EditProfileScreen(
            initialPhone: '09171234567',
            initialVehicle: 'motorcycle',
            initialPlate: 'ABC 1234',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, '12345');
    await tester.tap(find.text('Save Changes'));
    await tester.pumpAndSettle();

    expect(
      find.textContaining('Please enter a valid Philippine mobile number'),
      findsOneWidget,
    );
    expect(patchCalls, 0);
  });

  testWidgets('valid edit normalizes the phone and saves once', (tester) async {
    String? patchBody;
    var patchCalls = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'PATCH') {
          patchCalls++;
          patchBody = request.body;
          return http.Response(
            jsonEncode({'rider': _rider(phone: '09171234567')}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response(
          jsonEncode({'vehicle_types': []}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(
            create: (_) => RiderProvider(RiderService(api)),
          ),
          ChangeNotifierProvider(
            create: (_) =>
                AuthProvider(api: api, storage: _MemoryTokenStorage()),
          ),
        ],
        child: const MaterialApp(
          home: EditProfileScreen(
            initialPhone: '09171234567',
            initialVehicle: 'motorcycle',
            initialPlate: 'ABC 1234',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byType(TextFormField).first,
      '+63 917 123 4567',
    );
    await tester.tap(find.text('Save Changes'));
    await tester.pumpAndSettle();

    expect(patchCalls, 1);
    expect(patchBody, contains('"phone":"09171234567"'));
  });
}
