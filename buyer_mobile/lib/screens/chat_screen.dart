import '../widgets/buyer_dialog.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_page.dart';
import 'dart:async';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../models/chat.dart';
import '../widgets/chat_product_card.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../widgets/inbox_status.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import 'login_screen.dart';

/// Starts (or reuses) a conversation with a seller and opens the thread.
/// Used from the product page and the seller store page ("Chat with Seller").
Future<void> openSellerChat(
  BuildContext context, {
  required int sellerId,
  required String subject,
  required String initialBody,
  int? productId,
  int? variantId,
  ApiService? api,
}) async {
  final auth = AuthServiceProvider.of(context);
  if (!auth.isLoggedIn) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Please log in to chat with the seller.')),
    );
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
    return;
  }

  final client = api ?? ApiService();
  try {
    final r = await client.post('conversations', {
      'seller_id': sellerId,
      'subject': subject,
      'body': initialBody,
      if (productId != null) 'product_id': productId,
      if (variantId != null) 'variant_id': variantId,
    });
    BuyerUpdates.inboxChanged();
    final conv = r['conversation'] as Map<String, dynamic>;
    if (!context.mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatThreadScreen(
          api: client,
          conversationId: conv['id'] as int,
          subject: subject,
        ),
      ),
    );
  } catch (e) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(e.toString())));
  }
}

