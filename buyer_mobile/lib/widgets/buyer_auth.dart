import 'package:flutter/material.dart';
import '../theme.dart';
import 'buyer_page.dart';
import 'invoiz_logo.dart';
import 'buyer_scaffold.dart';

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
  Widget build(BuildContext context) => BuyerScaffold(
    backgroundColor: AppColors.paper,
    title: 'INVOIZ',
    leading: Navigator.canPop(context)
        ? null
        : InvoizLogo.logoWidget(size: 26, radius: 8),
    child: SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
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
