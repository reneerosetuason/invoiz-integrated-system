import 'dart:async';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/motion.dart';
import '../../models/conversation.dart';
import '../../widgets/app_ui.dart';
import '../../providers/auth_provider.dart';
import '../../services/message_service.dart';

/// Rider chat screen.
///
/// Without [thread] it keeps the legacy behavior: the single Logistics
/// support thread via the dedicated endpoints. With [thread] it shows any
/// inbox thread (support/buyer/seller) through the conversations endpoints.
/// Either way the rider only ever opens threads the backend authorized.
class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key, this.thread});

  final ConversationThread? thread;

  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  List<RiderMessage> _messages = [];
  ConversationHeader? _header;
  bool _loading = true;
  bool _sending = false;
  Timer? _poll;
  // Pre-send attachment staging (preview + remove before sending).
  Uint8List? _pendingBytes;
  String? _pendingName;
  // Message being edited (thread mode only).
  RiderMessage? _editing;
  int _threadPage = 1;
  bool _threadHasMore = false;
  bool _loadingOlder = false;

  @override
  void initState() {
    super.initState();
    if (widget.thread != null) {
      _loadThread();
    } else {
      _load();
    }
    _poll = Timer.periodic(const Duration(seconds: 5), (_) => _pollNew());
  }

  MessageService _svc() {
    final auth = context.read<AuthProvider>();
    // Reuse the auth provider's ApiClient so the bearer token is attached.
    return MessageService(auth.api);
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final svc = _svc();
      final list = await svc.getMessages();
      if (mounted) {
        setState(() {
          _header = svc.conversation;
          _messages = list;
          _loading = false;
        });
      }
      _jump();
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pollNew() async {
    if (widget.thread != null) {
      await _pollThread();
      return;
    }
    if (_messages.isEmpty) return;
    try {
      final svc = _svc();
      final after = _messages.isNotEmpty ? _messages.last.id : null;
      final more = await svc.poll(after: after);
      if (more.isNotEmpty && mounted) {
        setState(() => _messages.addAll(more));
        _jump();
      }
    } catch (_) {}
  }

  /// Thread mode: full reload of the newest page, then merge only unseen
  /// messages so loaded-older history and scroll position survive polling.
  Future<void> _pollThread() async {
    final thread = widget.thread;
    if (thread == null || _messages.isEmpty) return;
    try {
      final res = await _svc().getThread(thread.id);
      if (!mounted) return;
      final fresh = res.messages.reversed
          .map(RiderMessage.fromJson)
          .toList();
      if (fresh.isEmpty) return;
      final known = _messages.map((m) => m.id).toSet();
      final incoming =
          fresh.where((m) => !known.contains(m.id)).toList();
      if (incoming.isEmpty) return;
      final nearBottom = _scroll.hasClients &&
          (_scroll.position.maxScrollExtent - _scroll.position.pixels) < 120;
      setState(() => _messages.addAll(incoming));
      if (nearBottom) _jump();
    } catch (_) {}
  }

  /// Thread mode initial load (API returns newest-first).
  Future<void> _loadThread() async {
    final thread = widget.thread;
    if (thread == null) return;
    setState(() => _loading = true);
    try {
      final res = await _svc().getThread(thread.id);
      if (!mounted) return;
      setState(() {
        _header = ConversationHeader(
          id: thread.id,
          recipientName: thread.name,
          recipientType: thread.type,
          recipientLabel: thread.contextLine.isEmpty
              ? 'Conversation'
              : thread.contextLine,
        );
        _messages = res.messages.reversed
            .map(RiderMessage.fromJson)
            .toList();
        _threadPage = res.currentPage;
        _threadHasMore = res.hasMore;
        _loading = false;
      });
      _jump();
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadOlder() async {
    final thread = widget.thread;
    if (thread == null || !_threadHasMore || _loadingOlder) return;
    setState(() => _loadingOlder = true);
    try {
      final res = await _svc().getThread(thread.id, page: _threadPage + 1);
      if (!mounted) return;
      setState(() {
        _messages.insertAll(
          0,
          res.messages.reversed.map(RiderMessage.fromJson),
        );
        _threadPage = res.currentPage;
        _threadHasMore = res.hasMore;
        _loadingOlder = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingOlder = false);
    }
  }

  void _jump() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) _scroll.jumpTo(_scroll.position.maxScrollExtent);
      });

  Future<void> _send() async {
    if (_sending) return;
    final text = _input.text.trim();
    final bytes = _pendingBytes;
    final name = _pendingName;
    if (text.isEmpty && bytes == null) return;
    if (_editing != null) {
      await _saveEdit(text);
      return;
    }
    setState(() => _sending = true);
    try {
      final svc = _svc();
      final thread = widget.thread;
      final RiderMessage msg;
      if (thread != null) {
        msg = RiderMessage.fromJson(await svc.sendToThread(
          thread.id,
          body: text.isEmpty ? '(attachment)' : text,
          fileBytes: bytes,
          filename: name,
        ));
      } else {
        msg = await svc.send(
          body: text.isEmpty ? '(attachment)' : text,
          fileBytes: bytes,
          filename: name,
        );
      }
      _input.clear();
      if (mounted) {
        setState(() {
          _clearPending();
          _messages.add(msg);
        });
      }
      _jump();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _clearPending() {
    _pendingBytes = null;
    _pendingName = null;
  }

  bool get _pendingIsImage {
    final name = _pendingName;
    if (name == null) return false;
    final lower = name.toLowerCase();
    return lower.endsWith('.jpg') ||
        lower.endsWith('.jpeg') ||
        lower.endsWith('.png') ||
        lower.endsWith('.webp');
  }

  Future<void> _showAttachMenu() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_outlined),
              title: const Text('Photo'),
              subtitle: const Text('Gallery image',
                  style: TextStyle(fontSize: 12)),
              onTap: () => Navigator.of(ctx).pop('photo'),
            ),
            ListTile(
              leading: const Icon(Icons.attach_file_outlined),
              title: const Text('File'),
              subtitle: const Text(
                  'JPG, PNG, PDF, DOC, XLS, CSV up to 5 MB',
                  style: TextStyle(fontSize: 12)),
              onTap: () => Navigator.of(ctx).pop('file'),
            ),
          ],
        ),
      ),
    );
    if (choice == 'photo') {
      await _pickPhoto();
    } else if (choice == 'file') {
      await _pickFile();
    }
  }

  Future<void> _pickPhoto() async {
    final picker = ImagePicker();
    final file = await picker.pickImage(
        source: ImageSource.gallery, maxWidth: 1600, imageQuality: 80);
    if (file == null) return;
    final bytes = await file.readAsBytes();
    if (!mounted) return;
    setState(() {
      _pendingBytes = bytes;
      _pendingName = file.name;
    });
  }

  Future<void> _pickFile() async {
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'jpg', 'jpeg', 'png', 'webp', 'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'csv',
      ],
    );
    if (files.isEmpty) return;
    final file = files.first;
    final bytes = await file.readAsBytes();
    if (bytes.lengthInBytes > 5 * 1024 * 1024) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('File must not exceed 5 MB.')));
      return;
    }
    if (!mounted) return;
    setState(() {
      _pendingBytes = bytes;
      _pendingName = file.name;
    });
  }

  void _startEdit(RiderMessage message) {
    setState(() => _editing = message);
    _input.text = message.body;
  }

  void _cancelEdit() {
    setState(() => _editing = null);
    _input.clear();
  }

  Future<void> _saveEdit(String text) async {
    final editing = _editing;
    final thread = widget.thread;
    if (editing == null || thread == null || text.isEmpty) return;
    setState(() => _sending = true);
    try {
      await _svc().editThreadMessage(thread.id, editing.id, text);
      if (!mounted) return;
      setState(() {
        final i = _messages.indexWhere((m) => m.id == editing.id);
        if (i != -1) {
          final m = _messages[i];
          _messages[i] = RiderMessage(
            id: m.id,
            mine: m.mine,
            senderType: m.senderType,
            body: text,
            createdAt: m.createdAt,
            time: m.time,
            dayLabel: m.dayLabel,
            isRead: m.isRead,
            deleted: m.deleted,
            attachments: m.attachments,
          );
        }
        _editing = null;
      });
      _input.clear();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _deleteMessage(RiderMessage message) async {
    final thread = widget.thread;
    if (thread == null) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete message?'),
        content:
            const Text('This message will be removed for everyone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Delete',
                style: TextStyle(color: AppColors.warning)),
          ),
        ],
      ),
    );
    if (confirm != true || !mounted) return;
    try {
      await _svc().deleteThreadMessage(thread.id, message.id);
      if (!mounted) return;
      setState(() {
        final i = _messages.indexWhere((m) => m.id == message.id);
        if (i != -1) {
          final m = _messages[i];
          _messages[i] = RiderMessage(
            id: m.id,
            mine: m.mine,
            senderType: m.senderType,
            body: '',
            createdAt: m.createdAt,
            time: m.time,
            dayLabel: m.dayLabel,
            isRead: m.isRead,
            deleted: true,
            attachments: const [],
          );
        }
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  void _viewAttachment(MessageAttachment a) {
    showDialog<void>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.transparent,
        child: GestureDetector(
          onTap: () => Navigator.of(ctx).pop(),
          child: InteractiveViewer(
            child: Image.network(
              a.url,
              fit: BoxFit.contain,
              loadingBuilder: (_, child, progress) =>
                  progress == null ? child : const Center(child: CircularProgressIndicator()),
              errorBuilder: (_, _, _) => const Center(
                child: Text('Unable to load attachment',
                    style: TextStyle(color: Colors.white)),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _attachmentTile(MessageAttachment a, bool mine) {
    final isImage = a.isImage && a.url.isNotEmpty;
    final linkStyle = TextStyle(
      fontSize: 12,
      color: mine ? Colors.white70 : AppTheme.primary,
    );
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: isImage
          ? GestureDetector(
              onTap: () => _viewAttachment(a),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  a.url,
                  width: 180,
                  height: 140,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => Text('📎 ${a.name} (${a.size})', style: linkStyle),
                ),
              ),
            )
          : Text('📎 ${a.name} (${a.size})', style: linkStyle),
    );
  }

  @override
  void dispose() {
    _poll?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            AvatarView(
              label: _header?.displayName ?? 'Messages',
              radius: 17,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_header?.displayName ?? 'Messages',
                      style:
                          const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                  if (_header != null)
                    Text(
                      _header!.tracking != null
                          ? '${_header!.recipientLabel ?? 'Support'} · ${_header!.tracking}'
                          : (_header!.recipientLabel ?? 'Support'),
                      style: const TextStyle(
                          fontSize: 12, color: AppColors.textSecondary),
                    ),
                ],
              ),
            ),
          ],
        ),
        centerTitle: false,
      ),
      body: Column(children: [
        if (widget.thread != null && _threadHasMore && !_loading)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: _loadingOlder
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : TextButton(
                    onPressed: _loadOlder,
                    child: const Text('Load older messages'),
                  ),
          ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _messages.isEmpty
                  ? const Center(child: Text('No messages yet. Start a conversation with Logistics.'))
                  : ListView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.all(16),
                      itemCount: _messages.length,
                      itemBuilder: (_, i) {
                        final m = _messages[i];
                        final showDay = i == 0 || _messages[i - 1].dayLabel != m.dayLabel;
                        return Column(children: [
                          if (showDay)
                            Padding(
                              padding:
                                  const EdgeInsets.symmetric(vertical: 12),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 5),
                                decoration: BoxDecoration(
                                  color: AppColors.surfaceSoft,
                                  borderRadius: BorderRadius.circular(
                                      AppRadius.pill),
                                ),
                                child: Text(m.dayLabel,
                                    style: AppText.caption),
                              ),
                            ),
                          Align(
                            alignment: m.mine ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.symmetric(vertical: 4),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
                              decoration: BoxDecoration(
                                color: m.mine ? AppTheme.primary : Colors.white,
                                // Tight corner on the sender's side marks
                                // the direction of each message.
                                borderRadius: BorderRadius.only(
                                  topLeft: const Radius.circular(18),
                                  topRight: const Radius.circular(18),
                                  bottomLeft: Radius.circular(m.mine ? 18 : 6),
                                  bottomRight:
                                      Radius.circular(m.mine ? 6 : 18),
                                ),
                                border: Border.all(color: m.mine ? AppTheme.primary : AppColors.border),
                              ),
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                if (m.deleted) const Text('This message was deleted', style: TextStyle(fontStyle: FontStyle.italic, color: AppColors.textSecondary)),
                                if (!m.deleted) Text(m.body, style: TextStyle(color: m.mine ? Colors.white : AppColors.textPrimary)),
                                if (m.attachments.isNotEmpty) ...m.attachments.map((a) => _attachmentTile(a, m.mine)),
                                const SizedBox(height: 4),
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(m.time, style: TextStyle(fontSize: 10, color: m.mine ? Colors.white70 : AppColors.textSecondary)),
                                    if (m.mine && !m.deleted && widget.thread != null)
                                      PopupMenuButton<String>(
                                        icon: Icon(Icons.more_vert,
                                            size: 14,
                                            color: m.mine ? Colors.white70 : AppColors.textSecondary),
                                        padding: EdgeInsets.zero,
                                        constraints: const BoxConstraints(),
                                        tooltip: 'Message actions',
                                        onSelected: (value) {
                                          if (value == 'edit') {
                                            _startEdit(m);
                                          } else if (value == 'delete') {
                                            _deleteMessage(m);
                                          }
                                        },
                                        itemBuilder: (_) => const [
                                          PopupMenuItem(
                                              value: 'edit',
                                              child: Text('Edit')),
                                          PopupMenuItem(
                                              value: 'delete',
                                              child: Text('Delete')),
                                        ],
                                      ),
                                  ],
                                ),
                              ]),
                            ),
                          ),
                        ]);
                      },
                    ),
        ),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: AppColors.border))),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedSwitcher(
                duration: AppMotion.micro,
                switchInCurve: AppMotion.easeOut,
                child: _editing != null
                    ? Container(
                        key: ValueKey(_editing!.id),
                        width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppColors.secondary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Expanded(
                        child: Text('Editing message',
                            style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textSecondary)),
                      ),
                      TextButton(
                        onPressed: _sending ? null : _cancelEdit,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: const Size(0, 0),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: const Text('Cancel',
                            style: TextStyle(fontSize: 12)),
                      ),
                    ],
                  ),
                )
              : const SizedBox.shrink(),
            ),
              AnimatedSwitcher(
                duration: AppMotion.micro,
                switchInCurve: AppMotion.easeOut,
                child: _pendingName != null
                    ? Container(
                        key: ValueKey(_pendingName),
                        width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSoft,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Row(
                    children: [
                      _pendingIsImage && _pendingBytes != null
                          ? ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.memory(
                                _pendingBytes!,
                                width: 48,
                                height: 48,
                                fit: BoxFit.cover,
                              ),
                            )
                          : Container(
                              width: 48,
                              height: 48,
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                    color: AppColors.border),
                              ),
                              child: const Icon(
                                  Icons.insert_drive_file_outlined,
                                  color: AppColors.textSecondary),
                            ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              _pendingName!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600),
                            ),
                            Text(
                              '${(_pendingBytes!.lengthInBytes / 1024).toStringAsFixed(0)} KB',
                              style: const TextStyle(
                                  fontSize: 11,
                                  color: AppColors.textSecondary),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: _sending
                            ? null
                            : () => setState(_clearPending),
                        icon: const Icon(Icons.close, size: 18),
                        tooltip: 'Remove attachment',
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                )
              : const SizedBox.shrink(),
            ),
              Row(children: [
            IconButton(
                onPressed: _sending ? null : _showAttachMenu,
                icon: const Icon(Icons.add_circle_outline),
                tooltip: 'Attach photo or file'),
            Expanded(child: TextField(controller: _input, decoration: InputDecoration(hintText: 'Type a message...', border: OutlineInputBorder(borderRadius: BorderRadius.circular(20)), contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10)), onSubmitted: (_) => _send())),
            const SizedBox(width: 8),
            CircleAvatar(backgroundColor: AppTheme.primary, child: IconButton(icon: _sending ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.send_outlined, color: Colors.white, size: 18), onPressed: _sending ? null : () => _send())),
          ]),
            ],
          ),
        ),
      ]),
    );
  }
}
