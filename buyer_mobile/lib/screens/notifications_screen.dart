import '../widgets/buyer_page.dart';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../widgets/inbox_status.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';
import 'order_detail_screen.dart';
import 'chat_screen.dart';

class NotificationsScreen extends StatefulWidget {
  final ApiService? api;
  const NotificationsScreen({super.key, this.api});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late final ApiService _api;
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  int _unreadCount = 0, _request = 0;
  String? _error;
  final _reading = <String>{};
  bool _markingAll = false, _opening = false;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _api.get('notifications');
      if (!mounted || request != _request) return;
      setState(() {
        _items = (data['notifications'] as List).cast<Map<String, dynamic>>();
        _unreadCount = data['unread_count'] as int? ?? 0;
        _loading = false;
      });
    } catch (e) {
      if (!mounted || request != _request) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _markRead(Map<String, dynamic> n) async {
    final id = n['id'].toString();
    if (n['read'] == true || _markingAll || !_reading.add(id)) return;
    try {
      await _api.post('notifications/$id/read', {});
      if (!mounted) return;
      setState(() {
        if (n['read'] != true) _unreadCount = (_unreadCount - 1).clamp(0, 999);
        n['read'] = true;
      });
      BuyerUpdates.inboxChanged();
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      _reading.remove(id);
    }
  }

  Future<void> _markAllRead() async {
    if (_markingAll || _reading.isNotEmpty) return;
    setState(() => _markingAll = true);
    try {
      await _api.post('notifications/read-all', {});
      if (!mounted) return;
      setState(() {
        for (final n in _items) {
          n['read'] = true;
        }
        _unreadCount = 0;
      });
      BuyerUpdates.inboxChanged();
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _markingAll = false);
    }
  }

  Future<void> _open(Map<String, dynamic> n) async {
    if (_opening || _markingAll) return;
    _opening = true;
    try {
      await _markRead(n);
      if (!mounted) return;
      final conversationId = n['conversation_id'] as int?;
      final orderId = n['order_id'] as int?;
      if (conversationId != null) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ChatThreadScreen(
              api: _api,
              conversationId: conversationId,
              subject: 'Messages',
            ),
          ),
        );
      } else if (orderId != null) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => OrderDetailScreen(api: _api, orderId: orderId),
          ),
        );
      } else {
        return;
      }
      if (mounted) await _load();
    } finally {
      _opening = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: 'Notifications',
      currentIndex: -1,
      showBottomNav: false,
      showTopActions: false,
      actions: [
        IconButton(
          tooltip: 'Refresh notifications',
          onPressed: _loading || _markingAll || _opening ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
      showNotificationIcon: false,
      showSettingsIcon: false,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? InboxStatus(message: _error!, onRetry: _load)
          : _items.isEmpty
          ? BuyerEmptyState(
              icon: Icons.notifications_none,
              title: 'No notifications yet.',
              description:
                  'Your order updates and message alerts will appear here.',
              action: TextButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh),
                label: const Text('Refresh'),
              ),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: Column(
                children: [
                  if (_unreadCount > 0)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.primary.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              '$_unreadCount unread',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                          const Spacer(),
                          TextButton.icon(
                            onPressed: _markingAll || _opening
                                ? null
                                : _markAllRead,
                            icon: const Icon(Icons.done_all, size: 16),
                            label: Text(
                              _markingAll ? 'Updating…' : 'Mark all as read',
                            ),
                            style: TextButton.styleFrom(
                              foregroundColor: AppColors.primary,
                              textStyle: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  Expanded(
                    child: ListView.separated(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: _items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) => _item(_items[i]),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _item(Map<String, dynamic> n) {
    final type = n['type'] as String;
    final read = n['read'] as bool? ?? false;

    final IconData icon;
    final Color color;
    switch (type) {
      case 'chat':
        icon = Icons.chat_bubble_outline;
        color = AppColors.primary;
        break;
      case 'review':
        icon = Icons.star_outline;
        color = AppColors.gold;
        break;
      default:
        icon = Icons.local_shipping_outlined;
        color = AppColors.primary;
    }

    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: () => _open(n),
      child: Container(
        decoration: BoxDecoration(
          color: read ? AppColors.card : AppColors.accent,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: read
                ? AppColors.border
                : AppColors.primary.withValues(alpha: 0.4),
          ),
        ),
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          n['title'] as String,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: read
                                ? FontWeight.w500
                                : FontWeight.w700,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      if (!read)
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: AppColors.primary,
                            shape: BoxShape.circle,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    n['body'] as String,
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _relativeTime(n['created_at'] as String),
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
      ),
    );
  }

  String _relativeTime(String iso) {
    try {
      final d = DateTime.parse(iso).toLocal();
      final diff = DateTime.now().difference(d);
      if (diff.inMinutes < 1) return 'Just now';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      if (diff.inDays < 7) return '${diff.inDays}d ago';
      return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    } catch (_) {
      return '';
    }
  }
}
