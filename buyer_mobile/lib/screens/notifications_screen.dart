import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';
import 'order_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  int _unreadCount = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _api.get('notifications');
      setState(() {
        _items = (data['notifications'] as List).cast<Map<String, dynamic>>();
        _unreadCount = data['unread_count'] as int? ?? 0;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _markRead(Map<String, dynamic> n) async {
    if (n['read'] == true) return;
    try {
      await _api.post('notifications/${n['id']}/read', {});
      setState(() {
        n['read'] = true;
        _unreadCount = (_unreadCount - 1).clamp(0, 999);
      });
    } catch (_) {}
  }

  Future<void> _markAllRead() async {
    try {
      await _api.post('notifications/read-all', {});
      setState(() {
        for (final n in _items) {
          n['read'] = true;
        }
        _unreadCount = 0;
      });
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: 'Notifications',
      currentIndex: -1,
      showNotificationIcon: false,
      showSettingsIcon: false,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.notifications_none,
                    size: 64,
                    color: AppColors.textSecondary,
                  ),
                  SizedBox(height: 10),
                  Text(
                    'No notifications yet.',
                    style: TextStyle(color: AppColors.textSecondary),
                  ),
                ],
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
                            onPressed: _markAllRead,
                            icon: const Icon(Icons.done_all, size: 16),
                            label: const Text('Mark all as read'),
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
                      padding: const EdgeInsets.all(12),
                      itemCount: _items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
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
    final orderId = n['order_id'] as int?;
    final read = n['read'] as bool? ?? false;

    final IconData icon;
    final Color color;
    switch (type) {
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
      onTap: () {
        _markRead(n);
        if (orderId != null) {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(orderId: orderId),
            ),
          );
        }
      },
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
                            color: const Color(0xFF212121),
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
                      fontSize: 12.5,
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
