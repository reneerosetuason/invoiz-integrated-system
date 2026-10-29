/// A rider notification from `/api/rider/notifications`.
class RiderNotification {
  const RiderNotification({
    required this.id,
    required this.type,
    required this.title,
    this.body,
    required this.isRead,
    this.createdAt,
    this.data,
  });

  final int id;
  final String type;
  final String title;
  final String? body;
  final bool isRead;
  final DateTime? createdAt;
  final Map<String, dynamic>? data;

  factory RiderNotification.fromJson(Map<String, dynamic> json) {
    return RiderNotification(
      id: (json['id'] as num?)?.toInt() ?? 0,
      type: json['type'] as String? ?? 'system',
      title: json['title'] as String? ?? '',
      body: json['body'] as String?,
      isRead: json['is_read'] as bool? ?? false,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())?.toLocal()
          : null,
      data: json['data'] is Map<String, dynamic>
          ? json['data'] as Map<String, dynamic>
          : null,
    );
  }

  RiderNotification copyWith({bool? isRead}) {
    return RiderNotification(
      id: id,
      type: type,
      title: title,
      body: body,
      isRead: isRead ?? this.isRead,
      createdAt: createdAt,
      data: data,
    );
  }
}