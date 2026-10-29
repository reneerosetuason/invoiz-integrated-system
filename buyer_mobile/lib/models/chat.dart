class Conversation {
  final int id;
  final String subject;
  final int unreadCount;
  final String? lastBody;
  final String? lastSender;
  final String? lastTime;
  final int? sellerId;
  final String? sellerName;
  final String? sellerBusinessName;

  Conversation({
    required this.id,
    required this.subject,
    this.unreadCount = 0,
    this.lastBody,
    this.lastSender,
    this.lastTime,
    this.sellerId,
    this.sellerName,
    this.sellerBusinessName,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) {
    final last = json['last_message'];
    Map<String, dynamic>? lastMsg;
    if (last is Map<String, dynamic>) {
      lastMsg = last;
    }
    final sender = lastMsg?['sender'];
    final seller = json['seller'];
    String? sellerBusinessName;
    String? sellerName;
    if (seller is Map<String, dynamic>) {
      final sellerRow = seller['seller'];
      if (sellerRow is Map<String, dynamic> &&
          sellerRow['business_name'] != null) {
        sellerBusinessName = sellerRow['business_name'] as String;
      }
      sellerName = seller['first_name'] as String?;
    }
    return Conversation(
      id: json['id'] as int,
      subject: json['subject'] as String? ?? 'Chat',
      unreadCount: (json['unread_count'] as int?) ?? 0,
      lastBody: lastMsg?['body'] as String?,
      lastSender: sender is Map<String, dynamic>
          ? sender['first_name'] as String?
          : null,
      lastTime: lastMsg?['created_at'] as String?,
      sellerId: json['seller_id'] as int?,
      sellerName: sellerName,
      sellerBusinessName: sellerBusinessName,
    );
  }

  /// Display name for the thread: the seller's shop name when this is a
  /// seller chat, otherwise the subject.
  String get displayName => sellerBusinessName ?? subject;

  String get initial {
    final name = sellerBusinessName ?? subject;
    return name.trim().isEmpty
        ? 'I'
        : name.trim().substring(0, 1).toUpperCase();
  }
}

class ChatMessage {
  final ChatProduct? productCard;
  final int id;
  final int conversationId;
  final int senderId;
  final String body;
  final bool isRead;
  final String createdAt;
  final String? senderName;

  ChatMessage({
    this.productCard,
    required this.id,
    required this.conversationId,
    required this.senderId,
    required this.body,
    this.isRead = false,
    required this.createdAt,
    this.senderName,
  });

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'];
    return ChatMessage(
      productCard: json['product_card'] is Map<String, dynamic>
          ? ChatProduct.fromJson(json['product_card'] as Map<String, dynamic>)
          : null,
      id: json['id'] as int,
      conversationId: json['conversation_id'] as int,
      senderId: json['sender_id'] as int,
      body: json['body'] as String,
      isRead: json['is_read'] as bool? ?? false,
      createdAt: json['created_at'] as String,
      senderName: sender is Map<String, dynamic>
          ? (sender['first_name'] as String? ?? 'User')
          : 'User',
    );
  }
}

class ChatProduct {
  final int id;
  final String name;
  final String? image;
  final double price;
  final String? option;

  const ChatProduct({
    required this.id,
    required this.name,
    this.image,
    required this.price,
    this.option,
  });

  factory ChatProduct.fromJson(Map<String, dynamic> json) => ChatProduct(
    id: (json['id'] as num).toInt(),
    name: json['name'] as String,
    image: json['image'] as String?,
    price: double.tryParse('${json['price']}') ?? 0,
    option: json['option'] as String?,
  );
}
