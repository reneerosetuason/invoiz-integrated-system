import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/services/tutorial_completion_store.dart';

const _secureChannel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

Map<String, dynamic> _rider({bool online = true}) => {
  'id': 7,
  'name': 'Pedro Jose Garcia',
  'email': 'pedrojose.garcia@example.com',
  'phone': '09451239876',
  'vehicle_type': 'motorcycle',
  'license_plate': '123ABC',
  'company': 'Bilis Hatid Express Inc.',
  'status': online ? 'available' : 'offline',
  'is_online': online,
  'can_go_online': true,
};

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final secureStore = <String, String>{};

  setUp(() {
    secureStore.clear();
    SharedPreferences.setMockInitialValues({});
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

  test('a saved session reopens the rider home without logging in again', () async {
    final storage = TokenStorage();
    await storage.saveToken('token-123');
    await storage.saveUser(jsonEncode({'rider': _rider()}));

    String? sentAuth;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/rider/profile')) {
          sentAuth = request.headers['Authorization'] ??
              request.headers['authorization'];
          return _json({'rider': _rider()});
        }
        return _json({'message': 'Not found.'}, 404);
      }),
    );

    final auth = AuthProvider(api: api, storage: storage);
    await auth.restoreSession();

    expect(auth.status, AuthStatus.authenticated);
    expect(auth.rider?.name, 'Pedro Jose Garcia');
    expect(auth.rider?.company, 'Bilis Hatid Express Inc.');
    expect(sentAuth, contains('token-123'));
  });

  test('the cached rider still opens the app when the network is down', () async {
    final storage = TokenStorage();
    await storage.saveToken('token-123');
    await storage.saveUser(jsonEncode({'rider': _rider()}));

    final api = ApiClient(
      client: MockClient((_) async {
        throw http.ClientException('offline');
      }),
    );

    final auth = AuthProvider(api: api, storage: storage);
    await auth.restoreSession();

    expect(auth.status, AuthStatus.authenticated);
    expect(auth.rider?.name, 'Pedro Jose Garcia');
  });

  test('logging out clears the saved session so Back cannot restore it', () async {
    const tutorialStore = TutorialCompletionStore();
    await tutorialStore.markCompleted();
    final storage = TokenStorage();
    await storage.saveToken('token-123');
    await storage.saveUser(jsonEncode({'rider': _rider()}));

    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/rider/status')) {
          return _json({'rider': _rider(online: false)});
        }
        if (request.url.path.endsWith('/logout')) {
          return _json({'message': 'Logged out.'});
        }
        if (request.url.path.endsWith('/rider/profile')) {
          return _json({'rider': _rider()});
        }
        return _json({'message': 'Not found.'}, 404);
      }),
    );

    final auth = AuthProvider(api: api, storage: storage);
    await auth.restoreSession();
    expect(auth.isAuthenticated, isTrue);

    await auth.logout();

    expect(auth.status, AuthStatus.unauthenticated);
    expect(auth.rider, isNull);
    expect(await storage.readToken(), isNull);
    expect(await storage.readCachedUser(), isNull);
    expect(await tutorialStore.isCompleted(), isTrue);

    // A fresh app start finds no token and stays on the login screen.
    final restarted = AuthProvider(api: api, storage: storage);
    await restarted.restoreSession();
    expect(restarted.status, AuthStatus.unauthenticated);
  });
}
