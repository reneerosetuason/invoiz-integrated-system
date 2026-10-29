import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../models/rider_notification.dart';
import '../../providers/notification_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/stagger_in.dart';
import '../deliveries/delivery_detail_screen.dart';

/// Rider notifications list.
class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<NotificationProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        automaticallyImplyLeading: false,
        actions: [
          if (provider.unread > 0)
            TextButton(
              onPressed: provider.markAllRead,
              child: const Text('Mark all read'),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: provider.load,
        child: provider.loading && provider.notifications.isEmpty
            ? const LoadingWidget(label: 'Loading notifications...')
            : provider.error != null && provider.notifications.isEmpty
                ? ErrorView(
                    message: provider.error!,
                    onRetry: provider.load,
                  )
                : _buildList(provider),
      ),
    );
  }

  /// Mark read, then open the linked delivery when the notification
  /// carries one. Delivery ownership stays server-enforced: an
  /// unauthorized or missing delivery simply shows the detail error
  /// state instead of leaking data.
  void _openNotification(BuildContext context, NotificationProvider provider,
      RiderNotification notification) {
    provider.markRead(notification.id);
    final data = notification.data;
    final rawId = data == null ? null : data['delivery_id'];
    final deliveryId =
        rawId is int ? rawId : int.tryParse('${rawId ?? ''}');
    if (deliveryId == null || deliveryId <= 0) return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DeliveryDetailScreen(deliveryId: deliveryId),
      ),
    );
  }

  Widget _buildList(NotificationProvider provider) {
    if (provider.notifications.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 100),
          EmptyState(
            icon: Icons.notifications_none,
            title: 'No notifications yet',
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: provider.notifications.length,
      itemBuilder: (context, index) {
        final n = provider.notifications[index];
        return StaggerIn(
          index: index,
          child: _NotificationTile(
            notification: n,
            onTap: () => _openNotification(context, provider, n),
          ),
        );
      },
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification, this.onTap});

  final RiderNotification notification;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final isRead = notification.isRead;
    final title = notification.title;
    final body = notification.body;
    final createdAt = notification.createdAt;
    final icon = switch (notification.type) {
      'delivery' => Icons.local_shipping_outlined,
      'earnings' => Icons.savings_outlined,
      'announcement' => Icons.campaign_outlined,
      _ => Icons.notifications_outlined,
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconTile(
                icon: icon,
                size: 42,
                iconSize: 20,
                color: isRead
                    ? AppColors.textSecondary
                    : AppTheme.primary,
              ),
              const SizedBox(width: AppGaps.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: AppText.body.copyWith(
                        fontWeight:
                            isRead ? FontWeight.w500 : FontWeight.w800,
                      ),
                    ),
                    if (body != null && body.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(body, style: AppText.secondary),
                    ],
                    const SizedBox(height: 6),
                    Text(
                      FormatUtils.timeAgo(createdAt),
                      style: AppText.caption,
                    ),
                  ],
                ),
              ),
              if (!isRead)
                Container(
                  width: 8,
                  height: 8,
                  margin: const EdgeInsets.only(top: 6),
                  decoration: const BoxDecoration(
                    color: AppColors.primary,
                    shape: BoxShape.circle,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}