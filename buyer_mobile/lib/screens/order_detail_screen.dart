import 'package:flutter/material.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  final _api = ApiService();
  Order? _order;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('orders/${widget.orderId}');
      setState(() {
        _order = Order.fromJson(data['order'] as Map<String, dynamic>);
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _cancelOrder() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Order'),
        content: const Text('Are you sure you want to cancel this order?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('No'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Yes, cancel'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await _api.post('orders/${widget.orderId}/cancel', {
        'reason': 'changed_mind',
      });
      _load();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Order cancelled.')));
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: Colors.red.shade700,
        ),
      );
    }
  }

  Future<void> _rateProduct(OrderItem item) async {
    int rating = 5;
    final commentCtrl = TextEditingController();
    final result = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: Text('Rate "${item.productName}"'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (i) {
                  return IconButton(
                    icon: Icon(
                      Icons.star,
                      color: i < rating ? AppColors.gold : AppColors.border,
                      size: 32,
                    ),
                    onPressed: () => setLocal(() => rating = i + 1),
                  );
                }),
              ),
              TextField(
                controller: commentCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Feedback (optional)',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Submit'),
            ),
          ],
        ),
      ),
    );
    if (result != true) return;
    try {
      await _api.post('orders/${widget.orderId}/rate', {
        'product_id': item.productId,
        'rating': rating,
        'comment': commentCtrl.text.trim(),
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Thank you for your feedback!')),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: Colors.red.shade700,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: 'Order #${widget.orderId}',
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _order == null
          ? const Center(child: Text('Order not found.'))
          : ListView(
              padding: const EdgeInsets.all(12),
              children: [
                _header(_order!),
                const SizedBox(height: 8),
                _progressCard(_order!),
                const SizedBox(height: 8),
                _itemsCard(_order!),
                const SizedBox(height: 8),
                _summaryCard(_order!),
                const SizedBox(height: 8),
                _timelineCard(_order!),
                const SizedBox(height: 8),
                if (_order!.status == 'pending' ||
                    _order!.status == 'confirmed')
                  OutlinedButton(
                    onPressed: _cancelOrder,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red,
                      side: const BorderSide(color: Colors.red),
                    ),
                    child: const Text('Cancel Order'),
                  ),
                const SizedBox(height: 16),
              ],
            ),
    );
  }

  Widget _header(Order order) {
    final pay = order.payment;
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text(
                'Status: ',
                style: TextStyle(fontSize: 14, color: Color(0xFF212121)),
              ),
              Text(
                order.status.replaceAll('_', ' ').toUpperCase(),
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: _color(order.status),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'Placed on: ${_date(order.createdAt)}',
            style: const TextStyle(fontSize: 12, color: Color(0xFF616161)),
          ),
          if (pay != null)
            Text(
              'Payment: ${pay.method.replaceAll('_', ' ').toUpperCase()} · ${pay.status.toUpperCase()}',
              style: const TextStyle(fontSize: 12, color: Color(0xFF616161)),
            ),
          if (order.delivery != null)
            Text(
              'Delivery: ${order.delivery!.status.replaceAll('_', ' ').toUpperCase()}',
              style: const TextStyle(fontSize: 12, color: Color(0xFF616161)),
            ),
        ],
      ),
    );
  }

  Widget _progressCard(Order order) {
    final steps = ['pending', 'confirmed', 'out_for_delivery', 'delivered'];
    final labels = ['Placed', 'Confirmed', 'On the way', 'Delivered'];

    final currentIdx = steps.indexOf(order.status);
    int done = currentIdx == -1 ? 0 : currentIdx;

    if (order.status == 'cancelled') {
      return Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: Colors.red.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(6),
        ),
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            const Icon(Icons.cancel_outlined, color: Colors.red),
            const SizedBox(width: 10),
            const Expanded(
              child: Text(
                'This order was cancelled.',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      );
    }

    // For out_for_delivery the parcel is still moving; highlight the last step only once delivered.
    if (order.status == 'out_for_delivery') {
      done = 2;
    }

    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(6),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Progress',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: List.generate(steps.length, (i) {
              final isDone = i <= done;
              final isLast = i == steps.length - 1;
              return Expanded(
                child: Row(
                  children: [
                    _stepDot(
                      i,
                      isDone,
                      order.status == 'delivered' && i == done,
                    ),
                    if (!isLast)
                      Expanded(
                        child: Container(
                          height: 3,
                          margin: const EdgeInsets.symmetric(horizontal: 4),
                          decoration: BoxDecoration(
                            color: isDone
                                ? AppColors.success
                                : AppColors.border,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ),
                  ],
                ),
              );
            }),
          ),
          const SizedBox(height: 8),
          Row(
            children: List.generate(steps.length, (i) {
              return Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: Text(
                    labels[i],
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: i <= done ? FontWeight.w700 : FontWeight.w400,
                      color: i <= done
                          ? AppColors.success
                          : AppColors.textSecondary,
                    ),
                  ),
                ),
              );
            }),
          ),
        ],
      ),
    );
  }

  Widget _stepDot(int i, bool isDone, bool isCurrent) {
    if (isCurrent) {
      return Container(
        width: 22,
        height: 22,
        decoration: BoxDecoration(
          color: AppColors.success,
          shape: BoxShape.circle,
        ),
        child: const Icon(Icons.check, size: 14, color: Colors.white),
      );
    }
    return Container(
      width: 20,
      height: 20,
      decoration: BoxDecoration(
        color: isDone ? AppColors.success : AppColors.border,
        shape: BoxShape.circle,
      ),
      child: isDone
          ? const Icon(Icons.check, size: 13, color: Colors.white)
          : Icon(
              Icons.circle,
              size: 8,
              color: Colors.white.withValues(alpha: 0.9),
            ),
    );
  }

  Widget _itemsCard(Order order) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(6),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Items',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 10),
          ...order.items.map((item) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.productName,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF212121),
                          ),
                        ),
                      ),
                      Text(
                        '${item.quantity} × ${_fmt(item.price)}',
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF212121),
                        ),
                      ),
                    ],
                  ),
                  if (item.variantLabel != null &&
                      item.variantLabel!.isNotEmpty)
                    Text(
                      item.variantLabel!,
                      style: TextStyle(
                        fontSize: 11,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Text(
                        'Subtotal: ${_fmt(item.subtotal)}',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const Spacer(),
                      if (order.status == 'delivered')
                        OutlinedButton(
                          onPressed: () => _rateProduct(item),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size(90, 30),
                            padding: const EdgeInsets.symmetric(horizontal: 10),
                          ),
                          child: const Text(
                            'Rate',
                            style: TextStyle(fontSize: 12),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _summaryCard(Order order) {
    final subtotal = order.subtotal;
    final discount = order.discount ?? 0;
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(6),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Subtotal',
                style: TextStyle(fontSize: 13, color: Color(0xFF212121)),
              ),
              Text(_fmt(subtotal), style: const TextStyle(fontSize: 13)),
            ],
          ),
          if (discount > 0) ...[
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Voucher Discount',
                  style: TextStyle(fontSize: 13, color: Color(0xFF212121)),
                ),
                Text(
                  '-${_fmt(discount)}',
                  style: TextStyle(fontSize: 13, color: AppColors.primary),
                ),
              ],
            ),
          ],
          const Divider(height: 18),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Total',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF212121),
                ),
              ),
              Text(
                _fmt(order.totalAmount),
                style: TextStyle(
                  color: AppColors.primary,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _timelineCard(Order order) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(6),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Timeline',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 10),
          if (order.statusHistories.isEmpty)
            Text(
              'No status updates yet.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            )
          else
            ...order.statusHistories.map((h) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.circle, size: 8, color: AppColors.primary),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            h.toStatus.replaceAll('_', ' ').toUpperCase(),
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF212121),
                            ),
                          ),
                          if (h.note != null)
                            Text(
                              h.note!,
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF212121),
                              ),
                            ),
                          Text(
                            _date(h.createdAt),
                            style: const TextStyle(
                              fontSize: 11,
                              color: Color(0xFF616161),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }

  Color _color(String s) {
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

  String _fmt(double v) {
    final n = v.toStringAsFixed(2);
    return '₱${n.split('.')[0]}.${n.split('.')[1]}';
  }

  String _date(String iso) {
    try {
      final d = DateTime.parse(iso).toLocal();
      return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return iso;
    }
  }
}
