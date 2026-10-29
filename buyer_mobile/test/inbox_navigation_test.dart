import 'package:invoiz/widgets/buyer_field.dart';
import 'package:flutter/cupertino.dart';
import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:invoiz/screens/home_screen.dart';
import 'package:invoiz/screens/wishlist_screen.dart';
import 'package:invoiz/screens/chat_screen.dart';
import 'package:invoiz/screens/notifications_screen.dart';
import 'package:invoiz/screens/order_detail_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/services/recently_viewed_service.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/widgets/main_layout.dart';
import 'package:invoiz/theme.dart';

class _SignedIn extends AuthService {
  @override
  bool get isLoggedIn => true;
}

http.Response json(Object value, [int code = 200]) => http.Response(
  jsonEncode(value),
  code,
  headers: {'content-type': 'application/json'},
);
Widget app(Widget child) => AuthServiceProvider(
  authService: _SignedIn(),
  child: MaterialApp(theme: AppTheme.light, home: child),
);
Map<String, dynamic> message(int id, String text) => {
  'id': id,
  'conversation_id': 9,
  'sender_id': 12,
  'body': text,
  'created_at': '2026-10-28T12:00:00Z',
  'sender': {'first_name': 'Shop'},
  'is_read': false,
};
Map<String, dynamic> notification(
  String id, {
  int? conversationId,
  int? orderId,
}) => {
  'id': id,
  'type': conversationId == null ? 'order' : 'chat',
  'title': 'Fixture notification $id',
  'body': 'Fixture update',
  'created_at': '2026-10-28T12:00:00Z',
  'read': false,
  'conversation_id': conversationId,
  'order_id': orderId,
};
void main() {
  WidgetController.hitTestWarningShouldBeFatal = true;
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
    ApiService.onSessionInvalidated = null;
  });
  for (final index in [-1, 5, 999]) {
    testWidgets('secondary/invalid tab $index never crashes CupertinoTabBar', (
      tester,
    ) async {
      await tester.pumpWidget(
        app(
          MainLayout(
            currentIndex: index,
            showTopActions: false,
            showNotificationIcon: false,
            child: const Text('Secondary page'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Secondary page'), findsOneWidget);
      expect(find.byType(CupertinoTabBar), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets(
    'all five primary tabs retain their correct selected destination',
    (tester) async {
      for (var index = 0; index < 5; index++) {
        await tester.pumpWidget(
          app(
            MainLayout(
              currentIndex: index,
              showTopActions: false,
              showNotificationIcon: false,
              child: const Text('Primary page'),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          tester
              .widget<CupertinoTabBar>(find.byType(CupertinoTabBar))
              .currentIndex,
          index,
        );
        expect(tester.takeException(), isNull);
      }
    },
  );
  testWidgets(
    'real home message and notification buttons open their pages and return safely',
    (tester) async {
      final api = ApiService(
        client: MockClient(
          (r) async => switch (r.url.path.split('/api/').last) {
            'products' => json({
              'data': [],
              'total': 0,
              'last_page': 1,
              'current_page': 1,
            }),
            'categories' => json({'categories': []}),
            'conversations' => json({'conversations': []}),
            'notifications' => json({'notifications': [], 'unread_count': 0}),
            _ => json({}),
          },
        ),
      );
      await tester.pumpWidget(app(HomeScreen(api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(CupertinoIcons.chat_bubble));
      await tester.pumpAndSettle();
      expect(find.text('No conversations yet.'), findsOneWidget);
      expect(find.byType(CupertinoTabBar), findsNothing);
      await tester.tap(find.text('Start a Conversation'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Send'));
      await tester.pumpAndSettle();
      expect(find.text('Enter a message.'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(CupertinoIcons.bell));
      await tester.pumpAndSettle();
      expect(find.text('No notifications yet.'), findsOneWidget);
      expect(find.byType(CupertinoTabBar), findsNothing);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.byType(HomeScreen), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'conversation opens its real thread and refreshes safely after returning',
    (tester) async {
      var reads = 0;
      final api = ApiService(
        client: MockClient((r) async {
          if (r.url.path.endsWith('/messages'))
            return json({
              'messages': [message(1, 'Shop reply')],
            });
          reads++;
          return json({
            'conversations': [
              {
                'id': 9,
                'subject': 'Product question',
                'seller_id': 12,
                'unread_count': 1,
              },
            ],
          });
        }),
      );
      await tester.pumpWidget(app(ChatScreen(api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Product question'));
      await tester.pumpAndSettle();
      expect(find.text('Shop reply'), findsOneWidget);
      expect(find.byType(CupertinoTabBar), findsNothing);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(reads, 2);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'failed reply keeps the draft, retry is single flight, success clears it',
    (tester) async {
      var sends = 0;
      final pending = Completer<http.Response>();
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'GET') return json({'messages': []});
          sends++;
          if (sends == 1)
            return json({
              'message': 'Connection failed. Retry your message.',
            }, 503);
          return pending.future;
        }),
      );
      await tester.pumpWidget(
        app(ChatThreadScreen(api: api, conversationId: 9, subject: 'Shop')),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(CupertinoTextField),
        'Keep this draft',
      );
      await tester.tap(find.byIcon(CupertinoIcons.arrow_up));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<CupertinoTextField>(find.byType(CupertinoTextField))
            .controller!
            .text,
        'Keep this draft',
      );
      await tester.tap(find.byIcon(CupertinoIcons.arrow_up));
      await tester.pump();
      tester
          .widget<CupertinoTextField>(find.byType(CupertinoTextField))
          .onSubmitted!('Keep this draft');
      expect(sends, 2);
      pending.complete(json({'message': message(2, 'Keep this draft')}, 201));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<CupertinoTextField>(find.byType(CupertinoTextField))
            .controller!
            .text,
        isEmpty,
      );
      expect(find.text('Keep this draft'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'late reply after leaving the thread does not set state after disposal',
    (tester) async {
      final pending = Completer<http.Response>();
      final api = ApiService(
        client: MockClient(
          (r) async =>
              r.method == 'GET' ? json({'messages': []}) : pending.future,
        ),
      );
      await tester.pumpWidget(
        app(ChatThreadScreen(api: api, conversationId: 9, subject: 'Shop')),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(CupertinoTextField), 'Fixture reply');
      await tester.tap(find.byIcon(CupertinoIcons.arrow_up));
      await tester.pump();
      await tester.pumpWidget(const SizedBox());
      pending.complete(json({'message': message(2, 'Fixture reply')}, 201));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('new incoming replies appear while the conversation stays open', (
    tester,
  ) async {
    var loads = 0;
    final api = ApiService(
      client: MockClient(
        (r) async => json({
          'messages': [message(++loads, 'Reply $loads')],
        }),
      ),
    );
    await tester.pumpWidget(
      app(ChatThreadScreen(api: api, conversationId: 9, subject: 'Shop')),
    );
    await tester.pumpAndSettle();
    expect(find.text('Reply 1'), findsOneWidget);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpAndSettle();
    expect(loads, greaterThan(1));
    expect(find.text('Reply $loads'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    expect(tester.takeException(), isNull);
  });
  for (final kind in ['messages', 'thread', 'notifications']) {
    testWidgets('$kind shows retry after a network error and recovers', (
      tester,
    ) async {
      var calls = 0;
      final api = ApiService(
        client: MockClient((r) async {
          if (++calls == 1)
            return json({'message': 'Temporarily unavailable.'}, 503);
          return json({
            'conversations': [],
            'messages': [],
            'notifications': [],
            'unread_count': 0,
          });
        }),
      );
      final Widget screen = switch (kind) {
        'messages' => ChatScreen(api: api),
        'thread' => ChatThreadScreen(
          api: api,
          conversationId: 9,
          subject: 'Shop',
        ),
        _ => NotificationsScreen(api: api),
      };
      await tester.pumpWidget(app(screen));
      await tester.pumpAndSettle();
      expect(find.text('Try again'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Try again'), findsNothing);
      expect(calls, 2);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }
  testWidgets(
    'notification double tap sends one read request and opens one working thread',
    (tester) async {
      final pending = Completer<http.Response>();
      var reads = 0;
      final item = notification('chat-9-2', conversationId: 9);
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'POST') {
            reads++;
            return pending.future;
          }
          if (r.url.path.endsWith('/messages'))
            return json({
              'messages': [message(2, 'Notification reply')],
            });
          return json({
            'notifications': [item],
            'unread_count': 1,
          });
        }),
      );
      await tester.pumpWidget(app(NotificationsScreen(api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Fixture notification chat-9-2'));
      await tester.tap(find.text('Fixture notification chat-9-2'));
      expect(reads, 1);
      pending.complete(json({}));
      await tester.pumpAndSettle();
      expect(find.text('Notification reply'), findsOneWidget);
      expect(find.byType(ChatThreadScreen), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'order notification opens its order detail without selecting a fake tab',
    (tester) async {
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'POST') return json({});
          if (r.url.path.endsWith('/orders/42'))
            return json({
              'order': {
                'id': 42,
                'status': 'delivered',
                'total_amount': 100,
                'created_at': '2026-10-28T12:00:00Z',
                'items': [],
                'returns': [],
                'deliveries': [],
                'can_cancel': false,
              },
            });
          return json({
            'notifications': [notification('order-1', orderId: 42)],
            'unread_count': 1,
          });
        }),
      );
      await tester.pumpWidget(app(NotificationsScreen(api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Fixture notification order-1'));
      await tester.pumpAndSettle();
      expect(find.byType(OrderDetailScreen), findsOneWidget);
      expect(find.text('Order #42'), findsWidgets);
      expect(find.byType(CupertinoTabBar), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'mark all read is single flight and refreshes the home notification badge',
    (tester) async {
      ApiService.setToken('fixture-token');
      final pending = Completer<http.Response>();
      var marked = false, writes = 0;
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'POST') {
            writes++;
            await pending.future;
            marked = true;
            return json({});
          }
          if (r.url.path.endsWith('/notifications'))
            return json({
              'notifications': [
                {...notification('order-1'), 'read': marked},
              ],
              'unread_count': marked ? 0 : 1,
            });
          if (r.url.path.endsWith('/unread-count'))
            return json({'unread_count': 0});
          return json({});
        }),
      );
      await tester.pumpWidget(
        app(
          MainLayout(
            api: api,
            showSettingsIcon: false,
            child: const Text('Home fixture'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('1'), findsOneWidget);
      await tester.tap(find.byIcon(CupertinoIcons.bell));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Mark all as read'));
      await tester.pump();
      expect(find.text('Updating…'), findsOneWidget);
      expect(writes, 1);
      pending.complete(json({}));
      await tester.pumpAndSettle();
      expect(find.text('1 unread'), findsNothing);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('1'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  test(
    'recent products are refreshed and removed catalog entries cannot return from cache',
    () async {
      await RecentlyViewedService.record({
        'id': 115,
        'name': 'Archived QA fixture',
        'price': 1,
      });
      await RecentlyViewedService.record({
        'id': 7,
        'name': 'Old product name',
        'price': 1,
      });
      final api = ApiService(
        client: MockClient((r) async {
          expect(r.url.queryParameters['ids'], '7,115');
          return json({
            'data': [
              {'id': 7, 'name': 'Current product', 'price': 200},
            ],
          });
        }),
      );
      final available = await RecentlyViewedService.available(api);
      expect(available.map((p) => p['id']), [7]);
      expect(available.single['price'], 200);
      expect((await RecentlyViewedService.load()).map((p) => p['id']), [7]);
    },
  );
  testWidgets(
    'support form preserves a failed message and opens the created thread on retry',
    (tester) async {
      var sends = 0;
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'POST') {
            sends++;
            expect(jsonDecode(r.body)['body'], 'Support fixture text');
            return sends == 1
                ? json({'message': 'Please retry.'}, 503)
                : json({
                    'conversation': {'id': 9, 'subject': 'Support inquiry'},
                  }, 201);
          }
          return json({'conversations': [], 'messages': []});
        }),
      );
      await tester.pumpWidget(app(ChatScreen(api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Start a Conversation'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(BuyerField).last,
        'Support fixture text',
      );
      await tester.ensureVisible(find.text('Send'));
      await tester.tap(find.text('Send'));
      await tester.pumpAndSettle();
      expect(find.text('Please retry.'), findsOneWidget);
      expect(
        tester
            .widget<BuyerField>(find.byType(BuyerField).last)
            .controller!
            .text,
        'Support fixture text',
      );
      await tester.tap(find.text('Send'));
      await tester.pumpAndSettle();
      expect(find.byType(ChatThreadScreen), findsOneWidget);
      expect(sends, 2);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'favorites tolerate missing products and prevent duplicate remove requests',
    (tester) async {
      var writes = 0;
      final pending = Completer<http.Response>();
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'POST') {
            writes++;
            return pending.future;
          }
          return json({
            'favorites': [
              {'id': 1, 'product': null},
              {
                'id': 2,
                'product': {
                  'id': 7,
                  'name': 'Favorite fixture',
                  'price': 100,
                  'stock': 5,
                },
              },
            ],
          });
        }),
      );
      await tester.pumpWidget(app(WishlistScreen(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('Favorite fixture'), findsOneWidget);
      final heart = find.byIcon(CupertinoIcons.heart_fill);
      await tester.tap(heart);
      await tester.tap(heart);
      expect(writes, 1);
      pending.complete(json({'is_favorited': false}));
      await tester.pumpAndSettle();
      expect(find.text('Your wishlist is empty'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
