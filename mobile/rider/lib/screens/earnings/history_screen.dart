import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../models/earning.dart';
import '../../providers/earnings_provider.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../deliveries/delivery_detail_screen.dart';

/// Delivery history with search + status filter.
class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  final _searchController = TextEditingController();
  String _status = '';
  DateTimeRange? _range;

  static String? _apiDate(DateTime? date) =>
      date?.toIso8601String().split('T').first;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EarningsProvider>().loadHistory();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _reload(EarningsProvider provider) async {
    await provider.loadHistory(
      search: _searchController.text.trim(),
      status: _status,
      from: _apiDate(_range?.start),
      to: _apiDate(_range?.end),
    );
  }

  Future<void> _pickRange(EarningsProvider provider) async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 3),
      lastDate: now,
      initialDateRange: _range,
      helpText: 'Filter delivery history by date',
    );
    if (picked == null || !mounted) return;
    setState(() => _range = picked);
    await _reload(provider);
  }

  String get _rangeLabel {
    final range = _range;
    if (range == null) return 'Date';
    String short(DateTime d) =>
        '${d.year}-${d.month.toString().padLeft(2, '0')}-'
        '${d.day.toString().padLeft(2, '0')}';
    return '${short(range.start)} – ${short(range.end)}';
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<EarningsProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Delivery History')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: TextField(
              controller: _searchController,
              onSubmitted: (_) => _reload(provider),
              decoration: InputDecoration(
                hintText: 'Search by tracking no., shop, customer...',
                filled: true,
                fillColor: AppColors.surfaceSoft,
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.clear),
                  onPressed: () {
                    _searchController.clear();
                    _reload(provider);
                  },
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),
          SizedBox(
            height: 56,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              children: [
                _FilterChip(
                  label: 'All',
                  selected: _status == '',
                  onPressed: () {
                    setState(() => _status = '');
                    _reload(provider);
                  },
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: 'Delivered',
                  selected: _status == 'delivered',
                  onPressed: () {
                    setState(() => _status = 'delivered');
                    _reload(provider);
                  },
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: 'Failed',
                  selected: _status == 'failed',
                  onPressed: () {
                    setState(() => _status = 'failed');
                    _reload(provider);
                  },
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: 'Lost',
                  selected: _status == 'lost',
                  onPressed: () {
                    setState(() => _status = 'lost');
                    _reload(provider);
                  },
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: 'Damaged',
                  selected: _status == 'damaged',
                  onPressed: () {
                    setState(() => _status = 'damaged');
                    _reload(provider);
                  },
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: _rangeLabel,
                  selected: _range != null,
                  icon: Icons.date_range_outlined,
                  onPressed: () => _pickRange(provider),
                ),
                if (_range != null) ...[
                  const SizedBox(width: 8),
                  _FilterChip(
                    label: 'Clear dates',
                    selected: false,
                    onPressed: () {
                      setState(() => _range = null);
                      _reload(provider);
                    },
                  ),
                ],
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _reload(provider),
              child: _buildList(provider),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildList(EarningsProvider provider) {
    if (provider.historyLoading && provider.history.isEmpty) {
      return const LoadingWidget(label: 'Loading history...');
    }

    if (provider.error != null && provider.history.isEmpty) {
      return ErrorView(
        message: provider.error!,
        onRetry: () => _reload(provider),
      );
    }

    if (provider.history.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 80),
          EmptyState(
            icon: Icons.history,
            title: 'No delivery history',
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: provider.history.length,
      itemBuilder: (context, index) {
        final entry = provider.history[index];
        return _HistoryCard(
          entry: entry,
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => DeliveryDetailScreen(deliveryId: entry.id),
            ),
          ),
        );
      },
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onPressed,
    this.icon,
  });

  final String label;
  final bool selected;
  final VoidCallback onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      avatar: icon == null
          ? null
          : Icon(
              icon,
              size: 16,
              color: selected ? Colors.white : AppColors.textSecondary,
            ),
      label: Text(label),
      selected: selected,
      onSelected: (_) => onPressed(),
      selectedColor: AppTheme.primary,
      labelStyle: TextStyle(
        color: selected ? Colors.white : AppColors.textSecondary,
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.entry, this.onTap});

  final HistoryEntry entry;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final isDelivered = entry.status == 'delivered';
    final isLostOrDamaged =
        entry.status == 'lost' || entry.status == 'damaged';
    final color = isDelivered
        ? AppColors.success
        : isLostOrDamaged
        ? AppTheme.danger
        : AppColors.warning;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      entry.trackingNumber,
                      style: AppText.tracking.copyWith(fontSize: 14),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      entry.statusLabel,
                      style: TextStyle(
                        color: color,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                '${entry.shopName} → ${entry.customerName}',
                style: AppText.secondary.copyWith(fontSize: 13),
              ),
              const SizedBox(height: 4),
              Text(
                FormatUtils.dateTime(entry.deliveredAt),
                style: AppText.caption.copyWith(fontSize: 12),
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    _paymentLabel(entry.paymentMethod),
                    style: const TextStyle(
                        fontSize: 12, color: AppColors.textSecondary),
                  ),
                  if (entry.earned != null)
                    Text(
                      'Earned ${FormatUtils.peso(entry.earned!)}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                        color: AppTheme.primary,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 4),
              const Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Icon(
                    Icons.chevron_right,
                    size: 18,
                    color: AppColors.textSecondary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _paymentLabel(String? method) {
    switch (method) {
      case 'cash_on_delivery':
        return 'COD';
      case 'gcash':
        return 'GCash';
      case 'bank_transfer':
        return 'Bank Transfer';
      default:
        return method ?? '—';
    }
  }
}