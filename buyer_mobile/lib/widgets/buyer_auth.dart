import 'package:flutter/material.dart';
import '../theme.dart';
import 'buyer_page.dart';
import 'invoiz_logo.dart';

class BuyerAuthPage extends StatelessWidget {
  final String title, description;
  final Widget child;
  final Widget? footer;
  const BuyerAuthPage({
    super.key,
    required this.title,
    required this.description,
    required this.child,
    this.footer,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.paper,
    appBar: AppBar(
      title: Row(
        children: [
          InvoizLogo.logoWidget(size: 28, radius: 8),
          const SizedBox(width: 10),
          const Text('INVOIZ'),
        ],
      ),
    ),
    body: SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 28, 20, 32),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                BuyerPageHeading(title: title, description: description),
                BuyerPanel(child: child),
                if (footer != null) ...[const SizedBox(height: 18), footer!],
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
