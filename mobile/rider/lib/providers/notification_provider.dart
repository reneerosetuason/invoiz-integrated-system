import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/rider_notification.dart';
import '../services/notification_service.dart';

/// Notification list state.
class NotificationProvider extends ChangeNotifier {
  NotificationProvider(this._service);

  final NotificationService _service;

  List<RiderNotification> _notifications = [];
  bool _loading = false;
  String? _error;
  int _unread = 0;

  List<RiderNotification> get notifications => _notifications;
  int get unread => _unread;
  bool get loading => _loading;
  String? get error => _error;

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      _notifications = await _service.list();
      _unread = _notifications.where((n) => !n.isRead).length;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Unable to load notifications. Please try again.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> markRead(int id) async {
    try {
      await _service.markRead(id);
      final index = _notifications.indexWhere((n) => n.id == id);
      if (index != -1) {
        _notifications[index] = _notifications[index].copyWith(isRead: true);
      }
      _recountUnread();
      notifyListeners();
    } on ApiException {
      // Ignore; the user can retry from the pull-to-refresh.
    }
  }

  Future<void> markAllRead() async {
    try {
      await _service.markAllRead();
      _notifications = _notifications
          .map((n) => n.copyWith(isRead: true))
          .toList();
      _recountUnread();
      notifyListeners();
    } on ApiException {
      // Ignore.
    }
  }

  void _recountUnread() {
    _unread = _notifications.where((n) => !n.isRead).length;
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}