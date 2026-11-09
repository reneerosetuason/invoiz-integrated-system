import 'package:flutter/material.dart';
import '../theme.dart';
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
    backgroundColor: AppColors.background,
    title: 'Sign in',
    showNavigationBar: Navigator.canPop(context),
    child: SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                BuyerAuthIntro(title: title, description: description),
                const SizedBox(height: 24),
                BuyerAuthPanel(child: child),
                if (footer != null) ...[const SizedBox(height: 18), footer!],
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class BuyerAuthPanel extends StatelessWidget {
  final String? title;
  final Widget child;
  const BuyerAuthPanel({super.key, this.title, required this.child});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AppColors.border),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: .02),
          blurRadius: 8,
          offset: const Offset(0, 4),
        ),
      ],
    ),
    child: Material(
      type: MaterialType.transparency,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null) ...[
            Text(
              title!,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 17,
                fontWeight: FontWeight.w600,
                color: AppColors.primaryDark,
              ),
            ),
            const SizedBox(height: 18),
          ],
          child,
        ],
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
          InvoizLogo.logoWidget(size: 54, radius: 16),
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
                  'BUYER CENTER',
                  style: TextStyle(
                    fontSize: 10,
                    letterSpacing: 1.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      const SizedBox(height: 24),
      Text(
        title,
        style: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 30,
          fontWeight: FontWeight.w600,
          height: 1.12,
          color: AppColors.primaryDark,
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
      if (registering) ...[
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surfaceTint,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text(
            '1. Your details   /   2. Verify email   /   3. Account review',
            style: const TextStyle(
              fontSize: 12,
              height: 1.5,
              color: AppColors.primaryDark,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    ],
  );
}
