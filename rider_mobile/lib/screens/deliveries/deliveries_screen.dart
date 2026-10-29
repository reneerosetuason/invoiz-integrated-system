import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/delivery_provider.dart';
import '../../widgets/delivery_card.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/stagger_in.dart';
import '../scan/scan_parcel_screen.dart';
import 'delivery_detail_screen.dart';

/// Delivery list with status filter chips.
class DeliveriesScreen extends StatefulWidget {
  const DeliveriesScreen({super.key});

  @override
  State<DeliveriesScreen> createState() => _DeliveriesScreenState();
}

class _DeliveriesScreenState extends State<DeliveriesScreen> {
  @override
  Widget build(BuildContext context) {
    final provider = context.watch<DeliveryProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Deliveries'),
        // Back arrow only when this screen was pushed (e.g. from the
        // dashboard); the bottom-nav tab instance stays root-level.
        automaticallyImplyLeading: Navigator.of(context).canPop(),
        actions: [
          IconButton(
            tooltip: 'Scan Parcel',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ScanParcelScreen()),
            ),
            icon: const Icon(Icons.qr_code_scanner_outlined),
          ),
        ],
      ),
      body: Column(
        children: [
          _FilterBar(
            current: provider.filter,
            onChanged: (f) => context.read<DeliveryProvider>().setFilter(f),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: provider.load,
              child: _buildList(provider),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildList(DeliveryProvider provider) {
    if (provider.loading && provider.deliveries.isEmpty) {
      return const LoadingWidget(label: 'Loading deliveries...');
    }

    if (provider.error != null && provider.deliveries.isEmpty) {
      return ErrorView(message: provider.error!, onRetry: provider.load);
    }

    if (provider.deliveries.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 80),
          StaggerIn(
            child: EmptyState(
              icon: Icons.inventory_2_outlined,
              title: 'No deliveries here',
              message: 'New assignments will appear here.',
            ),
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: provider.deliveries.length,
      itemBuilder: (context, index) {
        final delivery = provider.deliveries[index];
        return DeliveryCard(
          delivery: delivery,
          staggerIndex: index,
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => DeliveryDetailScreen(deliveryId: delivery.id),
              ),
            );
          },
          trailing: const Icon(
            Icons.chevron_right,
            color: AppColors.textSecondary,
          ),
        );
      },
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({required this.current, required this.onChanged});

  final String current;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 58,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        children: DeliveryProvider.filters.entries.map((entry) {
          final selected = entry.key == current;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text(entry.value),
              selected: selected,
              onSelected: (_) => onChanged(entry.key),
              selectedColor: AppTheme.primary,
              labelStyle: TextStyle(
                color: selected ? Colors.white : AppColors.textSecondary,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}
