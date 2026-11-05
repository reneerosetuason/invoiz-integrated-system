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
    title: 'Sign in',
    showNavigationBar: Navigator.canPop(context),
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
                BuyerAuthIntro(title: title, description: description),
                const SizedBox(height: 24),
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

/// Shared identity for the native sign-in and registration screens.
class BuyerAuthIntro extends StatelessWidget {
  final String title, description;
  final bool registering;
  const BuyerAuthIntro({
    super.key,
    required this.title,
    required this.description,
    this.registering = false,
  });

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Row(
        children: [
          InvoizLogo.logoWidget(size: 48, radius: 14),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'INVOIZ',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 21,
                    color: AppColors.primaryDark,
                  ),
                ),
                Text(
                  "Life's short. Shop fast.",
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      const SizedBox(height: 28),
      Text(
        title,
        style: const TextStyle(
          fontFamily: 'Fraunces',
          fontSize: 34,
          height: 1.12,
          color: AppColors.textPrimary,
        ),
      ),
      const SizedBox(height: 10),
      Text(
        description,
        style: const TextStyle(
          fontSize: 14,
          height: 1.5,
          color: AppColors.textSecondary,
        ),
      ),
      const SizedBox(height: 18),
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surfaceTint,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Text(
          registering
              ? 'Your details  →  Verify email  →  Account review'
              : 'Your favorites, conversations and deliveries. All together.',
          style: const TextStyle(
            fontSize: 12,
            height: 1.5,
            color: AppColors.primaryDark,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    ],
  );
}
