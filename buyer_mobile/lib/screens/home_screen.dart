import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/catalog_browser.dart';
import '../widgets/main_layout.dart';

class HomeScreen extends StatelessWidget {
  final ApiService? api;
  const HomeScreen({super.key, this.api});
  @override
  Widget build(BuildContext context) => CatalogBrowser(
    api: api,
    showIntro: true,
    bottomPadding: AppSizes.bottomNavClearance(context),
    scaffoldBuilder: (context, search, content) => MainLayout(
      api: api,
      title: 'INVOIZ',
      currentIndex: 0,
      showSettingsIcon: false,
      headerSearch: search,
      child: content,
    ),
  );
}
