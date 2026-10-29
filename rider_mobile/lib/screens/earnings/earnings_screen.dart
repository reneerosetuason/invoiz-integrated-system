import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../providers/earnings_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/dashboard_card.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../remittances/remittances_screen.dart';
import 'history_screen.dart';

/// Rider earnings dashboard (daily / weekly / monthly).
class EarningsScreen extends StatelessWidget {
  const EarningsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<EarningsProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Earnings'),
      ),
      body: RefreshIndicator(
        onRefresh: provider.loadSummary,
        child: provider.loading && provider.summary == null
            ? const LoadingWidget(label: 'Loading earnings...')
            : provider.error != null && provider.summary == null
                ? ErrorView(
                    message: provider.error!,
                    onRetry: provider.loadSummary,
                  )
                : _buildContent(context, provider),
      ),
    );
  }

  Widget _buildContent(BuildContext context, EarningsProvider provider) {
    final summary = provider.summary;
    if (summary == null) return const LoadingWidget();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          padding: const EdgeInsets.all(AppGaps.xl),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppTheme.primary, AppTheme.primaryDark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(AppRadius.lg),
            boxShadow: [
              BoxShadow(
                color: AppTheme.primary.withValues(alpha: 0.3),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Row(
                children: [
                  Icon(
                    Icons.account_balance_wallet_outlined,
                    color: Colors.white70,
                    size: 14,
                  ),
                  SizedBox(width: 6),
                  Text(
                    "Today's Earnings",
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                FormatUtils.peso(summary.today),
                style: AppText.kpi(34).copyWith(color: Colors.white),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: DashboardCard(
                label: 'This Week',
                value: FormatUtils.peso(summary.thisWeek),
                icon: Icons.date_range_outlined,
                color: const Color(0xFF1D6FE0),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: DashboardCard(
                label: 'This Month',
                value: FormatUtils.peso(summary.thisMonth),
                icon: Icons.calendar_month_outlined,
                color: AppColors.secondary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Card(
          child: ListTile(
            leading: const IconTile(
              icon: Icons.account_balance_wallet_outlined,
              size: 42,
              iconSize: 20,
            ),
            title: const Text('Cash Remittances'),
            subtitle: const Text('Cash-on-Delivery collections to hand over'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const RemittancesScreen(),
                ),
              );
            },
          ),
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            const Expanded(
              child: Text('Last 30 Days', style: AppText.section),
            ),
            TextButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const HistoryScreen(),
                  ),
                );
              },
              child: const Text('View History'),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (summary.history.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'No earnings recorded yet.',
                  style: TextStyle(color: AppColors.textSecondary),
                ),
              ),
            ),
          )
        else
          ...summary.history.map((day) {
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const IconTile(
                  icon: Icons.savings_outlined,
                  size: 42,
                  iconSize: 20,
                ),
                title: Text(
                  FormatUtils.date(day.date),
                  style: AppText.cardTitle.copyWith(fontSize: 14),
                ),
                subtitle: Text(
                  day.deliveries == 1
                      ? '1 delivery'
                      : '${day.deliveries} deliveries',
                  style: AppText.secondary.copyWith(fontSize: 12),
                ),
                trailing: Text(
                  FormatUtils.peso(day.amount),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.primary,
                  ),
                ),
              ),
            );
          }),
        const SizedBox(height: 24),
      ],
    );
  }
}