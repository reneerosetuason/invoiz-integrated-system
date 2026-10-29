import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../models/remittance.dart';
import '../../providers/remittance_provider.dart';
import '../../widgets/dashboard_card.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';

/// Cash-on-Delivery collections the rider must hand over to logistics.
class RemittancesScreen extends StatefulWidget {
  const RemittancesScreen({super.key});

  @override
  State<RemittancesScreen> createState() => _RemittancesScreenState();
}

class _RemittancesScreenState extends State<RemittancesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RemittanceProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RemittanceProvider>();
    final page = provider.page;

    return Scaffold(
      appBar: AppBar(title: const Text('Cash Remittances')),
      body: RefreshIndicator(
        onRefresh: provider.load,
        child: provider.loading && page == null
            ? const LoadingWidget(label: 'Loading remittances...')
            : provider.error != null && page == null
                ? ErrorView(
                    message: provider.error!,
                    onRetry: provider.load,
                  )
                : _buildContent(context, provider, page),
      ),
    );
  }

  Widget _buildContent(
    BuildContext context,
    RemittanceProvider provider,
    RemittancePage? page,
  ) {
    if (page == null) return const LoadingWidget();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(
              child: DashboardCard(
                label: 'To Remit',
                value: FormatUtils.peso(page.summary.totalDue),
                icon: Icons.account_balance_outlined,
                color: AppTheme.primary,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: DashboardCard(
                label: 'Confirmed',
                value: FormatUtils.peso(page.summary.totalConfirmed),
                icon: Icons.verified_outlined,
                color: AppTheme.success,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Text(
          'COD collections are confirmed by logistics staff after handover. '
          'Mark a remittance as sent once you have turned over the cash.',
          style: AppText.secondary.copyWith(fontSize: 12),
        ),
        const SizedBox(height: 16),
        if (page.remittances.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'No cash remittances yet.',
                  style: TextStyle(color: AppColors.textSecondary),
                ),
              ),
            ),
          )
        else
          ...page.remittances.map(
            (remittance) => _RemittanceCard(
              remittance: remittance,
              busy: provider.actionBusy,
              onMarkSent: () => _markSent(context, provider, remittance),
            ),
          ),
      ],
    );
  }

  Future<void> _markSent(
    BuildContext context,
    RemittanceProvider provider,
    Remittance remittance,
  ) async {
    var reference = '';

    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Mark as Sent'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Confirm that you handed over '
              '${FormatUtils.peso(remittance.amount)} to logistics.',
            ),
            const SizedBox(height: 12),
            TextField(
              onChanged: (value) => reference = value,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(
                labelText: 'Reference number (optional)',
                hintText: 'e.g. GC-12345',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(reference.trim()),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );

    if (result == null || !context.mounted) return;

    final ok = await provider.markSent(
      remittance.id,
      reference: result.isEmpty ? null : result,
    );

    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Remittance marked as sent.'
              : provider.error ?? 'Unable to update the remittance.',
        ),
      ),
    );
  }
}

class _RemittanceCard extends StatelessWidget {
  const _RemittanceCard({
    required this.remittance,
    required this.busy,
    required this.onMarkSent,
  });

  final Remittance remittance;
  final bool busy;
  final VoidCallback onMarkSent;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(AppGaps.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        FormatUtils.peso(remittance.amount),
                        style: AppText.kpi(22),
                      ),
                      const SizedBox(height: 4),
                      if (remittance.trackingNumber != null)
                        Text(
                          'Tracking ${remittance.trackingNumber}',
                          style: AppText.secondary.copyWith(fontSize: 12),
                        ),
                      Text(
                        'Order #${remittance.orderId}',
                        style: AppText.secondary.copyWith(fontSize: 12),
                      ),
                    ],
                  ),
                ),
                _StatusChip(status: remittance.status),
              ],
            ),
            const SizedBox(height: 10),
            if (remittance.reference != null &&
                remittance.reference!.isNotEmpty)
              Text(
                'Reference: ${remittance.reference}',
                style: AppText.secondary.copyWith(fontSize: 12),
              ),
            if (remittance.sentAt != null)
              Text(
                'Sent ${FormatUtils.date(remittance.sentAt!)}',
                style: AppText.secondary.copyWith(fontSize: 12),
              ),
            if (remittance.confirmedAt != null)
              Text(
                'Confirmed ${FormatUtils.date(remittance.confirmedAt!)}',
                style: AppText.secondary.copyWith(fontSize: 12),
              ),
            if (remittance.isPending) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: FilledButton.tonalIcon(
                  onPressed: busy ? null : onMarkSent,
                  icon: const Icon(Icons.send_outlined, size: 18),
                  label: const Text('Mark as Sent'),
                ),
              ),
            ] else if (remittance.isSent) ...[
              const SizedBox(height: 8),
              Text(
                'Waiting for logistics to confirm receipt.',
                style: AppText.secondary.copyWith(fontSize: 12),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (label, color, background) = switch (status) {
      'confirmed' => ('Confirmed', AppTheme.success, const Color(0xFFE8F5E9)),
      'sent' => ('Sent', const Color(0xFF1D6FE0), const Color(0xFFE7F0FF)),
      _ => ('Pending', const Color(0xFFB26A00), const Color(0xFFFFF4E5)),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
