import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:invoiz/screens/splash_screen.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/widgets/invoiz_logo.dart';

void main() {
  testWidgets('App boots to splash screen', (WidgetTester tester) async {
    final auth = AuthService();
    await tester.pumpWidget(
      AuthServiceProvider(
        authService: auth,
        child: const MaterialApp(home: SplashScreen()),
      ),
    );
    expect(find.byType(Hero), findsOneWidget);
    final logo = tester.widget<Image>(find.byType(Image));
    expect((logo.image as AssetImage).assetName, InvoizLogo.assetPath);

    // Leaving the splash must not navigate after its delayed callback fires.
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 1500));
    await tester.pumpAndSettle();
  });
}
