import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_theme.dart';
import '../providers/chat_provider.dart';
import '../widgets/chat_bubble.dart';
import '../widgets/typing_indicator.dart';

/// Quick suggestion chip data.
class _Suggestion {
  final String label;
  final IconData icon;
  const _Suggestion(this.label, this.icon);
}

const _suggestions = [
  _Suggestion('What products do you have?', Icons.shopping_bag_outlined),
  _Suggestion('Show me electronics', Icons.devices),
  _Suggestion('Help me find a gift', Icons.card_giftcard),
  _Suggestion('Track my order', Icons.local_shipping_outlined),
];

/// Full-featured AI chatbot screen.
class ChatbotScreen extends ConsumerStatefulWidget {
  const ChatbotScreen({super.key});

  @override
  ConsumerState<ChatbotScreen> createState() => _ChatbotScreenState();
}

class _ChatbotScreenState extends ConsumerState<ChatbotScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final _focusNode = FocusNode();

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _send() {
    final text = _controller.text.trim();
    if (text.isEmpty) return;

    ref.read(chatProvider.notifier).sendMessage(text);
    _controller.clear();
    _focusNode.requestFocus();
    _scrollToBottom();
  }

  void _sendSuggestion(String text) {
    ref.read(chatProvider.notifier).sendMessage(text);
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    final chatState = ref.watch(chatProvider);
    final theme = Theme.of(context);

    // Auto-scroll when messages change
    ref.listen<ChatState>(chatProvider, (prev, next) {
      if (prev?.messages.length != next.messages.length ||
          prev?.isSending != next.isSending) {
        _scrollToBottom();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: [
            Icon(Icons.smart_toy_outlined, size: 24),
            SizedBox(width: 8),
            Text('AI Assistant'),
          ],
        ),
        flexibleSpace: Container(
          decoration:
              const BoxDecoration(gradient: AppTheme.chatHeaderGradient),
        ),
        actions: [
          if (chatState.hasConversation)
            PopupMenuButton<String>(
              icon: const Icon(Icons.more_vert),
              onSelected: (value) {
                if (value == 'clear') {
                  _showClearDialog(context);
                }
              },
              itemBuilder: (context) => [
                const PopupMenuItem(
                  value: 'clear',
                  child: ListTile(
                    leading: Icon(Icons.delete_outline),
                    title: Text('New conversation'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ],
            ),
        ],
      ),
      body: Column(
        children: [
          // Message list
          Expanded(
            child: chatState.isEmpty && !chatState.isLoadingHistory
                ? _buildEmptyState(theme)
                : _buildMessageList(chatState, theme),
          ),

          // Input bar
          _buildInputBar(chatState, theme),
        ],
      ),
    );
  }

  Widget _buildEmptyState(ThemeData theme) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const SizedBox(height: 40),
          // Avatar
          Container(
            width: 80,
            height: 80,
            decoration: const BoxDecoration(
              gradient: AppTheme.chatHeaderGradient,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.smart_toy_outlined,
              size: 40,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 20),
          Text(
            'How can I help you?',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'I can help you find products, track orders,\nand answer questions about our store.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: AppTheme.textLight,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 32),
          // Quick suggestions
          Text(
            'Try asking:',
            style: theme.textTheme.labelLarge?.copyWith(
              color: AppTheme.textLight,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            children: _suggestions.map((s) {
              return ActionChip(
                avatar: Icon(s.icon, size: 18, color: AppTheme.primaryColor),
                label: Text(
                  s.label,
                  style: const TextStyle(fontSize: 13),
                ),
                onPressed: () => _sendSuggestion(s.label),
                backgroundColor: AppTheme.primaryColor.withOpacity(0.08),
                side: BorderSide(
                  color: AppTheme.primaryColor.withOpacity(0.2),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildMessageList(ChatState chatState, ThemeData theme) {
    if (chatState.isLoadingHistory) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('Loading conversation...'),
          ],
        ),
      );
    }

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
      itemCount: chatState.messages.length + (chatState.isSending ? 1 : 0),
      itemBuilder: (context, index) {
        // Show typing indicator as last item while sending
        if (index == chatState.messages.length && chatState.isSending) {
          return const TypingIndicator();
        }

        return ChatBubble(message: chatState.messages[index]);
      },
    );
  }

  Widget _buildInputBar(ChatState chatState, ThemeData theme) {
    return Container(
      padding: EdgeInsets.only(
        left: 12,
        right: 12,
        top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 8,
      ),
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        border: const Border(
          top: BorderSide(color: AppTheme.borderColor),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Text input
          Expanded(
            child: TextField(
              controller: _controller,
              focusNode: _focusNode,
              textInputAction: TextInputAction.send,
              maxLines: 4,
              minLines: 1,
              textCapitalization: TextCapitalization.sentences,
              onSubmitted: (_) => _send(),
              enabled: !chatState.isSending,
              decoration: InputDecoration(
                hintText: chatState.isSending
                    ? 'Waiting for response...'
                    : 'Type a message...',
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: const BorderSide(color: AppTheme.borderColor),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: const BorderSide(color: AppTheme.borderColor),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: const BorderSide(
                      color: AppTheme.primaryColor, width: 1.5),
                ),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                isDense: true,
              ),
            ),
          ),
          const SizedBox(width: 8),
          // Send button
          Container(
            decoration: BoxDecoration(
              gradient:
                  chatState.isSending ? null : AppTheme.chatHeaderGradient,
              color: chatState.isSending ? Colors.grey.shade300 : null,
              shape: BoxShape.circle,
            ),
            child: IconButton(
              icon: Icon(
                chatState.isSending ? Icons.hourglass_top : Icons.send,
                color: Colors.white,
                size: 20,
              ),
              onPressed: chatState.isSending ? null : _send,
            ),
          ),
        ],
      ),
    );
  }

  void _showClearDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('New Conversation'),
        content: const Text(
          'This will clear the current conversation and start fresh. Continue?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              ref.read(chatProvider.notifier).clearConversation();
            },
            child: const Text('Clear'),
          ),
        ],
      ),
    );
  }
}