class ChatScreen extends StatefulWidget {
  final ApiService? api;
  const ChatScreen({super.key, this.api});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  late final ApiService _api;
  List<Conversation> _conversations = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _api.get('messages-center/inbox');
      final convList = data['conversations'] as List?;
      if (!mounted) return;
      setState(() {
        _conversations = (convList ?? [])
            .whereType<Map<String, dynamic>>()
            .map(Conversation.fromJson)
            .toList();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _newConversation() async {
    final conversation = await showCupertinoDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _NewMessageDialog(api: _api),
    );
    if (!mounted || conversation == null) return;
    BuyerUpdates.inboxChanged();
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatThreadScreen(
          api: _api,
          conversationId: conversation['id'] as int,
          subject: conversation['subject'] as String? ?? 'Support inquiry',
          threadKey: conversation['thread_key'] as String?,
        ),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: 'Messages',
      currentIndex: -1,
      showBottomNav: false,
      showTopActions: false,
      showNotificationIcon: false,
      actions: [
        BuyerButton.icon(
          tooltip: 'Refresh messages',
          onPressed: _loading ? null : _load,
          icon: const Icon(CupertinoIcons.refresh),
        ),
      ],
      child: _loading
          ? const Center(child: CupertinoActivityIndicator())
          : _error != null
          ? InboxStatus(message: _error!, onRetry: _load)
          : _conversations.isEmpty
          ? BuyerEmptyState(
              icon: CupertinoIcons.chat_bubble,
              title: 'No conversations yet.',
              description:
                  'Ask a seller about a product, or get help from support.',
              action: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  BuyerButton.plain(
                    onPressed: _load,
                    icon: const Icon(CupertinoIcons.refresh),
                    label: const Text('Refresh'),
                  ),
                  const SizedBox(height: 6),
                  BuyerButton.primary(
                    onPressed: _newConversation,
                    child: const Text('Start a Conversation'),
                  ),
                ],
              ),
            )
          : Column(
              children: [
                Expanded(
                  child: RefreshIndicator.adaptive(
                    onRefresh: _load,
                    child: ListView.separated(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: _conversations.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, i) =>
                          _conversationTile(_conversations[i]),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: BuyerButton.primary(
                    onPressed: _newConversation,
                    icon: const Icon(CupertinoIcons.add),
                    label: const Text('New Message'),
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size.fromHeight(44),
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _conversationTile(Conversation c) {
    return GestureDetector(
      onTap: () =>
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ChatThreadScreen(
                api: _api,
                conversationId: c.id,
                subject: c.displayName,
                threadKey: c.threadKey,
                canSend: c.canSend,
              ),
            ),
          ).then((_) {
            if (mounted) _load();
          }),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(12),
              ),
              alignment: Alignment.center,
              child: Text(
                c.initial,
                style: const TextStyle(
                  color: AppColors.primary,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          c.displayName,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (c.unreadCount > 0)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.primary,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '${c.unreadCount}',
                            style: const TextStyle(
                              fontSize: 10,
                              color: Colors.white,
                            ),
                          ),
                        ),
                    ],
                  ),
                  if (c.lastBody != null)
                    Text(
                      c.lastBody!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12,
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
}

class ChatThreadScreen extends StatefulWidget {
  final int conversationId;
  final String subject;
  final ApiService? api;
  final String? threadKey;
  final bool canSend;
  const ChatThreadScreen({
    super.key,
    required this.conversationId,
    required this.subject,
    this.api,
    this.threadKey,
    this.canSend = true,
  });

  @override
  State<ChatThreadScreen> createState() => _ChatThreadScreenState();
}

class _ChatThreadScreenState extends State<ChatThreadScreen>
    with WidgetsBindingObserver {
  late final ApiService _api;
  final _inputCtrl = TextEditingController();
  List<ChatMessage> _messages = [];
  late bool _canSend;
  bool _loading = true;
  String? _error, _sendError;
  final _scroll = ScrollController();
  bool _sending = false, _fetching = false;
  bool _hasOlder = false, _loadingOlder = false;
  int _page = 1;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    _canSend = widget.canSend;
    _api = widget.api ?? ApiService();
    WidgetsBinding.instance.addObserver(this);
    _refreshTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted &&
          ModalRoute.of(context)?.isCurrent == true &&
          WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) {
        _load(silent: true);
      }
    });
    _load();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        mounted &&
        ModalRoute.of(context)?.isCurrent == true) {
      _load(silent: true);
    }
  }

  Future<void> _load({bool silent = false}) async {
    if (!mounted || _fetching || _sending || (silent && _page > 1)) return;
    _fetching = true;
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    final nearBottom = !_scroll.hasClients || _scroll.position.extentAfter < 80;
    try {
      final data = await _api.get(
        widget.threadKey == null
            ? 'conversations/${widget.conversationId}/messages'
            : 'messages-center/threads/${widget.threadKey}',
      );
      if (!mounted) return;
      final incoming = (data['messages'] as List)
          .whereType<Map<String, dynamic>>()
          .map(ChatMessage.fromJson)
          .toList();
      if (widget.threadKey != null) {
        incoming.sort((a, b) => a.id.compareTo(b.id));
      }
      final changed =
          incoming.length != _messages.length ||
          (incoming.isNotEmpty &&
              incoming.last.id !=
                  (_messages.isEmpty ? null : _messages.last.id));
      // Merge a reply that completed after this refresh started.
      final receivedIds = incoming.map((m) => m.id).toSet();
      final newestId = incoming.isEmpty ? 0 : incoming.last.id;
      final newer = _messages.where(
        (m) => m.id > newestId && !receivedIds.contains(m.id),
      );
      setState(() {
        _canSend = data['can_send'] as bool? ?? _canSend;
        _messages = [...incoming, ...newer];
        if (widget.threadKey != null) {
          _hasOlder = (data['pagination']?['last_page'] as int? ?? 1) > 1;
          _page = 1;
        }
        _loading = false;
        _error = null;
      });
      if (changed) BuyerUpdates.inboxChanged();
      if (nearBottom && changed) _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    } finally {
      _fetching = false;
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.jumpTo(_scroll.position.maxScrollExtent);
    });
  }

  Future<void> _loadOlder() async {
    if (widget.threadKey == null || _loadingOlder) return;
    setState(() => _loadingOlder = true);
    try {
      final result = await _api.get(
        'messages-center/threads/${widget.threadKey}',
        query: {'page': '${_page + 1}'},
      );
      if (!mounted) return;
      final older = (result['messages'] as List)
          .whereType<Map<String, dynamic>>()
          .map(ChatMessage.fromJson)
          .toList()
          .reversed;
      final known = _messages.map((message) => message.id).toSet();
      setState(() {
        _messages.insertAll(
          0,
          older.where((message) => !known.contains(message.id)),
        );
        _page = result['pagination']['current_page'] as int;
        _hasOlder = _page < (result['pagination']['last_page'] as int);
      });
    } catch (error) {
      if (mounted) setState(() => _sendError = error.toString());
    } finally {
      if (mounted) setState(() => _loadingOlder = false);
    }
  }

  Future<void> _send() async {
    if (!_canSend) return;
    final text = _inputCtrl.text.trim();
    if (text.isEmpty || _sending || _loading) return;
    if (text.length > 2000) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keep messages under 2,000 characters.')),
      );
      return;
    }
    setState(() {
      _sending = true;
      _sendError = null;
    });
    try {
      final r = await _api.post(
        widget.threadKey == null
            ? 'conversations/${widget.conversationId}/reply'
            : 'messages-center/threads/${widget.threadKey}',
        {'body': text},
      );
      if (!mounted) return;
      final message = ChatMessage.fromJson(
        (r['message'] ?? r['data']) as Map<String, dynamic>,
      );
      setState(() {
        if (!_messages.any((m) => m.id == message.id)) _messages.add(message);
        _error = null;
        _inputCtrl.clear();
      });
      BuyerUpdates.inboxChanged();
      _scrollToBottom();
    } catch (e) {
      if (mounted) setState(() => _sendError = e.toString());
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _inputCtrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: widget.subject,
      currentIndex: -1,
      showBottomNav: false,
      showTopActions: false,
      showNotificationIcon: false,
      actions: [
        BuyerButton.icon(
          tooltip: 'Refresh messages',
          onPressed: _loading || _sending ? null : () => _load(),
          icon: const Icon(CupertinoIcons.refresh),
        ),
      ],
      child: Column(
        children: [
          if (_hasOlder)
            TextButton(
              onPressed: _loadingOlder ? null : _loadOlder,
              child: const Text('Load earlier messages'),
            ),
          Expanded(
            child: _loading
                ? const Center(child: CupertinoActivityIndicator())
                : _error != null && _messages.isEmpty
                ? InboxStatus(message: _error!, onRetry: () => _load())
                : _messages.isEmpty
                ? const BuyerEmptyState(
                    icon: CupertinoIcons.chat_bubble_2,
                    title: 'No messages yet. Say hello!',
                    description:
                        'Start a conversation using the message box below.',
                  )
                : ListView.builder(
                    controller: _scroll,
                    padding: const EdgeInsets.all(12),
                    itemCount: _messages.length,
                    itemBuilder: (context, i) => _bubble(_messages[i]),
                  ),
          ),
          if (_sendError != null)
            Semantics(
              liveRegion: true,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
                child: Text(
                  _sendError!,
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
            ),
          Container(
            color: AppColors.card,
            padding: const EdgeInsets.all(8),
            child: SafeArea(
              child: Row(
                children: [
                  Expanded(
                    child: BuyerField(
                      controller: _inputCtrl,
                      readOnly: _sending || !_canSend,
                      maxLength: 2000,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: InputDecoration(
                        hintText: _canSend
                            ? 'Type a message...'
                            : 'Conversation is read only',
                        counterText: '',
                        contentPadding: EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  BuyerButton.primary(
                    onPressed: _sending || _loading || !_canSend ? null : _send,
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size(46, 46),
                      padding: EdgeInsets.zero,
                      shape: const CircleBorder(),
                    ),
                    child: _sending
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CupertinoActivityIndicator(),
                          )
                        : const Icon(CupertinoIcons.arrow_up),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bubble(ChatMessage m) {
    final myId = AuthServiceProvider.of(context).user?.id;
    final mine = m.senderId == myId;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width.clamp(0, 760) * .78,
        ),
        decoration: BoxDecoration(
          color: mine ? AppColors.primary : AppColors.card,
          border: Border.all(
            color: mine ? AppColors.primary : AppColors.border,
          ),
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(14),
            topRight: const Radius.circular(14),
            bottomLeft: Radius.circular(mine ? 14 : 4),
            bottomRight: Radius.circular(mine ? 4 : 14),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!mine)
              Text(
                m.senderName ?? '',
                style: TextStyle(
                  fontSize: 10,
                  color: mine ? Colors.white70 : AppColors.primary,
                ),
              ),
            const SizedBox(height: 2),
            if (m.productCard != null) ...[
              ChatProductCard(card: m.productCard!, api: _api),
              const SizedBox(height: 8),
            ],
            Text(
              m.body,
              style: TextStyle(
                fontSize: 14,
                height: 1.6,
                color: mine ? Colors.white : AppColors.textPrimary,
              ),
            ),
            for (final attachment in m.attachments)
              if (attachment['is_image'] == true)
                FutureBuilder<Uint8List>(
                  future: _api.getImage(
                    'messaging/attachments/${attachment['id']}',
                  ),
                  builder: (context, snapshot) => snapshot.hasData
                      ? Image.memory(
                          snapshot.data!,
                          width: 180,
                          fit: BoxFit.contain,
                        )
                      : Text(attachment['name'] as String? ?? 'Attachment'),
                )
              else
                Text(attachment['name'] as String? ?? 'Attachment'),
            const SizedBox(height: 2),
            Text(
              _time(m.createdAt),
              style: TextStyle(
                fontSize: 11.5,
                color: mine ? Colors.white60 : AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _time(String iso) {
    try {
      final d = DateTime.parse(iso).toLocal();
      return '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return '';
    }
  }
}

class _NewMessageDialog extends StatefulWidget {
  final ApiService api;
  const _NewMessageDialog({required this.api});
  @override
  State<_NewMessageDialog> createState() => _NewMessageDialogState();
}

class _NewMessageDialogState extends State<_NewMessageDialog> {
  final _form = GlobalKey<FormState>();
  final _subject = TextEditingController(), _body = TextEditingController();
  bool _sending = false;
  String? _error;
  List<Map<String, dynamic>> _contacts = [];
  String? _recipient;

  @override
  void initState() {
    super.initState();
    _loadContacts();
  }

  Future<void> _loadContacts() async {
    try {
      final result = await widget.api.get('messages-center/contacts');
      if (!mounted) return;
      setState(() {
        _contacts = (result['contacts'] as List)
            .whereType<Map<String, dynamic>>()
            .toList();
      });
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    }
  }

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_sending || !_form.currentState!.validate()) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      if (_recipient != null) {
        final thread = await widget.api.post('messages-center/start', {
          'recipient': _recipient,
        });
        final key = thread['thread_key'] as String;
        await widget.api.post('messages-center/threads/$key', {
          'body': _body.text.trim(),
        });
        if (!mounted) return;
        final contact = _contacts.firstWhere(
          (contact) => contact['key'] == _recipient,
        );
        Navigator.pop(context, <String, dynamic>{
          'id': int.parse(key.split(':').last),
          'thread_key': key,
          'subject': contact['name'],
        });
        return;
      }
      final result = await widget.api.post('conversations', {
        'subject': _subject.text.trim().isEmpty
            ? 'Support inquiry'
            : _subject.text.trim(),
        'body': _body.text.trim(),
      });
      if (mounted) {
        Navigator.pop(context, result['conversation'] as Map<String, dynamic>);
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_sending,
    child: BuyerAlertDialog(
      title: const Text('New message'),
      content: SingleChildScrollView(
        child: Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'For a product question, use Chat with seller on the product or store page.',
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _recipient,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Contact'),
                hint: const Text('Platform support'),
                items: _contacts
                    .map(
                      (contact) => DropdownMenuItem<String>(
                        value: contact['key'] as String,
                        child: Text(
                          contact['name'] as String,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: _sending
                    ? null
                    : (value) => setState(() => _recipient = value),
              ),
              const SizedBox(height: 12),
              BuyerField.form(
                controller: _subject,
                enabled: !_sending,
                maxLength: 150,
                decoration: const InputDecoration(
                  labelText: 'Subject (optional)',
                ),
              ),
              const SizedBox(height: 10),
              BuyerField.form(
                controller: _body,
                enabled: !_sending,
                maxLines: 3,
                maxLength: 2000,
                decoration: const InputDecoration(labelText: 'Message'),
                validator: (v) =>
                    (v ?? '').trim().isEmpty ? 'Enter a message.' : null,
              ),
              if (_error != null)
                Text(_error!, style: const TextStyle(color: AppColors.danger)),
            ],
          ),
        ),
      ),
      actions: [
        BuyerDialogAction(
          onPressed: _sending ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        BuyerDialogAction(
          onPressed: _sending ? null : _send,
          child: Text(_sending ? 'Sending…' : 'Send'),
        ),
      ],
    ),
  );
}
