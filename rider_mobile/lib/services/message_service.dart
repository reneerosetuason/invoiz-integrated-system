import 'dart:typed_data';

import '../core/network/api_client.dart';
import '../models/conversation.dart';

class RiderMessage {
  const RiderMessage({
    required this.id,
    required this.mine,
    required this.senderType,
    required this.body,
    required this.createdAt,
    required this.time,
    required this.dayLabel,
    this.isRead = false,
    this.deleted = false,
    this.attachments = const [],
  });
  final int id;
  final bool mine;
  final String senderType;
  final String body;
  final String createdAt;
  final String time;
  final String dayLabel;
  final bool isRead;
  final bool deleted;
  final List<MessageAttachment> attachments;
  factory RiderMessage.fromJson(Map<String, dynamic> j) => RiderMessage(
        id: (j['id'] as num?)?.toInt() ?? 0,
        mine: j['mine'] as bool? ?? false,
        senderType: j['sender_type'] as String? ?? 'rider',
        body: j['body'] as String? ?? '',
        createdAt: j['created_at'] as String? ?? '',
        time: j['time'] as String? ?? '',
        dayLabel: j['dayLabel'] as String? ?? '',
        isRead: j['is_read'] as bool? ?? false,
        deleted: j['deleted'] as bool? ?? false,
        attachments: (j['attachments'] as List? ?? []).whereType<Map<String, dynamic>>().map(MessageAttachment.fromJson).toList(),
      );
}

class MessageAttachment {
  const MessageAttachment({required this.id, required this.name, required this.size, required this.url, this.isImage = false});
  final int id;
  final String name;
  final String size;
  final String url;
  final bool isImage;
  factory MessageAttachment.fromJson(Map<String, dynamic> j) => MessageAttachment(
        id: (j['id'] as num?)?.toInt() ?? 0,
        name: j['name'] as String? ?? '',
        size: j['size'] as String? ?? '',
        url: j['url'] as String? ?? '',
        isImage: j['is_image'] as bool? ?? false,
      );
}

class ConversationHeader {
  const ConversationHeader({
    required this.id,
    this.subject,
    this.recipientName,
    this.recipientType,
    this.recipientLabel,
    this.tracking,
  });

  final int id;
  final String? subject;
  final String? recipientName;
  final String? recipientType;
  final String? recipientLabel;
  final String? tracking;

  factory ConversationHeader.fromJson(Map<String, dynamic> j) =>
      ConversationHeader(
        id: (j['id'] as num?)?.toInt() ?? 0,
        subject: j['subject'] as String?,
        recipientName: j['recipient_name'] as String?,
        recipientType: j['recipient_type'] as String?,
        recipientLabel: j['recipient_label'] as String?,
        tracking: j['tracking'] as String?,
      );

  /// Name shown in the header (who the rider is messaging).
  String get displayName => recipientName ?? 'Logistics';
}

class MessageService {
  MessageService(this._api);
  final ApiClient _api;
  ConversationHeader? conversation;

  Future<List<RiderMessage>> getMessages() async {
    final data = await _api.get('/rider/messages');
    if (data['conversation'] is Map<String, dynamic>) {
      conversation = ConversationHeader.fromJson(
        Map<String, dynamic>.from(data['conversation'] as Map),
      );
    }
    final list = data['messages'] as List? ?? [];
    return list.whereType<Map<String, dynamic>>().map(RiderMessage.fromJson).toList();
  }

  Future<List<RiderMessage>> poll({int? after}) async {
    final data = await _api.get('/rider/messages/poll', query: after != null ? {'after': after.toString()} : null);
    final list = data['messages'] as List? ?? [];
    return list.whereType<Map<String, dynamic>>().map(RiderMessage.fromJson).toList();
  }

  Future<RiderMessage> send({required String body, Uint8List? fileBytes, String? filename}) async {
    if (fileBytes != null && filename != null) {
      final data = await _api.postMultipart('/rider/messages', fileField: 'attachment', fileBytes: fileBytes, filename: filename, fields: {'body': body});
      return RiderMessage.fromJson(Map<String, dynamic>.from(data['data'] as Map));
    }
    final data = await _api.post('/rider/messages', body: {'body': body});
    return RiderMessage.fromJson(Map<String, dynamic>.from(data['data'] as Map));
  }

  /// Inbox threads auto-discovered from the rider's real relationships
  /// (support + per-delivery buyer/seller threads). No recipient picking.
  Future<ConversationPage> listConversations({String? search, int page = 1}) async {
    final query = <String, dynamic>{'page': page.toString()};
    if (search != null && search.trim().isNotEmpty) {
      query['search'] = search.trim();
    }
    final data = await _api.get('/rider/conversations', query: query);
    final list = data['conversations'] as List? ?? [];
    final threads = list
        .whereType<Map<String, dynamic>>()
        .map(ConversationThread.fromJson)
        .toList();
    final pagination = data['pagination'] as Map<String, dynamic>?;
    final current = (pagination?['current_page'] as num?)?.toInt() ?? 1;
    final last = (pagination?['last_page'] as num?)?.toInt() ?? 1;
    return ConversationPage(threads: threads, hasMore: current < last);
  }

  /// One thread's messages (API returns newest-first; callers display
  /// chronologically). Opening marks the thread read server-side.
  Future<ThreadMessages> getThread(int id, {int page = 1}) async {
    final data = await _api.get('/rider/conversations/$id', query: {'page': page.toString()});
    final list = data['messages'] as List? ?? [];
    final pagination = data['pagination'] as Map<String, dynamic>?;
    return ThreadMessages(
      messages: list.whereType<Map<String, dynamic>>().toList(),
      currentPage: (pagination?['current_page'] as num?)?.toInt() ?? 1,
      lastPage: (pagination?['last_page'] as num?)?.toInt() ?? 1,
    );
  }

  /// Send into a specific thread. Returns the created message payload.
  Future<Map<String, dynamic>> sendToThread(int id,
      {required String body, Uint8List? fileBytes, String? filename}) async {
    if (fileBytes != null && filename != null) {
      final data = await _api.postMultipart('/rider/conversations/$id/messages',
          fileField: 'attachment', fileBytes: fileBytes, filename: filename, fields: {'body': body});
      return Map<String, dynamic>.from(data['data'] as Map);
    }
    final data = await _api.post('/rider/conversations/$id/messages', body: {'body': body});
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  /// Edit own message in a thread (server enforces ownership).
  Future<void> editThreadMessage(int threadId, int messageId, String body) async {
    await _api.patch('/rider/conversations/$threadId/messages/$messageId',
        body: {'body': body});
  }

  /// Soft-delete own message in a thread (server enforces ownership).
  Future<void> deleteThreadMessage(int threadId, int messageId) async {
    await _api.delete('/rider/conversations/$threadId/messages/$messageId');
  }
}
