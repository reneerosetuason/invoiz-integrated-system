import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../core/utils/geolocation.dart';
import '../../models/conversation.dart';
import '../../models/delivery.dart';
import '../../providers/auth_provider.dart';
import '../../providers/delivery_provider.dart';
import '../../services/message_service.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/stagger_in.dart';
import '../../widgets/status_badge.dart';
import '../messages/conversations_screen.dart';
import '../messages/messages_screen.dart';
import 'customer_delivery_screen.dart';
import 'failed_delivery_screen.dart';
import 'pickup_screen.dart';

/// Full delivery detail: shop, order items, customer and workflow actions.
class DeliveryDetailScreen extends StatefulWidget {
  const DeliveryDetailScreen({super.key, required this.deliveryId});

  final int deliveryId;

  @override
  State<DeliveryDetailScreen> createState() => _DeliveryDetailScreenState();
}

class _DeliveryDetailScreenState extends State<DeliveryDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DeliveryProvider>().loadDetail(widget.deliveryId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<DeliveryProvider>();
    final delivery = provider.selected;

    if (provider.loading && delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery')),
        body: const LoadingWidget(label: 'Loading delivery...'),
      );
    }

    if (delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery')),
        body: ErrorView(
          message: provider.error ?? 'Delivery not found.',
          onRetry: () =>
              provider.loadDetail(widget.deliveryId),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(delivery.trackingNumber),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(color: AppColors.border, height: 1),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => provider.loadDetail(widget.deliveryId),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            StaggerIn(index: 0, child: _StatusHeader(delivery: delivery)),
            const SizedBox(height: 16),
            StaggerIn(index: 1, child: _ShopSection(delivery: delivery)),
            const SizedBox(height: 16),
            StaggerIn(index: 2, child: _OrderSection(delivery: delivery)),
            const SizedBox(height: 16),
            StaggerIn(index: 3, child: _CustomerSection(delivery: delivery)),
            const SizedBox(height: 16),
            if (delivery.statusLogs.isNotEmpty)
              StaggerIn(
                  index: 4,
                  child: _TimelineSection(logs: delivery.statusLogs)),
            const SizedBox(height: 24),
          ],
        ),
      ),
      bottomNavigationBar: StaggerIn(
        index: 5,
        child: _ActionBar(
          delivery: delivery,
          busy: provider.actionBusy,
        ),
      ),
    );
  }
}

class _StatusHeader extends StatelessWidget {
  const _StatusHeader({required this.delivery});

  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppGaps.lg),
        child: Row(
          children: [
            const IconTile(
              icon: Icons.local_shipping_outlined,
              size: 48,
              iconSize: 24,
            ),
            const SizedBox(width: AppGaps.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    delivery.trackingNumber,
                    style: AppText.tracking,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Order #${delivery.orderId ?? delivery.trackingNumber}',
                    style: AppText.secondary.copyWith(fontSize: 12),
                  ),
                ],
              ),
            ),
            StatusBadge(status: delivery.status, label: delivery.statusLabel),
          ],
        ),
      ),
    );
  }
}

class _ShopSection extends StatelessWidget {
  const _ShopSection({required this.delivery});

  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    final shop = delivery.shop;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SectionTitle(
              icon: Icons.storefront_outlined,
              title: 'Shop Information',
            ),
            const SizedBox(height: 12),
            _InfoRow(label: 'Shop', value: shop.name),
            if (shop.phone != null && shop.phone!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _InfoRow(label: 'Phone', value: shop.phone!),
            ],
            if (shop.address != null && shop.address!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _InfoRow(label: 'Address', value: shop.address!),
            ],
            if (shop.latitude != null && shop.longitude != null) ...[
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () => Geolocation.openNavigation(
                      shop.latitude!, shop.longitude!),
                  icon: const Icon(Icons.navigation_outlined, size: 18),
                  label: const Text('Navigate to Shop'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(44),
                  ),
                ),
              ),
            ] else
              const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}

