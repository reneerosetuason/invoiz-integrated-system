import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';

Future<bool> confirmFailedParcelHandover(BuildContext context) async {
  return await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Confirm hub handover'),
          content: const Text(
            'Confirm only after you have physically handed the parcel back to '
            'the destination Logistics Hub. It will wait there for logistics '
            'staff to arrange the next step. After three failed attempts, '
            'the seller chooses a funded return or unclaimed storage.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Not yet'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirm handover'),
            ),
          ],
        ),
      ) ??
      false;
}

/// The third failed attempt requires seller resolution after physical hub return.
class FailedDeliveryHubReturn extends StatelessWidget {
  const FailedDeliveryHubReturn({
    super.key,
    required this.busy,
    required this.returned,
    required this.onReturn,
    this.centerName,
    this.failedAttempts = 0,
  });

  final bool busy;
  final bool returned;
  final VoidCallback onReturn;
  final String? centerName;
  final int failedAttempts;

  @override
  Widget build(BuildContext context) {
    final center = centerName?.trim();
    final exhausted = failedAttempts >= 3;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppGaps.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      returned
                          ? Icons.check_circle_outline
                          : Icons.warehouse_outlined,
                      color: returned ? AppTheme.success : AppTheme.primary,
                    ),
                    const SizedBox(width: AppGaps.md),
                    Expanded(
                      child: Text(
                        returned
                            ? 'Waiting at destination hub'
                            : 'Return to destination hub',
                        style: AppText.body,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppGaps.md),
                Text(
                  'Failed attempts: $failedAttempts / 3',
                  style: AppText.body,
                ),
                const SizedBox(height: AppGaps.md),
                Text(
                  exhausted
                      ? 'Three delivery attempts have failed. Return the parcel to the destination Logistics Hub if you have not handed it over yet. '
                            'The seller must choose return to seller or unclaimed storage. No further buyer delivery attempt can be assigned. '
                            'Completed rider and logistics services remain payable.'
                      : returned
                      ? 'The parcel is at ${center != null && center.isNotEmpty ? center : 'the destination Logistics Hub'}. '
                            'Waiting for logistics staff to assign a rider for another delivery attempt.'
                      : 'Bring the parcel back to the destination Logistics Hub. '
                            'It will stay there until logistics staff assigns an eligible rider '
                            'for another delivery attempt to the buyer.',
                  style: AppText.secondary,
                ),
              ],
            ),
          ),
        ),
        if (!returned) ...[
          const SizedBox(height: AppGaps.md),
          OutlinedButton.icon(
            onPressed: busy ? null : onReturn,
            icon: const Icon(Icons.warehouse_outlined),
            label: Text(
              busy ? 'Working...' : 'Confirm return to destination hub',
            ),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(50),
            ),
          ),
        ],
      ],
    );
  }
}
