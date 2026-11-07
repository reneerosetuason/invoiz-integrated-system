import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';

const _secureChannel = MethodChannel(
  'plugins.it_nomads.com/flutter_secure_storage',
);

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

Map<String, dynamic> _courier() => {
  'id': 5,
  'name': 'Justin Barber',
  'company': 'INVOIZ Logistics',
  'email': 'justin.barber@example.com',
  'phone': '09171234567',
  'is_online': false,
  'status': 'available',
  'vehicle_arrangement': 'company',
  'has_assigned_truck': true,
  'vehicle': {
    'id': 5,
    'plate_number': 'DEV-TEST-TRK-01',
    'type': 'light truck',
    'status': 'assigned',
    'parcel_capacity': 100,
    'capacity_kg': 2500,
  },
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final secureStore = <String, String>{};

  setUp(() {
    secureStore.clear();
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

  test(
    'logistics login and session restore never use rider endpoints',
    () async {
      final requests = <String>[];
      final payload = {
        'account_type': 'logistics_staff',
        'user': {
          'id': 42,
          'name': 'Approved Hub Staff',
          'role': 'logistics_staff',
        },
        'logistics': {
          'scope': 'hub',
          'can_scan': true,
          'facility': {'id': 8, 'name': 'Assigned Hub'},
        },
      };
      final api = ApiClient(
        client: MockClient((request) async {
          requests.add(request.url.path);
          if (request.url.path.endsWith('/login')) {
            return _json({'token': 'logistics-test-token', ...payload});
          }
          if (request.url.path.endsWith('/user')) return _json(payload);
          return _json({
            'message':
                'Forbidden. You are not authorized to access this resource.',
          }, 403);
        }),
      );
      final storage = TokenStorage();
      final auth = AuthProvider(api: api, storage: storage);
      expect(
        await auth.login(
          email: 'hub@example.test',
          password: 'fixture-password',
        ),
        isTrue,
      );
      expect(auth.isLogistics, isTrue);
      expect(auth.logistics?.facilityId, 8);
      expect(auth.rider, isNull);
      final restored = AuthProvider(api: api, storage: storage);
      await restored.restoreSession();
      expect(restored.isAuthenticated, isTrue);
      expect(restored.isLogistics, isTrue);
      expect(requests, ['/api/logistics/login', '/api/logistics/user']);
    },
  );

  test('repairs a logistics session cached as a rider by an old APK', () async {
    final requests = <String>[];
    final api = ApiClient(
      client: MockClient((request) async {
        requests.add(request.url.path);
        if (request.url.path.endsWith('/user')) {
          return _json({
            'account_type': 'logistics_staff',
            'user': {'id': 42, 'name': 'Hub Staff', 'role': 'logistics_staff'},
            'logistics': {
              'scope': 'hub',
              'can_scan': true,
              'facility': {'id': 8, 'name': 'Assigned Hub'},
            },
          });
        }
        return _json({
          'message':
              'Forbidden. You are not authorized to access this resource.',
        }, 403);
      }),
    );
    final storage = TokenStorage();
    await storage.saveToken('logistics-test-token');
    await storage.saveUser(
      jsonEncode({
        'workflow_type': 'delivery_rider',
        'rider': {'id': 0},
      }),
    );
    final auth = AuthProvider(api: api, storage: storage);
    await auth.restoreSession();
    expect(auth.isAuthenticated, isTrue);
    expect(auth.isLogistics, isTrue);
    expect(auth.rider, isNull);
    expect(auth.logistics?.facilityId, 8);
    expect(requests, ['/api/logistics/rider/profile', '/api/logistics/user']);
    expect((await storage.readCachedUser())?['workflow_type'], 'logistics');
  });

  test('a 403 from the rider login falls back to hub courier login', () async {
    final requests = <String>[];
    final api = ApiClient(
      client: MockClient((request) async {
        requests.add(request.url.path);
        if (request.url.path.endsWith('/courier/login')) {
          return _json({'token': 'courier-token', 'courier': _courier()});
        }
        if (request.url.path.endsWith('/login')) {
          return _json({
            'message':
                'This account is not registered as a rider or hub transport driver.',
          }, 403);
        }
        return _json({'message': 'Not found.'}, 404);
      }),
    );

    final auth = AuthProvider(api: api, storage: TokenStorage());
    final ok = await auth.login(
      email: 'justin.barber@example.com',
      password: 'secret123',
    );

    // ignore: avoid_print
    print('DEBUG ok=$ok error=${auth.error} requests=$requests');

    expect(ok, isTrue);
    expect(auth.isAuthenticated, isTrue);
    expect(auth.isHubCourier, isTrue);
    expect(auth.courier?.name, 'Justin Barber');
    expect(auth.rider, isNull);
    expect(requests.any((path) => path.endsWith('/courier/login')), isTrue);
  });

  test('the rider error is preserved when courier login also fails', () async {
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/courier/login')) {
          return _json({'message': 'Invalid courier credentials.'}, 401);
        }
        if (request.url.path.endsWith('/login')) {
          return _json({
            'message':
                'This account is not registered as a rider or hub transport driver.',
          }, 403);
        }
        return _json({'message': 'Not found.'}, 404);
      }),
    );

    final auth = AuthProvider(api: api, storage: TokenStorage());
    final ok = await auth.login(
      email: 'justin.barber@example.com',
      password: 'wrong-password',
    );

    expect(ok, isFalse);
    expect(auth.status, AuthStatus.unauthenticated);
    expect(
      auth.error,
      'This account is not registered as a rider or hub transport driver.',
    );
  });
}
