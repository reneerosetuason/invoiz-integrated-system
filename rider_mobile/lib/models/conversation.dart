/// A rider conversation thread from `GET /api/rider/conversations`.
///
/// Threads appear automatically from real relationships (logistics support,
/// per-delivery buyer/seller threads) — the rider never picks recipients.
class ConversationThread {
  const ConversationThread({
    required this.id,
    required this.name,
    required this.type,
    this.typeLabel,
    this.orderId,
    this.tracking,
    this.preview,
    this.unread = 0,
    this.lastMessageAt,
  });

  final int id;
  final String name;
  final String type;
  final String? typeLabel;
  final int? orderId;
  final String? tracking;
  final String? preview;
  final int unread;
  final DateTime? lastMessageAt;

  /// Human context line under the name (role + order/tracking when known).
  String get contextLine {
    final role = (typeLabel ?? type).isEmpty
        ? ''
        : _titleCase(typeLabel ?? type);
    final ref = tracking ??
        (orderId != null ? 'Order #$orderId' : null);
    if (role.isEmpty) return ref ?? '';
    if (ref == null || ref.isEmpty) return role;
    return '$role · $ref';
  }

  static String _titleCase(String value) {
    if (value.isEmpty) return value;
    return value[0].toUpperCase() + value.substring(1).toLowerCase();
  }

  factory ConversationThread.fromJson(Map<String, dynamic> json) {
    return ConversationThread(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name'] as String? ?? '',
      type: json['type'] as String? ?? '',
      typeLabel: json['type_label'] as String?,
      orderId: (json['order_id'] as num?)?.toInt(),
      tracking: json['tracking'] as String?,
      preview: json['preview'] as String?,
      unread: (json['unread'] as num?)?.toInt() ?? 0,
      lastMessageAt: json['last_message_at'] != null
          ? DateTime.tryParse(json['last_message_at'].toString())?.toLocal()
          : null,
    );
  }
}

/// A page of inbox threads.
class ConversationPage {
  const ConversationPage({required this.threads, required this.hasMore});

  final List<ConversationThread> threads;
  final bool hasMore;
}

/// One thread's messages with paging info (API returns newest-first).
class ThreadMessages {
  const ThreadMessages({
    required this.messages,
    required this.currentPage,
    required this.lastPage,
  });

  final List<Map<String, dynamic>> messages;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}
