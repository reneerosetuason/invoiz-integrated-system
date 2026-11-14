import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/chat_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _ChatAuth extends AuthService {
  @override
  bool get isLoggedIn => true;
}

void main() {
  testWidgets('an open parcel chat becomes read-only after handler handoff', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    var canSend = true;
    final api = ApiService(
      client: MockClient((request) async {
        return http.Response(
          jsonEncode({
            'messages': <Object>[],
            'can_send': canSend,
            'pagination': {'current_page': 1, 'last_page': 1},
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    await tester.pumpWidget(
      AuthServiceProvider(
        authService: _ChatAuth(),
        child: MaterialApp(
          theme: AppTheme.light,
          home: ChatThreadScreen(
            conversationId: 42,
            threadKey: 'logistics:42',
            subject: 'Parcel chat',
            api: api,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      tester.widget<EditableText>(find.byType(EditableText)).readOnly,
      isFalse,
    );
    canSend = false;
    await tester.pump(const Duration(seconds: 11));
    await tester.pumpAndSettle();
    expect(
      tester.widget<EditableText>(find.byType(EditableText)).readOnly,
      isTrue,
    );
    await tester.pumpWidget(const SizedBox.shrink());
  });
}
