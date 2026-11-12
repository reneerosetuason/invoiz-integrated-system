import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/chat_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _SignedIn extends AuthService {
  @override
  bool get isLoggedIn => true;
}

void main() {
  testWidgets(
    'same numeric IDs in marketplace and logistics open the correct private thread',
    (tester) async {
      SharedPreferences.setMockInitialValues({});
      final requests = <String>[];
      final api = ApiService(
        client: MockClient((request) async {
          requests.add('${request.method} ${request.url.path}');
          Object data;
          if (request.url.path.endsWith('/messages-center/inbox')) {
            data = {
              'conversations': [
                {
                  'id': 7,
                  'thread_key': 'marketplace:7',
                  'name': 'Store contact',
                  'unread': 0,
                  'can_send': true,
                },
                {
                  'id': 7,
                  'thread_key': 'logistics:7',
                  'name': 'Assigned delivery rider',
                  'unread': 1,
                  'can_send': true,
                },
              ],
            };
          } else if (request.url.path.endsWith(
            '/messages-center/threads/logistics:7',
          )) {
            data = {
              'messages': [
                {
                  'id': 12,
                  'conversation_id': 7,
                  'sender_id': 9,
                  'body': 'Arriving soon',
                  'created_at': '2026-11-12T10:00:00Z',
                  'is_read': true,
                },
              ],
              'pagination': {'current_page': 1, 'last_page': 1},
            };
          } else {
            data = <String, dynamic>{};
          }
          return http.Response(
            jsonEncode(data),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      await tester.pumpWidget(
        AuthServiceProvider(
          authService: _SignedIn(),
          child: MaterialApp(home: ChatScreen(api: api)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Store contact'), findsOneWidget);
      await tester.tap(find.text('Assigned delivery rider'));
      await tester.pumpAndSettle();
      expect(find.text('Arriving soon'), findsOneWidget);
      expect(
        requests,
        contains('GET /api/messages-center/threads/logistics:7'),
      );
      expect(requests, isNot(contains('GET /api/conversations/7/messages')));
      await tester.pumpWidget(const SizedBox());
    },
  );
}
