import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
// (Same checkout-wide package-config note as the other runnable tests.)
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/models/conversation.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/messages/conversations_screen.dart';
import '../lib/screens/messages/messages_screen.dart';
import '../lib/services/message_service.dart';

Map<String, dynamic> _threadJson({
  required int id,
  required String name,
  required String type,
  int unread = 0,
  String? tracking,
  int? orderId,
  String? preview,
}) =>
    {
      'id': id,
      'name': name,
      'type': type,
      'type_label': type.toUpperCase(),
      'order_id': orderId,
      'tracking': tracking,
      'preview': preview,
      'unread': unread,
      'last_message_at': '2026-09-25T10:00:00.000Z',
      'time': '10:00 AM',
    };

Map<String, dynamic> _msgJson(int id, String body, {bool mine = false}) => {
      'id': id,
      'mine': mine,
      'sender_type': mine ? 'rider' : 'logistics',
      'body': body,
      'deleted': false,
      'is_read': true,
      'created_at': '2026-09-25T10:0$id:00.000Z',
      'time': '10:0$id AM',
      'day': '2026-09-25',
      'dayLabel': 'Today',
      'attachments': [],
    };

MockClient _backend() {
  return MockClient((http.BaseRequest request) async {
    http.Response json(Object data, [int status = 200]) => http.Response(
          jsonEncode(data),
          status,
          headers: {'content-type': 'application/json'},
        );
    final path = request.url.path;
    final query = request.url.queryParameters;

    bool isPath(String suffix) => path.endsWith(suffix);

    if (isPath('/rider/conversations') && request.method == 'GET') {
      var threads = [
        _threadJson(
          id: 7,
          name: 'ABC Store',
          type: 'seller',
          unread: 2,
          tracking: 'TRK-20260925-AB12',
          orderId: 1024,
          preview: 'Parcel is ready.',
        ),
        _threadJson(id: 9, name: 'Logistics', type: 'rider', preview: 'Hi.'),
      ];
      if ((query['search'] ?? '').isNotEmpty) {
        final q = query['search']!.toLowerCase();
        threads = threads
            .where((t) =>
                (t['name'] as String).toLowerCase().contains(q) ||
                (t['tracking'] as String? ?? '').toLowerCase().contains(q))
            .toList();
      }
      return json({
        'conversations': threads,
        'pagination': {
          'current_page': 1,
          'last_page': 1,
          'total': threads.length,
          'per_page': 20
        },
      });
    }
    if (isPath('/rider/conversations/7') && request.method == 'GET') {
      return json({
        'conversation': _threadJson(
          id: 7,
          name: 'ABC Store',
          type: 'seller',
          unread: 0,
          tracking: 'TRK-20260925-AB12',
          orderId: 1024,
        ),
        'messages': [
          _msgJson(102, 'On my way', mine: false),
          _msgJson(101, 'Hello', mine: true),
        ],
        'pagination': {
          'current_page': 1,
          'last_page': 1,
          'total': 2,
          'per_page': 20
        },
      });
    }
    if (isPath('/rider/conversations/7/messages') &&
        request.method == 'POST') {
      return json({
        'message': 'Sent.',
        'data': _msgJson(103, 'Thanks!', mine: true),
      }, 201);
    }
    return json({}, 404);
  });
}

MessageService _service() =>
    MessageService(ApiClient(client: _backend()));

