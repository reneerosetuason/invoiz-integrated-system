import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';
import 'app_ui.dart';

/// Friendly error state with a retry button.
class ErrorView extends StatelessWidget {
  const ErrorView({
    super.key,
    required this.message,
    this.onRetry,
  });

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                color: AppTheme.danger.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: const Icon(
                Icons.cloud_off_outlined,
                size: 34,
                color: AppTheme.danger,
              ),
            ),
            const SizedBox(height: AppGaps.lg),
            Text(
              message,
              textAlign: TextAlign.center,
              style: AppText.body.copyWith(fontSize: 15),
            ),
            if (onRetry != null) ...[
              const SizedBox(height: AppGaps.xl),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Try Again'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(160, 44),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Empty state shown when there is nothing to display.
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return EmptyView(
      icon: icon,
      title: title,
      message: message,
      action: action,
    );
  }
}
