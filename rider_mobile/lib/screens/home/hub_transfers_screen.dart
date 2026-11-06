import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/hub_courier.dart';
import '../../providers/auth_provider.dart';
import '../../services/hub_courier_service.dart';
import 'hub_courier_home_screen.dart';

/// Screen listing assigned hub transfer manifests, pending trips, and completed transfers.
class HubTransfersScreen extends StatefulWidget {
  const HubTransfersScreen({super.key});

  @override
  State<HubTransfersScreen> createState() => _HubTransfersScreenState();
}

class _HubTransfersScreenState extends State<HubTransfersScreen> {
  late Future<List<HubTransferJob>> _transfersFuture;
  // 'all', 'active', 'pending', 'ready', 'in_transit', 'completed'
  String _selectedFilter = 'all';

  HubCourierService get _service =>
      HubCourierService(context.read<AuthProvider>().api);

  @override
  void initState() {
    super.initState();
    _transfersFuture = _service.transfers(filter: _selectedFilter);
  }

  /// Refetches from the server with the active filter so the list and the
  /// dashboard counters are always derived from the same lifecycle query.
  Future<void> _load([String? filter]) {
    if (!mounted) return Future<void>.value();
    if (filter != null) {
      _selectedFilter = filter;
    }
    final future = _service.transfers(filter: _selectedFilter);
    setState(() {
      _transfersFuture = future;
    });
    return future.then<void>(
      (_) {},
      onError: (_) {
        // FutureBuilder renders the error state.
      },
    );
  }

  bool inActiveStatus(String status) {
    return [
      'pending',
      'assigned',
      'loading',
      'ready_for_dispatch',
      'in_transit',
      'arrived',
      'receiving',
      'exception',
      'on_hold',
    ].contains(status);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Hub Transfers'),
        automaticallyImplyLeading: false,
      ),
      body: Column(
        children: [
          // Filter Chips
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                _filterChip('all', 'All Transfers'),
                const SizedBox(width: 8),
                _filterChip('pending', 'Pending'),
                const SizedBox(width: 8),
                _filterChip('active', 'Active Trips'),
                const SizedBox(width: 8),
                _filterChip('ready', 'Ready for Dispatch'),
                const SizedBox(width: 8),
                _filterChip('in_transit', 'In Transit'),
                const SizedBox(width: 8),
                _filterChip('completed', 'Completed'),
              ],
            ),
          ),
          const Divider(height: 1),
          // Transfer Manifests List
          Expanded(
            child: FutureBuilder<List<HubTransferJob>>(
              future: _transfersFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.error_outline,
                            size: 48,
                            color: Colors.red,
                          ),
                          const SizedBox(height: 12),
                          const Text('Failed to load transfers'),
                          Text(
                            snapshot.error.toString(),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 12),
                          ElevatedButton(
                            onPressed: _load,
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                final allTransfers = snapshot.data ?? [];

                if (allTransfers.isEmpty) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(32),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.local_shipping_outlined,
                            size: 48,
                            color: Colors.grey,
                          ),
                          const SizedBox(height: 12),
                          Text(
                            _selectedFilter == 'all'
                                ? 'No hub transfer manifests assigned.'
                                : 'No transfers match this filter.',
                            style: const TextStyle(
                              color: Colors.black54,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: allTransfers.length,
                    itemBuilder: (context, index) {
                      final item = allTransfers[index];
                      return _buildTransferCard(item);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _filterChip(String filterKey, String label) {
    final selected = _selectedFilter == filterKey;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (val) {
        if (val && _selectedFilter != filterKey) _load(filterKey);
      },
      selectedColor: const Color(0xFF16697A),
      labelStyle: TextStyle(
        color: selected ? Colors.white : Colors.black87,
        fontWeight: selected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
    );
  }

  Widget _buildTransferCard(HubTransferJob job) {
    final vehicle = job.data['vehicle'] as Map<String, dynamic>? ?? {};
    final isActive = inActiveStatus(job.status);
    final statusColor = _statusColor(job.status);

    return Card(
      elevation: isActive ? 3 : 1,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: isActive
              ? const Color(0xFF16697A).withOpacity(0.5)
              : Colors.grey.shade200,
          width: isActive ? 1.5 : 1,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => HubTransferDetailScreen(transferId: job.id),
          ),
        ).then((_) => _load()),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Manifest Ref & Status Badge
              Wrap(
                spacing: 12,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text(
                    job.reference,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                      color: Color(0xFF0E4A57),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: statusColor.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: statusColor.withOpacity(0.3)),
                    ),
                    child: Text(
                      job.statusLabel.toUpperCase(),
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: statusColor,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // Route: Origin -> Destination
              Row(
                children: [
                  const Icon(
                    Icons.storefront,
                    size: 16,
                    color: Color(0xFF16697A),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      job.origin['name'] ?? 'Origin Hub',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 6),
                    child: Icon(
                      Icons.arrow_forward,
                      size: 14,
                      color: Colors.grey,
                    ),
                  ),
                  const Icon(
                    Icons.warehouse,
                    size: 16,
                    color: Color(0xFF0E4A57),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      job.destination['name'] ?? 'Destination Hub',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
              const Divider(height: 18),

              // Vehicle & Parcel details
              Wrap(
                spacing: 12,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.local_shipping_outlined,
                        size: 15,
                        color: Colors.black54,
                      ),
                      const SizedBox(width: 4),
                      Flexible(
                        child: Text(
                          '${vehicle['plate_number'] ?? 'No vehicle'} · ${vehicle['type'] ?? 'Truck'}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: Colors.black87,
                          ),
                        ),
                      ),
                    ],
                  ),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.inventory_2_outlined,
                        size: 15,
                        color: Colors.black54,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${job.loadedCount}/${job.parcelCount} loaded',
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              if (job.receivedCount > 0 ||
                  job.missingCount > 0 ||
                  job.totalWeightKg != null) ...[
                const SizedBox(height: 6),
                Text(
                  '${job.receivedCount} of ${job.parcelCount} parcels received'
                  '${job.missingCount > 0 ? ' · ${job.missingCount} missing' : ''}'
                  '${job.totalWeightKg == null ? '' : ' · ${job.totalWeightKg!.toStringAsFixed(2)} kg'}',
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                ),
              ],

              if (job.data['planned_dispatch_at'] != null) ...[
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(Icons.schedule, size: 14, color: Colors.black54),
                    const SizedBox(width: 4),
                    Text(
                      'Dispatch: ${job.data['planned_dispatch_at']}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'received':
        return Colors.green.shade700;
      case 'in_transit':
        return Colors.blue.shade700;
      case 'ready_for_dispatch':
        return const Color(0xFFF0A202);
      case 'loading':
        return Colors.purple.shade700;
      case 'cancelled':
        return Colors.red.shade700;
      default:
        return Colors.blueGrey;
    }
  }
}
