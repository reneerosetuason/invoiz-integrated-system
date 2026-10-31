import '../widgets/buyer_picker.dart';
import '../widgets/buyer_dialog.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_page.dart';
import '../widgets/product_photo.dart';
import '../widgets/shopping_ui.dart';
import 'package:flutter/material.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';
import '../widgets/order_return_dialog.dart';

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
    if (_actionBusy) return;
    setState(() => _actionBusy = true);
    String? reason;
    var reasonDetails = '';
    final form = GlobalKey<FormState>();
    try {
      final confirm = await showCupertinoDialog<bool>(
        barrierDismissible: true,
        context: context,
        builder: (ctx) => StatefulBuilder(
          builder: (ctx, update) => BuyerAlertDialog(
            title: const Text('Cancel Order'),
            content: SingleChildScrollView(
              child: Form(
                key: form,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text('Why are you cancelling?'),
                    const SizedBox(height: 12),
                    BuyerPicker<String>(
                      key: const ValueKey('cancellation-reason'),
                      value: reason,
                      hint: const Text('Select a reason'),
                      decoration: const InputDecoration(
                        labelText: 'Reason (required)',
                      ),
                      items: const [
                        DropdownMenuItem(
                          value: 'changed_mind',
                          child: Text('Changed my mind'),
                        ),
                        DropdownMenuItem(
                          value: 'ordered_by_mistake',
                          child: Text('I ordered by mistake'),
                        ),
                        DropdownMenuItem(
                          value: 'found_better_price',
                          child: Text('I found a better price'),
                        ),
                        DropdownMenuItem(
                          value: 'delivery_too_long',
                          child: Text('Delivery is taking too long'),
                        ),
                        DropdownMenuItem(
                          value: 'wrong_item',
                          child: Text('I ordered the wrong item'),
                        ),
                        DropdownMenuItem(
                          value: 'other',
                          child: Text('Other reason'),
                        ),
                      ],
                      validator: (value) =>
                          value == null ? 'Please select a reason.' : null,
                      onChanged: (value) {
                        update(() => reason = value);
                        form.currentState?.validate();
                      },
                    ),
                    if (reason == 'other') ...[
                      const SizedBox(height: 12),
                      BuyerField.form(
                        key: const ValueKey('cancellation-details'),
                        initialValue: reasonDetails,
                        onChanged: (value) => reasonDetails = value.trim(),
                        minLines: 2,
                        maxLines: 3,
                        maxLength: 300,
                        decoration: const InputDecoration(
                          labelText: 'Tell us a little more',
                          hintText: 'Enter 10–300 characters',
                        ),
                        validator: (value) =>
                            reason == 'other' &&
                                (value?.trim().length ?? 0) < 10
                            ? 'Please provide at least 10 characters.'
                            : null,
                      ),
                    ],
                  ],
                ),
              ),
            ),
            actions: [
              BuyerDialogAction(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Keep order'),
              ),
              BuyerDialogAction(
                isDestructiveAction: true,
                onPressed: () {
                  if (form.currentState!.validate()) {
                    Navigator.pop(ctx, true);
                  }
                },
                child: const Text('Cancel order'),
              ),
            ],
          ),
        ),
      );
      if (confirm != true || !mounted) return;
      await _api.post('orders/${widget.orderId}/cancel', {
        'reason': reason!,
        if (reason == 'other') 'reason_details': reasonDetails,
      });
      if (!mounted) return;
      await _load();
      if (!mounted) return;
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
    final remaining = _order?.returnableQuantity(item) ?? item.quantity;
    if (remaining < 1) return;
    setState(() => _actionBusy = true);
    try {
      final saved = await showCupertinoDialog<Map<String, dynamic>>(
        barrierDismissible: true,
        context: context,
        builder: (_) => OrderReturnDialog(
          item: item,
          maxQuantity: remaining,
          submit: (body, [files]) {
            if (files != null && files.isNotEmpty) {
              final fields = body.map((k, v) => MapEntry(k, v.toString()));
              return _api.postForm(
                'orders/${widget.orderId}/returns',
                fields,
                files: files,
              );
            }
            return _api.post('orders/${widget.orderId}/returns', body);
          },
        ),
      );
      if (saved == null || !mounted) return;
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Return #${saved['id']} saved. Track it in Orders → Return.',
          ),
        ),
      );
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
      await showCupertinoDialog<void>(
        barrierDismissible: true,
        context: context,
        builder: (ctx) => BuyerAlertDialog(
          title: const Text('Delivery code'),
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
            BuyerDialogAction(
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

  Widget _deliveriesCard(Order order) => BuyerPanel(
    title: 'Your deliveries',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: order.deliveries
          .map(
            (delivery) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SelectableText(
                    delivery.trackingNumber ?? 'Delivery #${delivery.id ?? ''}',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  BuyerStatusChip(status: delivery.status),
                  if (delivery.canShowOtp && delivery.id != null) ...[
                    const SizedBox(height: 12),
                    BuyerButton.secondary(
                      onPressed: _actionBusy
                          ? null
                          : () => _showDeliveryCode(delivery),
                      icon: const Icon(Icons.pin_outlined, size: 20),
                      label: const Text('Show delivery code'),
                    ),
                  ],
                ],
              ),
            ),
          )
          .toList(),
    ),
  );

  Widget _returnAction(Order order, OrderItem item) {
    final previous = order.returnsForItem(item)
      ..sort(
        (a, b) => (int.tryParse('${b['id']}') ?? 0).compareTo(
          int.tryParse('${a['id']}') ?? 0,
        ),
      );
    final open = previous.any(
      (r) => ['requested', 'approved', 'item_received'].contains(r['status']),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final record in previous)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Return #${record['id']} · ${Order.returnStatusLabel(record['status'] as String?)}',
                  style: const TextStyle(
                    color: AppColors.primaryDark,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  '${record['quantity']} item(s) · ${_fmt(double.tryParse('${record['refund_amount']}') ?? 0)}',
                ),
                if (record['evidence_path'] != null)
                  Text(
                    'Evidence: ${record['evidence_type'] == 'video' ? 'Video' : 'Photo'} attached',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.primary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                if ((record['decision_note'] as String?)?.trim().isNotEmpty ==
                    true)
                  Text('Store note: ${record['decision_note']}'),
                if (record['status'] == 'approved')
                  const Text(
                    'Contact the store to arrange sending the item back.',
                  ),
                if (record['status'] == 'refunded')
                  const Text(
                    'Refund recorded by the store. Contact the store if payment has not arrived.',
                  ),
              ],
            ),
          ),
        if (!open && order.returnableQuantity(item) > 0)
          BuyerButton.plain(
            onPressed: _actionBusy ? null : () => _requestReturn(item),
            icon: const Icon(Icons.assignment_return_outlined, size: 18),
            label: const Text('Request return'),
          ),
      ],
    );
  }

  Future<void> _rateProduct(OrderItem item) async {
    int rating = 5;
    final commentCtrl = TextEditingController();
    final result = await showCupertinoDialog<bool>(
      barrierDismissible: true,
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => BuyerAlertDialog(
          title: const Text('Rate this item'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                item.productName,
                style: const TextStyle(
                  fontSize: 15,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width:
                    MediaQuery.sizeOf(ctx).width < 350 ||
                        MediaQuery.textScalerOf(ctx).scale(1) > 1.3
                    ? 132
                    : 220,
                child: Wrap(
                  alignment: WrapAlignment.center,
                  children: List.generate(5, (i) {
                    return BuyerButton.icon(
                      tooltip: '${i + 1} star${i == 0 ? '' : 's'}',
                      constraints: const BoxConstraints(
                        minWidth: 36,
                        minHeight: 40,
                      ),
                      icon: Icon(
                        CupertinoIcons.star_fill,
                        color: i < rating ? AppColors.gold : AppColors.border,
                        size: 28,
                      ),
                      onPressed: () => setLocal(() => rating = i + 1),
                    );
                  }),
                ),
              ),
              BuyerField(
                controller: commentCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Feedback (optional)',
                ),
              ),
            ],
          ),
          actions: [
            BuyerDialogAction(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            BuyerDialogAction(
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
          ? const Center(child: CupertinoActivityIndicator())
          : _order == null
          ? const Center(child: Text('Order not found.'))
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
              children: [
                BuyerPageHeading(
                  title: 'Order #${widget.orderId}',
                  description:
                      'Delivery updates, purchased items and payment details.',
                ),
                _header(_order!),
                const SizedBox(height: 12),
                _progressCard(_order!),
                const SizedBox(height: 12),
                if (_order!.deliveries.isNotEmpty) ...[
                  _deliveriesCard(_order!),
                  const SizedBox(height: 12),
                ],
                _itemsCard(_order!),
                const SizedBox(height: 12),
                _summaryCard(_order!),
                const SizedBox(height: 12),
                _timelineCard(_order!),
                const SizedBox(height: 20),
                if (_order!.canCancel ??
                    [
                      'pending',
                      'confirmed',
                      'processing',
                    ].contains(_order!.status))
                  BuyerButton.secondary(
                    onPressed: _actionBusy ? null : _cancelOrder,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.danger,
                      side: const BorderSide(color: AppColors.danger),
                    ),
                    child: const Text('Cancel Order'),
                  ),
                const SizedBox(height: 12),
              ],
            ),
    );
  }

  Widget _header(Order order) => BuyerPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 12,
          runSpacing: 12,
          alignment: WrapAlignment.spaceBetween,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Order total', style: TextStyle(fontSize: 14)),
                const SizedBox(height: 4),
                ShoppingPrice(_fmt(order.totalAmount), size: 22),
              ],
            ),
            BuyerStatusChip(status: order.status),
          ],
        ),
        const Divider(height: 16),
        Text(
          'Placed ${_date(order.createdAt)}',
          style: const TextStyle(fontSize: 14),
        ),
        if (order.payment != null) ...[
          const SizedBox(height: 8),
          Text(
            'Payment: ${order.payment!.method.replaceAll('_', ' ')} · ${order.payment!.status}',
            style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
          ),
        ],
        if (order.delivery != null) ...[
          const SizedBox(height: 8),
          Text(
            'Delivery: ${order.delivery!.status.replaceAll('_', ' ')}',
            style: const TextStyle(fontSize: 14),
          ),
        ],
      ],
    ),
  );

  Widget _progressCard(Order order) {
    if (order.status == 'cancelled') {
      return const BuyerPanel(
        child: Row(
          children: [
            Icon(Icons.cancel_outlined, color: AppColors.danger),
            SizedBox(width: 12),
            Expanded(
              child: Text(
                'This order was cancelled.',
                style: TextStyle(fontSize: 15, color: AppColors.danger),
              ),
            ),
          ],
        ),
      );
    }
    final done = switch (order.status) {
      'confirmed' || 'processing' || 'ready_for_delivery' => 1,
      'out_for_delivery' => 2,
      'delivered' => 3,
      _ => 0,
    };
    const labels = ['Placed', 'Confirmed', 'On the way', 'Delivered'];
    return BuyerPanel(
      title: 'Order progress',
      child: Column(
        children: List.generate(
          labels.length,
          (index) => Padding(
            padding: EdgeInsets.only(
              bottom: index == labels.length - 1 ? 0 : 10,
            ),
            child: Row(
              children: [
                _stepDot(index, index <= done, index == done),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    labels[index],
                    style: TextStyle(
                      fontSize: 15,
                      color: index <= done
                          ? AppColors.primaryDark
                          : AppColors.textSecondary,
                      fontWeight: index == done
                          ? FontWeight.w600
                          : FontWeight.w400,
                    ),
                  ),
                ),
                if (index == done)
                  const Text(
                    'Current',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primaryDark,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _stepDot(int index, bool isDone, bool isCurrent) => Container(
    width: 32,
    height: 32,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: isDone ? AppColors.primaryDark : AppColors.surfaceTint,
      border: Border.all(
        color: isDone ? AppColors.primaryDark : AppColors.border,
      ),
    ),
    child: Icon(
      isDone ? CupertinoIcons.check_mark : Icons.circle_outlined,
      size: isDone ? 18 : 13,
      color: isDone ? AppColors.surface : AppColors.textSecondary,
    ),
  );

  Widget _itemsCard(Order order) => BuyerPanel(
    title: 'Purchased items',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: order.items
          .map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: SizedBox(
                          width: 64,
                          height: 80,
                          child: ProductPhoto(
                            path: item.productImage,
                            label: item.productName,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.productName,
                              style: const TextStyle(
                                fontSize: 15,
                                height: 1.4,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            if (item.variantLabel?.isNotEmpty == true) ...[
                              const SizedBox(height: 4),
                              Text(
                                item.variantLabel!,
                                style: const TextStyle(fontSize: 13),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Text(
                              '${item.quantity} × ${_fmt(item.price)}',
                              style: const TextStyle(fontSize: 14),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ShoppingValueRow('Item total', _fmt(item.subtotal)),
                  if (order.status == 'delivered')
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        _returnAction(order, item),
                        BuyerButton.secondary(
                          onPressed: () => _rateProduct(item),
                          child: const Text('Rate'),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          )
          .toList(),
    ),
  );

  Widget _summaryCard(Order order) => BuyerPanel(
    title: 'Payment summary',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ShoppingValueRow('Subtotal', _fmt(order.subtotal)),
        if (order.merchandiseDiscount > 0)
          ShoppingValueRow(
            'Voucher discount',
            '-${_fmt(order.merchandiseDiscount)}',
          ),
        if (order.shippingFee != null)
          ShoppingValueRow(
            'Shipping',
            order.shippingFee == 0 ? 'Free' : _fmt(order.shippingFee!),
          ),
        const Divider(height: 24),
        ShoppingValueRow('Total', _fmt(order.totalAmount), total: true),
      ],
    ),
  );

  Widget _timelineCard(Order order) => BuyerPanel(
    title: 'Order history',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (order.statusHistories.isEmpty)
          const Text('No status updates yet.', style: TextStyle(fontSize: 14))
        else
          ...order.statusHistories.map(
            (history) => Padding(
              padding: const EdgeInsets.only(bottom: 20),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 5),
                    child: Icon(
                      Icons.circle,
                      size: 9,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          history.toStatus.replaceAll('_', ' '),
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        if (history.note?.isNotEmpty == true) ...[
                          const SizedBox(height: 6),
                          Text(
                            history.note!,
                            style: const TextStyle(
                              fontSize: 14,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ],
                        const SizedBox(height: 6),
                        Text(
                          _date(history.createdAt),
                          style: const TextStyle(fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    ),
  );

  String _fmt(double value) => shoppingMoney(value);

  String _date(String iso) {
    try {
      final d = DateTime.parse(iso).toLocal();
      return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return iso;
    }
  }
}