class _OrderSection extends StatelessWidget {
  const _OrderSection({required this.delivery});

  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SectionTitle(
              icon: Icons.receipt_long_outlined,
              title: 'Order Information',
            ),
            const SizedBox(height: 8),
            if (delivery.items.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: Text(
                  'No item details available.',
                  style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
                ),
              )
            else
              ...delivery.items.map(
                (item) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: 14,
                              ),
                            ),
                            if (item.variantLabel != null)
                              Text(
                                item.variantLabel!,
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                          ],
                        ),
                      ),
                      Text('x${item.quantity}',
                          style: const TextStyle(
                              fontWeight: FontWeight.w700)),
                      const SizedBox(width: 16),
                      SizedBox(
                        width: 80,
                        child: Text(
                          FormatUtils.peso(item.subtotal),
                          textAlign: TextAlign.right,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            const Divider(height: 20),
            _TotalRow(label: 'Subtotal', value: delivery.subtotal),
            if (delivery.deliveryFee != null)
              _TotalRow(label: 'Delivery Fee', value: delivery.deliveryFee!),
            const SizedBox(height: 4),
            _TotalRow(label: 'Total', value: delivery.total, bold: true),
            const Divider(height: 20),
            _InfoRow(
              label: 'Payment',
              value: _paymentLabel(delivery.paymentMethod),
            ),
            if (delivery.isCashOnDelivery && delivery.amountToCollect != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: _InfoRow(
                  label: 'Amount to Collect',
                  value: FormatUtils.peso(delivery.amountToCollect!),
                ),
              ),
          ],
        ),
      ),
    );
  }

  static String _paymentLabel(String? method) {
    switch (method) {
      case 'cash_on_delivery':
        return 'Cash on Delivery';
      case 'gcash':
        return 'GCash';
      case 'bank_transfer':
        return 'Bank Transfer';
      default:
        return method ?? '—';
    }
  }
}

class _CustomerSection extends StatelessWidget {
  const _CustomerSection({required this.delivery});

  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    final customer = delivery.customer;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SectionTitle(
              icon: Icons.person_outline,
              title: 'Customer Information',
            ),
            const SizedBox(height: 12),
            _InfoRow(label: 'Customer', value: customer.name),
            if (customer.phone != null && customer.phone!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _InfoRow(label: 'Phone', value: customer.phone!),
            ],
            if (customer.address != null && customer.address!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _InfoRow(label: 'Address', value: customer.address!),
            ],
            if (delivery.notes != null && delivery.notes!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _InfoRow(label: 'Instructions', value: delivery.notes!),
            ],
            const SizedBox(height: 14),
            Row(
              children: [
                if (customer.phone != null && customer.phone!.isNotEmpty)
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _call(customer.phone!, context),
                      icon: const Icon(Icons.call_outlined, size: 18),
                      label: const Text('Call'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(44),
                      ),
                    ),
                  ),
                const SizedBox(width: 10),
                if (customer.latitude != null && customer.longitude != null)
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => Geolocation.openNavigation(
                          customer.latitude!, customer.longitude!),
                      icon: const Icon(Icons.navigation_outlined, size: 18),
                      label: const Text('Navigate'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(44),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: _MessageBuyerButton(delivery: delivery),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _call(String phone, BuildContext context) async {
    final uri = Uri.parse('tel:$phone');
    final launched = await launchUrl(uri);
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to open the dialer.')),
      );
    }
  }
}

class _MessageBuyerButton extends StatefulWidget {
  const _MessageBuyerButton({required this.delivery});

  final Delivery delivery;

  @override
  State<_MessageBuyerButton> createState() => _MessageBuyerButtonState();
}

class _MessageBuyerButtonState extends State<_MessageBuyerButton> {
  bool _busy = false;

  Future<void> _openChat() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final auth = context.read<AuthProvider>();
      final service = MessageService(auth.api);

