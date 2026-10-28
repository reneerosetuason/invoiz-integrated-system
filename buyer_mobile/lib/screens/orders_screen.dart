import 'package:flutter/material.dart';
import '../config.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import 'chat_screen.dart';
import 'home_screen.dart';
import 'login_screen.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final _api = ApiService();
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
      setState(() {
        _allOrders = all;
        _applyFilters();
        _loading = false;
      });
    } catch (e) {
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

  Color _statusColor(String s) {
    switch (s) {
      case 'delivered':
        return AppColors.success;
      case 'cancelled':
        return Colors.red;
      case 'out_for_delivery':
        return AppColors.primary;
      default:
        return AppColors.warning;
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      currentIndex: 3,
      showAppBar: false,
      child: Column(
        children: [
          _ordersHeader(context),
          SizedBox(
            height: 46,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              children: _tabs.map((t) {
                final selected = _status == t.$1;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(t.$2),
                    selected: selected,
                    selectedColor: AppColors.primary,
                    backgroundColor: Colors.white.withValues(alpha: 0.6),
                    side: BorderSide.none,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(999),
                    ),
                    labelStyle: TextStyle(
                      color: selected ? Colors.white : AppColors.textPrimary,
                      fontSize: 13,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                    ),
                    onSelected: (_) {
                      setState(() => _status = t.$1);
                      _applyFilters();
                    },
                  ),
                );
              }).toList(),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _orders.isEmpty
                ? Center(
                    child: Text(
                      _search.isNotEmpty
                          ? 'No matching orders.'
                          : 'No orders found.',
                      style: const TextStyle(color: Color(0xFF212121)),
                    ),
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(12),
                    itemCount: _orders.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, i) => _orderCard(_orders[i]),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _ordersHeader(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [AppColors.primary, const Color(0xFF1B8A8A)],
        ),
      ),
      padding: EdgeInsets.fromLTRB(
        8,
        MediaQuery.of(context).padding.top + 6,
        8,
        10,
      ),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
            onPressed: () {
              if (Navigator.canPop(context)) {
                Navigator.pop(context);
              } else {
                Navigator.pushAndRemoveUntil(
                  context,
                  MaterialPageRoute(builder: (_) => const HomeScreen()),
                  (route) => false,
                );
              }
            },
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Container(
              height: 36,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const SizedBox(width: 10),
                  Icon(Icons.search, color: AppColors.textSecondary, size: 18),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Theme(
                      data: Theme.of(context).copyWith(
                        inputDecorationTheme: const InputDecorationTheme(
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          disabledBorder: InputBorder.none,
                          errorBorder: InputBorder.none,
                          focusedErrorBorder: InputBorder.none,
                        ),
                      ),
                      child: TextField(
                        controller: _searchCtrl,
                        textInputAction: TextInputAction.search,
                        onSubmitted: _onSearch,
                        onChanged: (v) {
                          if (v.isEmpty) _onSearch('');
                        },
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF212121),
                        ),
                        decoration: const InputDecoration(
                          hintText: 'Search orders...',
                          hintStyle: TextStyle(
                            color: Color(0xFF9E9E9E),
                            fontSize: 13,
                          ),
                          border: InputBorder.none,
                          isDense: true,
                          contentPadding: EdgeInsets.symmetric(vertical: 9),
                          filled: false,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
              ),
            ),
          ),
          const SizedBox(width: 6),
          const AppNotificationBell(iconColor: Colors.white),
          IconButton(
            icon: const Icon(
              Icons.chat_bubble_outline_rounded,
              color: Colors.white,
              size: 22,
            ),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
            onPressed: () {
              final auth = AuthServiceProvider.of(context);
              if (!auth.isLoggedIn) {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                );
                return;
              }
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ChatScreen()),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _orderCard(Order order) {
    final firstItem = order.items.isNotEmpty ? order.items.first : null;
    final otherCount = order.items.length - 1;
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: order.id)),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.8),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.white.withValues(alpha: 0.4)),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.04),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Order #${order.id}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: Color(0xFF212121),
                  ),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.transparent,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: _statusColor(order.status).withValues(alpha: 0.4),
                    ),
                  ),
                  child: Text(
                    _statusLabel(order.status),
                    style: TextStyle(
                      fontSize: 11,
                      color: _statusColor(order.status),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (firstItem != null)
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      width: 48,
                      height: 48,
                      color: AppColors.surfaceSoft,
                      child: _thumb(firstItem.productImage),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          firstItem.productName,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF212121),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (otherCount > 0)
                          Text(
                            '+$otherCount more item${otherCount == 1 ? '' : 's'}',
                            style: TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            const SizedBox(height: 8),
            Row(
              children: [
                Text(
                  'Total: ',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  _fmt(order.totalAmount),
                  style: TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const Spacer(),
                Text(
                  _date(order.createdAt),
                  style: TextStyle(
                    fontSize: 11,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _thumb(String? url) {
    if (url == null || url.isEmpty)
      return const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 22,
      );
    return Image.network(
      AppConfig.storageUrl(url),
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 22,
      ),
    );
  }

  String _fmt(double v) {
    final n = v.toStringAsFixed(2);
    return '₱${n.split('.')[0]}.${n.split('.')[1]}';
  }

  String _date(String iso) {
    try {
      final d = DateTime.parse(iso).toLocal();
      return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    } catch (_) {
      return iso;
    }
  }
}