Future<void> _pumpInbox(WidgetTester tester) async {
  SharedPreferences.setMockInitialValues({});
  await tester.pumpWidget(
    ChangeNotifierProvider<AuthProvider>.value(
      value: AuthProvider(
        api: ApiClient(client: _backend()),
        storage: TokenStorage(),
      ),
      child: const MaterialApp(home: ConversationsScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  group('ConversationThread model', () {
    test('context line combines role and tracking', () {
      const thread = ConversationThread(
        id: 7,
        name: 'ABC Store',
        type: 'seller',
        typeLabel: 'SELLER',
        tracking: 'TRK-1',
      );
      expect(thread.contextLine, 'Seller · TRK-1');
    });

    test('context line falls back gracefully', () {
      const bare = ConversationThread(id: 1, name: 'X', type: '');
      expect(bare.contextLine, '');
    });
  });

  group('MessageService conversations', () {
    test('lists threads with unread and tracking', () async {
      final page = await _service().listConversations();
      expect(page.threads.length, 2);
      expect(page.hasMore, isFalse);
      final seller =
          page.threads.firstWhere((t) => t.type == 'seller');
      expect(seller.name, 'ABC Store');
      expect(seller.unread, 2);
      expect(seller.tracking, 'TRK-20260925-AB12');
    });

    test('search narrows the inbox', () async {
      final page = await _service().listConversations(search: 'abc');
      expect(page.threads.length, 1);
      expect(page.threads.first.name, 'ABC Store');
    });

    test('thread messages and send round-trip', () async {
      final detail = await _service().getThread(7);
      expect(detail.messages.length, 2);
      expect(detail.hasMore, isFalse);

      final sent = await _service()
          .sendToThread(7, body: 'Thanks!');
      expect(sent['body'], 'Thanks!');
    });
  });

  group('ConversationsScreen inbox', () {
    testWidgets('renders threads with unread state', (tester) async {
      await _pumpInbox(tester);

      expect(find.text('ABC Store'), findsOneWidget);
      expect(find.text('Logistics'), findsOneWidget);
      expect(find.text('Parcel is ready.'), findsOneWidget);
      expect(find.text('2'), findsOneWidget);
    });

    testWidgets('tap opens the thread chat', (tester) async {
      await _pumpInbox(tester);

      await tester.tap(find.text('ABC Store'));
      await tester.pumpAndSettle();

      // Chat shows newest-first payload chronologically with composer.
      expect(find.text('Hello'), findsOneWidget);
      expect(find.text('On my way'), findsOneWidget);
      expect(find.text('Type a message...'), findsOneWidget);
    });

    testWidgets('empty inbox explains auto-discovery', (tester) async {
      SharedPreferences.setMockInitialValues({});
      final emptyBackend = MockClient((_) async => http.Response(
            jsonEncode({
              'conversations': [],
              'pagination': {
                'current_page': 1,
                'last_page': 1,
                'total': 0,
                'per_page': 20
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          ));
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: emptyBackend),
            storage: TokenStorage(),
          ),
          child: const MaterialApp(home: ConversationsScreen()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('No conversations yet.'), findsOneWidget);
      expect(find.textContaining('appear here automatically'), findsOneWidget);
    });
  });

  group('MessagesScreen thread mode', () {
    testWidgets('sends into the open thread', (tester) async {
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: _backend()),
            storage: TokenStorage(),
          ),
          child: MaterialApp(
            home: MessagesScreen(
              thread: const ConversationThread(
                id: 7,
                name: 'ABC Store',
                type: 'seller',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'Thanks!');
      await tester.tap(find.byIcon(Icons.send_outlined));
      await tester.pumpAndSettle();

      expect(find.text('Thanks!'), findsWidgets);
    });

    testWidgets('duplicate taps send only once', (tester) async {
      var posts = 0;
      final slowBackend = MockClient((http.BaseRequest request) async {
        http.Response json(Object data, [int status = 200]) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        bool isPath(String suffix) => path.endsWith(suffix);
        if (isPath('/rider/conversations/7') && request.method == 'GET') {
          return json({
            'conversation': _threadJson(id: 7, name: 'ABC', type: 'seller'),
            'messages': [],
            'pagination': {
              'current_page': 1,
              'last_page': 1,
              'total': 0,
              'per_page': 20
            },
          });
        }
        if (isPath('/rider/conversations/7/messages') &&
            request.method == 'POST') {
          posts++;
          await Future<void>.delayed(const Duration(milliseconds: 300));
          return json({
            'message': 'Sent.',
            'data': _msgJson(200 + posts, 'Hi!', mine: true),
          }, 201);
        }
        return json({}, 404);
      });
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: slowBackend),
            storage: TokenStorage(),
          ),
          child: MaterialApp(
            home: MessagesScreen(
              thread: const ConversationThread(
                id: 7,
                name: 'ABC Store',
                type: 'seller',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'Hi!');
      final sendCenter =
          tester.getCenter(find.byIcon(Icons.send_outlined));
      await tester.tapAt(sendCenter);
      await tester.pump(const Duration(milliseconds: 50));
      // Second tap lands on the disabled sending state: no second request.
      await tester.tapAt(sendCenter);
      await tester.pumpAndSettle();

      expect(posts, 1);
    });

    testWidgets('message menu edits own message', (tester) async {
      var patchedBody = '';
      final editBackend = MockClient((http.BaseRequest request) async {
        http.Response json(Object data, [int status = 200]) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        bool isPath(String suffix) => path.endsWith(suffix);
        if (isPath('/rider/conversations/7') && request.method == 'GET') {
          return json({
            'conversation': _threadJson(id: 7, name: 'ABC', type: 'seller'),
            'messages': [_msgJson(101, 'Typo here', mine: true)],
            'pagination': {
              'current_page': 1,
              'last_page': 1,
              'total': 1,
              'per_page': 20
            },
          });
        }
        if (isPath('/rider/conversations/7/messages/101') &&
            request.method == 'PATCH') {
          patchedBody = 'seen';
          return json({'ok': true});
        }
        return json({}, 404);
      });
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: editBackend),
            storage: TokenStorage(),
          ),
          child: MaterialApp(
            home: MessagesScreen(
              thread: const ConversationThread(
                id: 7,
                name: 'ABC Store',
                type: 'seller',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byIcon(Icons.more_vert).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Edit'));
      await tester.pumpAndSettle();

      expect(find.text('Editing message'), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'Fixed text');
      await tester.tap(find.byIcon(Icons.send_outlined));
      await tester.pumpAndSettle();

      expect(patchedBody, 'seen');
      expect(find.text('Fixed text'), findsOneWidget);
      expect(find.text('Editing message'), findsNothing);
    });

    testWidgets('message menu deletes with confirmation', (tester) async {
      var deleted = false;
      final deleteBackend = MockClient((http.BaseRequest request) async {
        http.Response json(Object data, [int status = 200]) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        bool isPath(String suffix) => path.endsWith(suffix);
        if (isPath('/rider/conversations/7') && request.method == 'GET') {
          return json({
            'conversation': _threadJson(id: 7, name: 'ABC', type: 'seller'),
            'messages': [_msgJson(101, 'Remove me', mine: true)],
            'pagination': {
              'current_page': 1,
              'last_page': 1,
              'total': 1,
              'per_page': 20
            },
          });
        }
        if (isPath('/rider/conversations/7/messages/101') &&
            request.method == 'DELETE') {
          deleted = true;
          return json({'ok': true});
        }
        return json({}, 404);
      });
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: deleteBackend),
            storage: TokenStorage(),
          ),
          child: MaterialApp(
            home: MessagesScreen(
              thread: const ConversationThread(
                id: 7,
                name: 'ABC Store',
                type: 'seller',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byIcon(Icons.more_vert).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Delete'));
      await tester.pumpAndSettle();

      expect(find.text('Delete message?'), findsOneWidget);
      await tester.tap(find.text('Delete').last);
      await tester.pumpAndSettle();

      expect(deleted, isTrue);
      expect(find.text('This message was deleted'), findsOneWidget);
    });

    testWidgets('attach button offers photo and file', (tester) async {
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: _backend()),
            storage: TokenStorage(),
          ),
          child: MaterialApp(
            home: MessagesScreen(
              thread: const ConversationThread(
                id: 7,
                name: 'ABC Store',
                type: 'seller',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byIcon(Icons.add_circle_outline));
      await tester.pumpAndSettle();

      expect(find.text('Photo'), findsOneWidget);
      expect(find.text('File'), findsOneWidget);
    });
  });
}
