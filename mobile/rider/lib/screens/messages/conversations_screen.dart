import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/format_utils.dart';
import '../../models/conversation.dart';
import '../../providers/auth_provider.dart';
import '../../services/message_service.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/stagger_in.dart';
import 'messages_screen.dart';

/// Rider inbox: threads appear automatically from real relationships
/// (logistics support + per-delivery buyer/seller threads). No recipient
/// picking — tap a thread to open it.
class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen> {
  final _search = TextEditingController();
  Timer? _debounce;
  List<ConversationThread> _threads = [];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _page = 1;
  bool _hasMore = false;

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  MessageService _svc() =>
      MessageService(context.read<AuthProvider>().api);

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    }
    try {
      final result = await _svc().listConversations(
        search: _search.text,
        page: reset ? 1 : _page,
      );
      if (!mounted) return;
      setState(() {
        _threads = reset
            ? result.threads
            : [..._threads, ...result.threads];
        _hasMore = result.hasMore;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        if (_threads.isEmpty) _error = 'Unable to load conversations.';
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() {
      _loadingMore = true;
      _page += 1;
    });
    await _load();
    if (mounted) setState(() => _loadingMore = false);
  }

  void _onSearchChanged() {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 400),
      () => _load(reset: true),
    );
  }

  Future<void> _open(ConversationThread thread) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => MessagesScreen(thread: thread)),
    );
    if (mounted) _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _search,
              onChanged: (_) => _onSearchChanged(),
              decoration: InputDecoration(
                hintText: 'Search conversations...',
                filled: true,
                fillColor: AppColors.surfaceSoft,
                prefixIcon: const Icon(Icons.search_outlined, size: 20),
                suffixIcon: _search.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _search.clear();
                          _load(reset: true);
                          setState(() {});
                        },
                      )
                    : null,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                  borderSide: BorderSide.none,
                ),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              ),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? _StateMessage(
                        icon: Icons.error_outline,
                        title: _error!,
                        actionLabel: 'Retry',
                        onAction: () => _load(reset: true),
                      )
                    : _threads.isEmpty
                        ? const _StateMessage(
                            icon: Icons.chat_bubble_outline,
                            title: 'No conversations yet.',
                            subtitle:
                                'Your delivery, pickup, order, and support '
                                'conversations will appear here automatically.',
                          )
                        : RefreshIndicator(
                            onRefresh: () => _load(reset: true),
                            child: ListView.builder(
                              padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                              itemCount: _threads.length + (_hasMore ? 1 : 0),
                              itemBuilder: (_, i) {
                                if (i >= _threads.length) {
                                  return Padding(
                                    padding: const EdgeInsets.only(top: 8),
                                    child: Center(
                                      child: _loadingMore
                                          ? const SizedBox(
                                              width: 22,
                                              height: 22,
                                              child:
                                                  CircularProgressIndicator(
                                                      strokeWidth: 2),
                                            )
                                          : TextButton(
                                              onPressed: _loadMore,
                                              child: const Text('Load more'),
                                            ),
                                    ),
                                  );
                                }
                                return StaggerIn(
                                  index: i,
                                  child: _ThreadTile(
                                    thread: _threads[i],
                                    onTap: () => _open(_threads[i]),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class _ThreadTile extends StatelessWidget {
  const _ThreadTile({required this.thread, required this.onTap});

  final ConversationThread thread;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final unread = thread.unread > 0;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Padding(
          padding: const EdgeInsets.all(AppGaps.sm + 2),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AvatarView(label: thread.name, radius: 21),
              const SizedBox(width: AppGaps.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            thread.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppText.body.copyWith(
                              fontWeight: unread
                                  ? FontWeight.w800
                                  : FontWeight.w600,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          FormatUtils.timeAgo(thread.lastMessageAt),
                          style: AppText.caption,
                        ),
                      ],
                    ),
                    if (thread.contextLine.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        thread.contextLine,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppText.secondary.copyWith(
                          fontSize: 12,
                          color: AppColors.primary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    if (thread.preview != null &&
                        thread.preview!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        thread.preview!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppText.secondary.copyWith(
                          color: unread
                              ? AppColors.textPrimary
                              : AppColors.textSecondary,
                          fontWeight: unread
                              ? FontWeight.w600
                              : FontWeight.w400,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (unread) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 7, vertical: 3),
                  decoration: const BoxDecoration(
                    color: AppColors.secondary,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    thread.unread > 99 ? '99+' : '${thread.unread}',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _StateMessage extends StatelessWidget {
  const _StateMessage({
    required this.icon,
    required this.title,
    this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 84),
        Center(
          child: Container(
            width: 76,
            height: 76,
            decoration: BoxDecoration(
              color: AppTheme.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(AppRadius.lg),
            ),
            child: Icon(icon, size: 34, color: AppTheme.primary),
          ),
        ),
        const SizedBox(height: AppGaps.lg),
        Text(
          title,
          textAlign: TextAlign.center,
          style: AppText.section.copyWith(fontSize: 17),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: AppGaps.sm),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 48),
            child: Text(
              subtitle!,
              textAlign: TextAlign.center,
              style: AppText.secondary,
            ),
          ),
        ],
        if (actionLabel != null && onAction != null) ...[
          const SizedBox(height: AppGaps.lg),
          Center(
            child: TextButton(
              onPressed: onAction,
              child: Text(actionLabel!),
            ),
          ),
        ],
      ],
    );
  }
}
