import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/catalog_browser.dart';
import '../widgets/main_layout.dart';

class ProductListScreen extends StatelessWidget {
  final int? categoryId;
  final String? categoryName;
  final String? search;
  final bool showAll;
  final ApiService? api;
  const ProductListScreen({
    super.key,
    this.categoryId,
    this.categoryName,
    this.search,
    this.showAll = false,
    this.api,
  });
  @override
  Widget build(BuildContext context) => MainLayout(
    api: api,
    title: categoryName ?? (showAll ? 'Shop' : 'Sale'),
    currentIndex: 1,
    showSettingsIcon: false,
    child: CatalogBrowser(
      api: api,
      onSale: !showAll,
      categoryId: categoryId,
      initialSearch: search,
    ),
  );
}