      ConversationThread? buyer;
      ConversationThread? other;
      var page = 1;
      var hasMore = true;

      // Per-delivery buyer/seller threads are created by the backend. Match
      // on order or tracking, preferring an actual buyer thread.
      while (hasMore && buyer == null && page <= 5) {
        final result = await service.listConversations(page: page);
        for (final thread in result.threads) {
          final sameOrder =
              widget.delivery.orderId != null &&
              thread.orderId == widget.delivery.orderId;
          final sameTracking =
              thread.tracking != null &&
              thread.tracking == widget.delivery.trackingNumber;
          if (!sameOrder && !sameTracking) continue;
          if (thread.type == 'buyer') {
            buyer = thread;
            break;
          }
          other ??= thread;
        }
        hasMore = result.hasMore;
        page++;
      }

      if (!mounted) return;
      final match = buyer ?? other;
      if (match != null) {
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => MessagesScreen(thread: match)),
        );
      } else {
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const ConversationsScreen()),
        );
        if (mounted) {
          _snack('No buyer conversation exists for this delivery yet.');
        }
      }
    } catch (_) {
      if (mounted) _snack('Unable to open messages. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: _busy ? null : _openChat,
      icon: _busy
          ? const SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : const Icon(Icons.chat_bubble_outline, size: 18),
      label: Text(_busy ? 'Opening messages…' : 'Message Buyer'),
      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(44)),
    );
  }
}

class _TimelineSection extends StatelessWidget {
  const _TimelineSection({required this.logs});

