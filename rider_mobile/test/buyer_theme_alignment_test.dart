// ignore_for_file: avoid_relative_lib_imports
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../lib/core/theme/app_theme.dart';
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/auth/login_screen.dart';

void main() {
  setUpAll(() async {
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    for (final family in ['Inter', 'Fraunces']) {
      await (FontLoader(
        family,
      )..addFont(rootBundle.load('assets/fonts/$family.ttf'))).load();
    }
  });

  for (final width in [320.0, 390.0, 768.0]) {
    testWidgets(
      'themed sign-in remains usable at ${width.toInt()}px with larger text and keyboard',
      (tester) async {
        tester.view.physicalSize = Size(width, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        addTearDown(tester.view.resetViewInsets);
        SharedPreferences.setMockInitialValues({});
        final auth = AuthProvider(api: ApiClient(), storage: TokenStorage());
        addTearDown(auth.dispose);
        await tester.pumpWidget(
          ChangeNotifierProvider.value(
            value: auth,
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: AppTheme.light(),
              home: const LoginScreen(),
            ),
          ),
        );
        await tester.pumpAndSettle();
        if (const bool.fromEnvironment('RIDER_THEME_PREVIEW')) {
          await expectLater(
            find.byType(MaterialApp),
            matchesGoldenFile('../../.tools/rider-login-${width.toInt()}.png'),
          );
        }
        // Larger system text and an open keyboard must never strand the submit action.
        await tester.pumpWidget(
          ChangeNotifierProvider.value(
            value: auth,
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: AppTheme.light(),
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(
                  context,
                ).copyWith(textScaler: const TextScaler.linear(1.4)),
                child: child!,
              ),
              home: const LoginScreen(),
            ),
          ),
        );
        tester.view.viewInsets = const FakeViewPadding(bottom: 280);
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.text('Sign In'));
        await tester.tap(find.text('Sign In'));
        await tester.pumpAndSettle();
        expect(find.text('Please enter your email.'), findsOneWidget);
        expect(find.text('Please enter your password.'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
