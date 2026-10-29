import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';
import '../core/utils/format_utils.dart';
import '../models/delivery.dart';
import 'app_ui.dart';
import 'stagger_in.dart';
import 'status_badge.dart';

/// Delivery card used in the delivery list.
///
/// Route-first layout: the tracking number and status lead, then a compact
/// pickup → drop-off timeline, then items and the collectable total. The
/// previous three labeled lines (Shop / Customer / Delivery) carried the
/// same information with more words; the timeline keeps every value.
class DeliveryCard extends StatelessWidget {
  const DeliveryCard({
    super.key,
    required this.delivery,
    this.onTap,
    this.trailing,
    this.staggerIndex,
  });

  final Delivery delivery;
  final VoidCallback? onTap;
  final Widget? trailing;

  /// When set, the card plays a one-shot staggered entrance.
  final int? staggerIndex;

  @override
  Widget build(BuildContext context) {
    final card = Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Padding(
          padding: const EdgeInsets.all(AppGaps.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      delivery.trackingNumber,
                      style: AppText.tracking,
                    ),
                  ),
                  StatusBadge(
                      status: delivery.status, label: delivery.statusLabel),
                ],
              ),
              const SizedBox(height: AppGaps.md),
              _RouteTimeline(delivery: delivery),
              if (delivery.items.isNotEmpty) ...[
                const SizedBox(height: AppGaps.md),
                Text(
                  _itemsSummary(),
                  style: AppText.secondary.copyWith(fontSize: 12),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              const Divider(height: AppGaps.xl),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      FormatUtils.peso(delivery.total),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                  if (delivery.isCashOnDelivery)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.secondary.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Text(
                        'COD',
                        style: TextStyle(
                          color: AppColors.secondary,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  if (trailing != null) ...[
                    const SizedBox(width: 8),
                    trailing!,
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
    final index = staggerIndex;
    if (index == null) return card;
    return StaggerIn(index: index, child: card);
  }

  String _itemsSummary() {
    final count =
        delivery.items.fold<int>(0, (sum, item) => sum + item.quantity);
    final names = delivery.items.map((item) => item.name).toList();
    final shown = names.take(2).join(' • ');
    final extra = names.length > 2 ? ' +${names.length - 2} more' : '';
    return '$count item${count == 1 ? '' : 's'} · $shown$extra';
  }
}

/// Compact pickup → drop-off timeline: tinted endpoint tiles joined by a
/// dotted connector, with addresses kept to two lines.
class _RouteTimeline extends StatelessWidget {
  const _RouteTimeline({required this.delivery});

  final Delivery delivery;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _Stop(
          icon: Icons.storefront_outlined,
          eyebrow: 'Pickup',
          name: delivery.shop.name,
          address: delivery.shop.address,
        ),
        const _Connector(),
        _Stop(
          icon: Icons.location_on_outlined,
          eyebrow: 'Drop-off',
          name: delivery.customer.name,
          address: delivery.customer.address,
        ),
      ],
    );
  }
}

class _Stop extends StatelessWidget {
  const _Stop({
    required this.icon,
    required this.eyebrow,
    required this.name,
    this.address,
  });

  final IconData icon;
  final String eyebrow;
  final String name;
  final String? address;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        IconTile(
          icon: icon,
          size: 36,
          iconSize: 18,
        ),
        const SizedBox(width: AppGaps.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(eyebrow.toUpperCase(), style: AppText.eyebrow),
              const SizedBox(height: 2),
              Text(
                name.isNotEmpty ? name : '—',
                style: AppText.cardTitle.copyWith(fontSize: 14),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              if (address != null && address!.isNotEmpty)
                Text(
                  address!,
                  style: AppText.secondary.copyWith(fontSize: 12),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Dotted vertical connector aligning with the endpoint tiles.
class _Connector extends StatelessWidget {
  const _Connector();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 17),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Column(
          children: List.generate(
            3,
            (_) => Container(
              width: 2.5,
              height: 2.5,
              margin: const EdgeInsets.symmetric(vertical: 1.5),
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