  final List<StatusLog> logs;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SectionTitle(
              icon: Icons.history,
              title: 'Status History',
            ),
            const SizedBox(height: 8),
            ...logs.asMap().entries.map((entry) {
              final log = entry.value;
              final isLast = entry.key == logs.length - 1;
              return IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Connected rail: colored node with a line to the next
                    // event so the journey reads top-to-bottom.
                    Column(
                      children: [
                        const SizedBox(height: 4),
                        Container(
                          width: 10,
                          height: 10,
                          decoration: BoxDecoration(
                            color: StatusBadge.colorFor(log.status),
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: StatusBadge.colorFor(log.status)
                                  .withValues(alpha: 0.3),
                              width: 2,
                            ),
                          ),
                        ),
                        if (!isLast)
                          Expanded(
                            child: Container(
                              width: 2,
                              margin: const EdgeInsets.symmetric(vertical: 3),
                              decoration: BoxDecoration(
                                color: AppColors.border,
                                borderRadius: BorderRadius.circular(2),
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Padding(
                        padding: EdgeInsets.only(
                          bottom: isLast ? 0 : AppGaps.md,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              log.status.toUpperCase().replaceAll('_', ' '),
                              style: AppText.body.copyWith(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            if (log.notes != null)
                              Text(
                                log.notes!,
                                style: AppText.secondary.copyWith(fontSize: 12),
                              ),
                            Text(
                              FormatUtils.dateTime(log.createdAt),
                              style: AppText.caption,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

class _ActionBar extends StatelessWidget {
  const _ActionBar({required this.delivery, required this.busy});

  final Delivery delivery;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final isDone = const ['delivered', 'delivery_failed', 'cancelled']
        .contains(delivery.status);

    // Terminal records stay read-only, except the failed-delivery
    // next step (re-attempt request / center return), which is valid
    // until used.
    if (delivery.status == 'delivery_failed') {
      return _failedNextStep(context);
    }

    if (isDone) {
      return const SizedBox.shrink();
    }

    // Already handed over — pickup rider's job is done; wait for center.
    if (delivery.status == 'picked_up' &&
        delivery.sortingCenterHandoffAt != null) {
      final handoffCenter = delivery.handlingCenterName;
      return SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
          child: _custodyBanner(
            handoffCenter != null && handoffCenter.isNotEmpty
                ? 'Parcel handed over to $handoffCenter.'
                : 'Parcel handed over to sorting center.',
          ),
        ),
      );
    }

    // Special handoff/pickup actions take precedence over the generic next-step.
    final handoffAction = _sortingCenterAction();
    if (handoffAction != null) {
      final isHandoff = delivery.status == 'picked_up' &&
          delivery.sortingCenterHandoffAt == null;
      final isPickup = (delivery.status == 'assigned' ||
              delivery.status == 'accepted') &&
          delivery.parcelStatus == 'sorted' &&
          delivery.sortingCenterHandoffAt != null &&
          delivery.sortingCenterPickupAt == null;
      final label = isHandoff
          ? 'Deliver to Sorting Center'
          : isPickup
              ? 'Pick Up from Sorting Center'
              : handoffAction.label;
      final icon = isHandoff
          ? Icons.warehouse_outlined
          : isPickup
              ? Icons.inventory_2_outlined
              : handoffAction.icon;

      return SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (isPickup)
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 8),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                        color: AppColors.primary.withValues(alpha: 0.18)),
                  ),
                  child: const Text(
                    'Parcel is sorted and ready at the center. Pick it up to start delivery.',
                    style: TextStyle(
                        fontSize: 12, color: AppColors.textSecondary),
                    textAlign: TextAlign.center,
                  ),
                ),
              ElevatedButton.icon(
                onPressed:
                    busy ? null : () => _onAction(context),
                icon: busy
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: Colors.white,
                        ),
                      )
                    : Icon(icon, size: 20),
                label: Text(busy ? 'Working...' : label),
              ),
            ],
          ),
        ),
      );
    }

    final action = _nextAction(delivery.status);

    // Collected from the center — persistent confirmation that the parcel
    // is back in rider custody. The generic flow (e.g. Accept) continues
    // underneath.
    final pickupCenter = delivery.destinationCenterName?.isNotEmpty == true
        ? delivery.destinationCenterName
        : delivery.handlingCenterName;
    final showPickupConfirm =
        (delivery.status == 'assigned' || delivery.status == 'accepted') &&
            delivery.sortingCenterPickupAt != null;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (showPickupConfirm) ...[
              _custodyBanner(
                pickupCenter != null && pickupCenter.isNotEmpty
                    ? 'Parcel picked up from $pickupCenter — ready for delivery.'
                    : 'Parcel picked up from sorting center — ready for delivery.',
              ),
              const SizedBox(height: 8),
            ],
            ElevatedButton.icon(
              onPressed: busy
                  ? null
                  : () => _onAction(context),
              icon: busy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        color: Colors.white,
                      ),
                    )
                  : Icon(action.icon, size: 20),
              label: Text(busy ? 'Working...' : action.label),
            ),
          ],
        ),
      ),
    );
  }

  /// Failed-delivery next step: request a re-attempt or return the
  /// parcel, unless already done. Terminal otherwise.
  Widget _failedNextStep(BuildContext context) {
    if (delivery.hasReattemptRequest) {
      return SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
          child: _custodyBanner(
            'Re-attempt requested — waiting on Logistics to reassign.',
          ),
        ),
      );
    }
    if (delivery.hasCenterReturn) {
      final center = delivery.destinationCenterName?.isNotEmpty == true
          ? delivery.destinationCenterName
          : delivery.handlingCenterName;
      return SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
          child: _custodyBanner(
            center != null && center.isNotEmpty
                ? 'Parcel returned to $center.'
                : 'Parcel returned to the sorting center.',
          ),
        ),
      );
    }
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // RID-FAIL-003: a refusal goes straight back; no further attempt.
            if (delivery.failureReason != 'Customer refused order') ...[
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: busy ? null : () => _nextStep(context, true),
                  icon: const Icon(Icons.replay_outlined, size: 20),
                  label: Text(busy ? 'Working...' : 'Request Re-attempt'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: busy ? null : () => _nextStep(context, false),
                icon: const Icon(Icons.warehouse_outlined, size: 20),
                label: Text(
                  busy ? 'Working...' : 'Return to Sorting Center (RTS)',
                ),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _nextStep(BuildContext context, bool reattempt) async {
    final provider = context.read<DeliveryProvider>();
    final ok = reattempt
        ? await provider.requestReattempt(delivery.id)
        : await provider.returnToCenter(delivery.id);
    if (!context.mounted) return;
    if (ok) {
      provider.loadDetail(delivery.id);
      _snack(
          context,
          reattempt
              ? 'Re-attempt requested. Logistics will review and reassign.'
              : 'Parcel marked as returned to the sorting center.');
    } else {
      _snack(
          context,
          provider.error ??
              (reattempt
                  ? 'Unable to request a re-attempt.'
                  : 'Unable to return the parcel.'));
    }
  }

  /// Shared green confirmation chip for sorting-center custody states.
  /// Callers provide the outer SafeArea/insets.
  Widget _custodyBanner(String message) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.success.withValues(alpha: 0.18)),
      ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const PopIn(
                child: Icon(Icons.check_circle_outline,
                    size: 18, color: AppColors.success),
              ),
              const SizedBox(width: 8),
          Flexible(
            child: Text(message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.success)),
          ),
        ],
      ),
    );
  }

  _Action? _sortingCenterAction() {
    if (delivery.status == 'picked_up' &&
        delivery.sortingCenterHandoffAt == null) {
      return _Action('Deliver to Sorting Center', Icons.warehouse_outlined);
    }
    if ((delivery.status == 'assigned' || delivery.status == 'accepted') &&
        delivery.parcelStatus == 'sorted' &&
        delivery.sortingCenterHandoffAt != null &&
        delivery.sortingCenterPickupAt == null) {
      return _Action('Pick Up from Sorting Center', Icons.inventory_2_outlined);
    }
    // Already handed over: hide further pickup-rider actions.
    if (delivery.status == 'picked_up' &&
        delivery.sortingCenterHandoffAt != null) {
      return null;
    }
    return null;
  }

  _Action _nextAction(String status) {
    switch (status) {
      case 'assigned':
        return _Action('Accept Delivery', Icons.check_circle_outline);
      case 'accepted':
        return _Action('Head to Shop', Icons.storefront_outlined);
      case 'going_to_pickup':
        return _Action('I Arrived at Shop', Icons.storefront_outlined);
      case 'arrived_at_shop':
        return _Action('Confirm Pickup', Icons.inventory_2_outlined);
      case 'picked_up':
        return _Action('Out for Delivery', Icons.delivery_dining_outlined);
      case 'out_for_delivery':
        return _Action('I Arrived at Customer', Icons.location_on_outlined);
      case 'arrived_at_customer':
        return _Action('Complete Delivery', Icons.check_circle_outline);
      default:
        return _Action('Next Step', Icons.arrow_forward);
    }
  }

  void _onAction(BuildContext context) {
    // Sorting center handoff (pickup rider)
    if (delivery.status == 'picked_up' &&
        delivery.sortingCenterHandoffAt == null) {
      _confirmThen(context,
          title: 'Deliver to Sorting Center?',
          message:
              'Confirm this parcel has been handed over to the sorting center. This will record the handoff time.',
          onConfirm: () async {
            final provider = context.read<DeliveryProvider>();
            final ok = await provider.sortingCenterHandoff(delivery.id);
            if (!context.mounted) return;
            if (ok) {
              provider.loadDetail(delivery.id);
              _snack(context, 'Parcel handed over to sorting center.');
            } else {
              _snack(context, provider.error ?? 'Unable to hand over.');
            }
          });
      return;
    }

    // Sorting center pickup (delivery rider)
    if ((delivery.status == 'assigned' || delivery.status == 'accepted') &&
        delivery.parcelStatus == 'sorted' &&
        delivery.sortingCenterHandoffAt != null &&
        delivery.sortingCenterPickupAt == null) {
      _confirmThen(context,
          title: 'Pick Up from Sorting Center?',
          message:
              'Confirm you have collected this parcel from the sorting center. Parcel will be marked dispatched.',
          onConfirm: () async {
            final provider = context.read<DeliveryProvider>();
            final ok = await provider.sortingCenterPickup(delivery.id);
            if (!context.mounted) return;
            if (ok) {
              provider.loadDetail(delivery.id);
              _snack(context, 'Parcel picked up from sorting center.');
            } else {
              _snack(context, provider.error ?? 'Unable to pick up.');
            }
          });
      return;
    }

    final provider = context.read<DeliveryProvider>();

    switch (delivery.status) {
      case 'assigned':
        _confirmThen(context,
            title: 'Accept this delivery?',
            message:
                'Once accepted, you are responsible for completing this delivery.',
            onConfirm: () async {
              final ok = await provider.accept(delivery.id);
              if (!context.mounted) return;
              if (ok) {
                provider.loadDetail(delivery.id);
                _snack(context, 'Delivery accepted.');
              } else {
                _snack(context, provider.error ?? 'Unable to accept.');
              }
            });
        break;
      case 'accepted':
        _update(context, 'going_to_pickup', 'Heading to the shop...');
        break;
      case 'going_to_pickup':
        _update(context, 'arrived_at_shop', 'Arrived at the shop.');
        break;
      case 'arrived_at_shop':
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => PickupScreen(deliveryId: delivery.id),
          ),
        );
        break;
      case 'picked_up':
        _update(context, 'out_for_delivery', 'Out for delivery.');
        break;
      case 'out_for_delivery':
        _update(context, 'arrived_at_customer', 'Arrived at the customer.');
        break;
      case 'arrived_at_customer':
        _showDeliveryChoices(context);
        break;
    }
  }

  void _update(BuildContext context, String status, String message) async {
    final provider = context.read<DeliveryProvider>();
    final ok = await provider.updateStatus(delivery.id, status);
    if (!context.mounted) return;
    if (ok) {
      provider.loadDetail(delivery.id);
      _snack(context, message);
    } else {
      _snack(context, provider.error ?? 'Unable to update delivery.');
    }
  }

  void _showDeliveryChoices(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ListTile(
                  leading: const Icon(Icons.check_circle_outline,
                      color: AppTheme.success),
                  title: const Text('Complete Delivery'),
                  subtitle: const Text('Collect COD and provide proof.'),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    _openProof(context);
                  },
                ),
                ListTile(
                  leading:
                      const Icon(Icons.error_outline, color: AppTheme.danger),
                  title: const Text('Report Failed Delivery'),
                  subtitle: const Text('Customer unavailable or other issue.'),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    _openFailed(context);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _openProof(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CustomerDeliveryScreen(deliveryId: delivery.id),
      ),
    );
  }

  void _openFailed(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => FailedDeliveryScreen(deliveryId: delivery.id),
      ),
    );
  }

  void _confirmThen(
    BuildContext context, {
    required String title,
    required String message,
    required Future<void> Function() onConfirm,
  }) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(dialogContext);
              await onConfirm();
            },
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
  }

  void _snack(BuildContext context, String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        IconTile(icon: icon, size: 34, iconSize: 18),
        const SizedBox(width: AppGaps.sm),
        Text(title, style: AppText.section),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 110,
          child: Text(
            label,
            style: AppText.secondary.copyWith(
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: AppText.body.copyWith(
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({
    required this.label,
    required this.value,
    this.bold = false,
  });

  final String label;
  final double value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 14,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
              color: bold ? AppColors.textPrimary : AppColors.textSecondary,
            ),
          ),
          Text(
            FormatUtils.peso(value),
            style: TextStyle(
              fontSize: 14,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
              color: bold ? AppTheme.primary : AppColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}

class _Action {
  const _Action(this.label, this.icon);

  final String label;
  final IconData icon;
}