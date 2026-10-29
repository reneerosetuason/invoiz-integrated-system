import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../widgets/buyer_page.dart';
import '../widgets/product_photo.dart';
import '../widgets/shopping_ui.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatefulWidget {
  final ApiService? api;
  const OrdersScreen({super.key, this.api});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  late final _api = widget.api ?? ApiService();
  final _searchCtrl = TextEditingController();
  List<Order> _allOrders = [];
  List<Order> _orders = [];
  bool _loading = true;
  String _status = 'all';
  String _search = '';

  static const _tabs = [
    ('all', 'All'),
    ('pending', 'To Ship'),
    ('out_for_delivery', 'In Transit'),
    ('delivered', 'Delivered'),
    ('cancelled', 'Cancelled'),
  ];

  static const _statusGroups = {
    'pending': ['pending', 'confirmed', 'processing', 'ready_for_delivery'],
    'out_for_delivery': ['out_for_delivery'],
    'delivered': ['delivered'],
    'cancelled': ['cancelled'],
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _api.get('orders');
      final all = (data['orders'] as List)
          .whereType<Map<String, dynamic>>()
          .map(Order.fromJson)
          .toList();
      if (!mounted) return;
      setState(() {
        _allOrders = all;
        _applyFilters();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  void _applyFilters() {
    var filtered = List<Order>.from(_allOrders);
    if (_status != 'all') {
      final group = _statusGroups[_status] ?? [_status];
      filtered = filtered.where((o) => group.contains(o.status)).toList();
    }
    if (_search.isNotEmpty) {
      final q = _search.toLowerCase();
      filtered = filtered
          .where(
            (o) => o.items.any((i) => i.productName.toLowerCase().contains(q)),
          )
          .toList();
    }
    setState(() => _orders = filtered);
  }

  void _onSearch(String v) {
    setState(() => _search = v.trim().toLowerCase());
    _applyFilters();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  String _statusLabel(String s) {
    switch (s) {
      case 'pending':
        return 'To Ship';
      case 'confirmed':
      case 'processing':
      case 'ready_for_delivery':
        return 'Processing';
      case 'out_for_delivery':
        return 'In Transit';
      case 'delivered':
        return 'Delivered';
      case 'cancelled':
        return 'Cancelled';
      default:
        return s.replaceAll('_', ' ').toUpperCase();
    }
  }

  @override
  Widget build(BuildContext context) => MainLayout(
    currentIndex: 3,
    title: 'Orders',
    showSettingsIcon: false,
    api: _api,
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const BuyerPageHeading(
                title: 'Your orders',
                description:
                    'From the store to your door. Follow every order here.',
              ),
              ShoppingSearch(
                controller: _searchCtrl,
                hint: 'Search orders...',
                onChanged: _onSearch,
              ),
            ],
          ),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 6),
          child: Row(
            children: _tabs
                .map(
                  (tab) => Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(tab.$2),
                      selected: _status == tab.$1,
                      showCheckmark: false,
                      selectedColor: AppColors.primaryDark,
                      backgroundColor: AppColors.paper,
                      labelStyle: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: _status == tab.$1
                            ? AppColors.surface
                            : AppColors.textPrimary,
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 6,
                        vertical: 4,
                      ),
                      onSelected: (_) {
                        setState(() => _status = tab.$1);
                        _applyFilters();
                      },
                    ),
                  ),
                )
                .toList(),
          ),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CupertinoActivityIndicator())
              : RefreshIndicator.adaptive(
                  onRefresh: _load,
                  child: _orders.isEmpty
                      ? LayoutBuilder(
                          builder: (context, constraints) => ListView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            children: [
                              SizedBox(
                                height: constraints.maxHeight,
                                child: BuyerEmptyState(
                                  icon: CupertinoIcons.doc_text,
                                  title: _search.isNotEmpty
                                      ? 'No matching orders'
                                      : 'No orders here yet',
                                  description: _search.isNotEmpty
                                      ? 'Try another product name or clear the filters.'
                                      : 'Your purchases and delivery updates will appear here.',
                                ),
                              ),
                            ],
                          ),
                        )
                      : ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                          itemCount: _orders.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 10),
                          itemBuilder: (_, i) => _orderCard(_orders[i]),
                        ),
                ),
        ),
      ],
    ),
  );

  Widget _orderCard(Order order) {
    final first = order.items.firstOrNull;
    final others = order.items.length - 1;
    return Material(
      key: ValueKey('order-card-${order.id}'),
      color: AppColors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppColors.border, width: .5),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) =>
                  OrderDetailScreen(orderId: order.id, api: widget.api),
            ),
          );
          if (mounted) _load();
        },
        child: Padding(
          padding: const EdgeInsets.all(12),
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
                          'Order #${order.id}',
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 13,
                            height: 1.3,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Placed ${_date(order.createdAt)}',
                          style: const TextStyle(fontSize: 12, height: 1.3),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  BuyerStatusChip(
                    status: order.status,
                    label: _statusLabel(order.status),
                  ),
                ],
              ),
              if (first != null) ...[
                const SizedBox(height: 8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: SizedBox(
                        width: 48,
                        height: 56,
                        child: ProductPhoto(
                          path: first.productImage,
                          label: first.productName,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            first.productName,
                            maxLines:
                                MediaQuery.textScalerOf(context).scale(1) > 1.2
                                ? null
                                : 2,
                            overflow:
                                MediaQuery.textScalerOf(context).scale(1) > 1.2
                                ? TextOverflow.visible
                                : TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 14,
                              height: 1.3,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Wrap(
                            spacing: 6,
                            runSpacing: 2,
                            children: [
                              if (first.variantLabel?.isNotEmpty == true)
                                Text(
                                  first.variantLabel!,
                                  style: const TextStyle(fontSize: 12),
                                ),
                              Text(
                                others > 0
                                    ? '+$others more item${others == 1 ? '' : 's'}'
                                    : 'Quantity ${first.quantity}',
                                style: const TextStyle(fontSize: 12),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  spacing: 12,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text('Total', style: TextStyle(fontSize: 12)),
                        const SizedBox(width: 6),
                        ShoppingPrice(_fmt(order.totalAmount), size: 16),
                      ],
                    ),
                    const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'View order',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppColors.primaryDark,
                          ),
                        ),
                        SizedBox(width: 6),
                        Icon(CupertinoIcons.arrow_right, size: 16),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _fmt(double value) => shoppingMoney(value);
  String _date(String iso) {
    try {
      final date = DateTime.parse(iso).toLocal();
      return '${date.day} ${const ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][date.month - 1]} ${date.year}';
    } catch (_) {
      return iso;
    }
  }
}
