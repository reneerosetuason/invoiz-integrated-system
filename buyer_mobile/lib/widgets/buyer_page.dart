import 'package:flutter/material.dart';
import '../theme.dart';

/// Shared page furniture follows the buyer website's paper, serif headings and hairlines.
class BuyerPageHeading extends StatelessWidget {
  final String title;
  final String? description;
  const BuyerPageHeading({super.key, required this.title, this.description});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontSize: 24),
        ),
        if (description != null) ...[
          const SizedBox(height: 4),
          Text(description!, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ],
    ),
  );
}

class BuyerPanel extends StatelessWidget {
  final String? title, description;
  final Widget child;
  final EdgeInsetsGeometry padding;
  const BuyerPanel({
    super.key,
    this.title,
    this.description,
    required this.child,
    this.padding = const EdgeInsets.all(12),
  });
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: padding,
    decoration: BoxDecoration(
      color: AppColors.surface,
      border: Border.all(color: AppColors.border, width: .5),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (title != null) ...[
          Text(
            title!,
            style: TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 20,
              fontWeight: FontWeight.w600,
              color: AppColors.primaryDark,
            ),
          ),
          SizedBox(height: description == null ? 8 : 4),
        ],
        if (description != null) ...[
          Text(description!, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 8),
        ],
        child,
      ],
    ),
  );
}

class BuyerEmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? description;
  final Widget? action;
  const BuyerEmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.description,
    this.action,
  });
  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 360),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 60,
              height: 60,
              decoration: const BoxDecoration(
                color: AppColors.paleTeal,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 27, color: AppColors.primary),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontFamily: 'Fraunces',
                fontSize: 23,
                fontWeight: FontWeight.w600,
                color: AppColors.primaryDark,
              ),
            ),
            if (description != null) ...[
              const SizedBox(height: 8),
              Text(description!, textAlign: TextAlign.center),
            ],
            if (action != null) ...[const SizedBox(height: 12), action!],
          ],
        ),
      ),
    ),
  );
}

class BuyerStatusChip extends StatelessWidget {
  final String status;
  final String? label;
  const BuyerStatusChip({super.key, required this.status, this.label});
  @override
  Widget build(BuildContext context) {
    final danger = ['cancelled', 'rejected', 'failed'].contains(status);
    final color = danger ? AppColors.danger : AppColors.primaryDark;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
      decoration: BoxDecoration(
        color: danger ? AppColors.errorTint : AppColors.paleTeal,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label ?? status.replaceAll('_', ' '),
        style: TextStyle(
          fontFamily: 'Inter',
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}
