import '../widgets/buyer_page.dart';
import 'package:flutter/material.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  final ApiService? api;
  const OrderDetailScreen({super.key, required this.orderId, this.api});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late final _api = widget.api ?? ApiService();
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
      if (!mounted) return;
      setState(() {
        _order = Order.fromJson(data['order'] as Map<String, dynamic>);
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
    if (confirm != true || !mounted || _actionBusy) return;
    try {
      setState(() => _actionBusy = true);
      await _api.post('orders/${widget.orderId}/cancel', {
        'reason': 'changed_mind',
      });
      if (!mounted) return;
      _load();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Order cancelled.')));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  bool _actionBusy = false;

  Future<void> _requestReturn(OrderItem item) async {
    if (_actionBusy) return;
    var reason = 'damaged';
    var quantity = 1;
    var reasonDetails = '';
    final form = GlobalKey<FormState>();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, update) => AlertDialog(
          title: const Text('Request a return'),
          content: SingleChildScrollView(
            child: Form(
              key: form,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(item.productName),
                  if (item.variantLabel?.isNotEmpty == true)
                    Text(item.variantLabel!),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: reason,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Reason'),
                    items: const [
                      DropdownMenuItem(
                        value: 'damaged',
                        child: Text('Damaged item'),
                      ),
                      DropdownMenuItem(
                        value: 'wrong_item',
                        child: Text('Wrong item'),
                      ),
                      DropdownMenuItem(
                        value: 'not_as_described',
                        child: Text('Not as described'),
                      ),
                      DropdownMenuItem(
                        value: 'changed_mind',
                        child: Text('Changed my mind'),
                      ),
                      DropdownMenuItem(value: 'other', child: Text('Other')),
                    ],
                    onChanged: (value) => update(() => reason = value!),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    initialValue: '1',
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: 'Quantity (up to ${item.quantity})',
                    ),
                    validator: (value) {
                      final parsed = int.tryParse(value ?? '');
                      if (parsed == null ||
                          parsed < 1 ||
                          parsed > item.quantity)
                        return 'Enter a valid purchased quantity.';
                      return null;
                    },
                    onSaved: (value) => quantity = int.parse(value!),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    onChanged: (value) => reasonDetails = value.trim(),
                    maxLength: 300,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Details'),
                    validator: (value) =>
                        reason == 'other' && (value?.trim().length ?? 0) < 10
                        ? 'Please provide at least 10 characters.'
                        : null,
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Keep item'),
            ),
            FilledButton(
              onPressed: () {
                if (form.currentState!.validate()) {
                  form.currentState!.save();
                  Navigator.pop(ctx, true);
                }
              },
              child: const Text('Send request'),
            ),
          ],
        ),
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _actionBusy = true);
    try {
      await _api.post('orders/${widget.orderId}/returns', {
        'order_item_id': item.id,
        'reason': reason,
        'quantity': quantity,
        if (reasonDetails.isNotEmpty) 'reason_details': reasonDetails,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Return requested. The store will review your request.',
          ),
        ),
      );
      await _load();
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _showDeliveryCode(Delivery delivery) async {
    if (_actionBusy || delivery.id == null) return;
    setState(() => _actionBusy = true);
    try {
      final data = await _api.get(
        'orders/${widget.orderId}/deliveries/${delivery.id}/otp',
      );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Delivery verification code'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${data['otp']}',
                style: const TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 6,
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Share this code with your rider only when you receive your parcel.',
              ),
              if (data['expires_at'] != null)
                Text('Expires: ${_date('${data['expires_at']}')}'),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Close'),
            ),
          ],
        ),
      );
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Widget _deliveriesCard(Order order) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Your deliveries',
            style: TextStyle(
              fontFamily: 'Fraunces',
              fontWeight: FontWeight.bold,
            ),
          ),
          for (final delivery in order.deliveries)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    delivery.trackingNumber ?? 'Delivery #${delivery.id ?? ''}',
                  ),
                  Text(delivery.status.replaceAll('_', ' ').toUpperCase()),
                  if (delivery.canShowOtp && delivery.id != null)
                    OutlinedButton.icon(
                      onPressed: _actionBusy
                          ? null
                          : () => _showDeliveryCode(delivery),
                      icon: const Icon(Icons.pin_outlined),
                      label: const Text('Show delivery code'),
                    ),
                ],
              ),
            ),
        ],
      ),
    ),
  );

  Widget _returnAction(Order order, OrderItem item) {
    final previous = order.returns
        .where((r) => r['order_item_id'] == item.id)
        .toList();
    final open = previous
        .where(
          (r) =>
              ['requested', 'approved', 'item_received'].contains(r['status']),
        )
        .firstOrNull;
    if (open != null)
      return Text(
        'Return: ${open['status'].toString().replaceAll('_', ' ')}',
        style: const TextStyle(color: AppColors.primary),
      );
    final refundedQuantity = previous
        .where((r) => r['status'] == 'refunded')
        .fold<int>(
          0,
          (sum, r) => sum + (int.tryParse('${r['quantity']}') ?? 0),
        );
    if (refundedQuantity >= item.quantity)
      return const Text('Returned and refunded');
    return TextButton.icon(
      onPressed: _actionBusy ? null : () => _requestReturn(item),
      icon: const Icon(Icons.assignment_return_outlined, size: 18),
      label: const Text('Request return'),
    );
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
                    constraints: const BoxConstraints(
                      minWidth: 36,
                      minHeight: 40,
                    ),
                    padding: const EdgeInsets.all(4),
                    icon: Icon(
                      Icons.star,
                      color: i < rating ? AppColors.gold : AppColors.border,
                      size: 28,
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
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Thank you for your feedback!')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.danger,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      showBottomNav: false,
      title: 'Order #${widget.orderId}',
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _order == null
          ? const Center(child: Text('Order not found.'))
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
              children: [
                const BuyerPageHeading(
                  title: 'Your order at a glance',
                  description:
                      'Track progress, delivery details and purchased items.',
                ),
                _header(_order!),
                const SizedBox(height: 16),
                _progressCard(_order!),
                const SizedBox(height: 8),
                if (_order!.deliveries.isNotEmpty) _deliveriesCard(_order!),
                _itemsCard(_order!),
                const SizedBox(height: 16),
                _summaryCard(_order!),
                const SizedBox(height: 16),
                _timelineCard(_order!),
                const SizedBox(height: 8),
                if (_order!.canCancel ??
                    [
                      'pending',
                      'confirmed',
                      'processing',
                    ].contains(_order!.status))
                  OutlinedButton(
                    onPressed: _actionBusy ? null : _cancelOrder,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.danger,
                      side: const BorderSide(color: AppColors.danger),
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
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              const Text(
                'Order status',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              BuyerStatusChip(status: order.status),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'Placed on: ${_date(order.createdAt)}',
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
            ),
          ),
          if (pay != null)
            Text(
              'Payment: ${pay.method.replaceAll('_', ' ').toUpperCase()} · ${pay.status.toUpperCase()}',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
          if (order.delivery != null)
            Text(
              'Delivery: ${order.delivery!.status.replaceAll('_', ' ').toUpperCase()}',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
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
          color: AppColors.danger.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(16),
        ),
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            const Icon(Icons.cancel_outlined, color: AppColors.danger),
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
        borderRadius: BorderRadius.circular(16),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Progress',
            style: TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
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
        borderRadius: BorderRadius.circular(16),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Items',
            style: TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
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
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      Text(
                        '${item.quantity} × ${_fmt(item.price)}',
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.textPrimary,
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
                  if (order.status == 'delivered') _returnAction(order, item),
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
        borderRadius: BorderRadius.circular(16),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Subtotal',
                style: TextStyle(fontSize: 13, color: AppColors.textPrimary),
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
                  style: TextStyle(fontSize: 13, color: AppColors.textPrimary),
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
                  color: AppColors.textPrimary,
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
        borderRadius: BorderRadius.circular(16),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Timeline',
            style: TextStyle(
              fontFamily: 'Fraunces',
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
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
                              color: AppColors.textPrimary,
                            ),
                          ),
                          if (h.note != null)
                            Text(
                              h.note!,
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          Text(
                            _date(h.createdAt),
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
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
        return AppColors.danger;
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
