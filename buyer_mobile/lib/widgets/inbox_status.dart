import 'package:flutter/cupertino.dart';
import 'buyer_controls.dart';
import 'package:flutter/material.dart';

/// Gives inbox failures a visible recovery action instead of a false empty state.
class InboxStatus extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const InboxStatus({super.key, required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(CupertinoIcons.cloud, size: 40),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          BuyerButton.primary(
            onPressed: onRetry,
            icon: const Icon(CupertinoIcons.refresh),
            label: const Text('Try again'),
          ),
        ],
      ),
    ),
  );
}
