import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/catalog_browser.dart';
import '../widgets/main_layout.dart';

class HomeScreen extends StatelessWidget {
  final ApiService? api;
  const HomeScreen({super.key, this.api});
  @override
  Widget build(BuildContext context) => MainLayout(
    api: api,
    title: 'INVOIZ',
    currentIndex: 0,
    showSettingsIcon: false,
    child: CatalogBrowser(api: api, showIntro: true),
  );
}
