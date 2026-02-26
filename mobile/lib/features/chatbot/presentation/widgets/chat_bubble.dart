import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../core/theme/app_theme.dart';
import '../../domain/entities/chat_message.dart';

/// A single chat message bubble with styling for user/assistant messages.
class ChatBubble extends StatelessWidget {
  final ChatMessage message;

  const ChatBubble({super.key, required this.message});

  @override
  Widget build(BuildContext context) {
    final isUser = message.isUser;
    final theme = Theme.of(context);

    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.8,
        ),
        margin: EdgeInsets.only(
          left: isUser ? 48 : 8,
          right: isUser ? 8 : 48,
          top: 4,
          bottom: 4,
        ),
        child: Column(
          crossAxisAlignment:
              isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            // Avatar + bubble
            Row(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                if (!isUser) ...[
                  _buildAvatar(),
                  const SizedBox(width: 8),
                ],
                Flexible(child: _buildBubble(context, theme, isUser)),
              ],
            ),
            // Timestamp
            Padding(
              padding: EdgeInsets.only(
                top: 2,
                left: isUser ? 0 : 40,
                right: isUser ? 0 : 0,
              ),
              child: Text(
                _formatTime(message.timestamp),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppTheme.textLight,
                  fontSize: 11,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvatar() {
    return Container(
      width: 32,
      height: 32,
      decoration: const BoxDecoration(
        gradient: AppTheme.chatHeaderGradient,
        shape: BoxShape.circle,
      ),
      child: const Icon(
        Icons.smart_toy_outlined,
        size: 18,
        color: Colors.white,
      ),
    );
  }

  Widget _buildBubble(BuildContext context, ThemeData theme, bool isUser) {
    return GestureDetector(
      onLongPress: () {
        Clipboard.setData(ClipboardData(text: message.content));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Message copied'),
            duration: Duration(seconds: 1),
          ),
        );
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: message.isError
              ? Colors.red.shade50
              : isUser
                  ? AppTheme.primaryColor
                  : Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isUser ? 16 : 4),
            bottomRight: Radius.circular(isUser ? 4 : 16),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
          border: message.isError
              ? Border.all(color: Colors.red.shade200)
              : isUser
                  ? null
                  : Border.all(color: AppTheme.borderColor),
        ),
        child: _buildContent(theme, isUser),
      ),
    );
  }

  Widget _buildContent(ThemeData theme, bool isUser) {
    final textColor = message.isError
        ? Colors.red.shade700
        : isUser
            ? Colors.white
            : AppTheme.textColor;

    // Detect product lists (numbered **name** - $price patterns from AI)
    final content = message.content;

    // Check if content contains product listing patterns
    if (!isUser && _hasProductList(content)) {
      return _buildRichContent(content, theme, textColor);
    }

    return SelectableText(
      content,
      style: theme.textTheme.bodyMedium?.copyWith(
        color: textColor,
        height: 1.4,
      ),
    );
  }

  bool _hasProductList(String content) {
    return RegExp(r'\d+\.\s+\*\*').hasMatch(content);
  }

  Widget _buildRichContent(String content, ThemeData theme, Color textColor) {
    final lines = content.split('\n');
    final widgets = <Widget>[];
    final buffer = StringBuffer();

    for (final line in lines) {
      final productMatch =
          RegExp(r'^(\d+)\.\s+\*\*(.+?)\*\*\s*[-–]\s*(.+)$').firstMatch(line);

      if (productMatch != null) {
        // Flush any buffered text
        if (buffer.isNotEmpty) {
          widgets.add(Text(
            buffer.toString().trimRight(),
            style: theme.textTheme.bodyMedium?.copyWith(
              color: textColor,
              height: 1.4,
            ),
          ));
          buffer.clear();
        }

        // Product card
        final name = productMatch.group(2)!;
        final priceInfo = productMatch.group(3)!.trim();

        widgets.add(Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: AppTheme.primaryColor.withOpacity(0.05),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(
                color: AppTheme.primaryColor.withOpacity(0.15),
              ),
            ),
            child: Row(
              children: [
                Icon(Icons.shopping_bag_outlined,
                    size: 16, color: AppTheme.primaryColor),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: textColor,
                        ),
                      ),
                      Text(
                        priceInfo,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppTheme.secondaryColor,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ));
      } else {
        buffer.writeln(line);
      }
    }

    // Flush remaining text
    if (buffer.isNotEmpty) {
      widgets.add(Text(
        buffer.toString().trimRight(),
        style: theme.textTheme.bodyMedium?.copyWith(
          color: textColor,
          height: 1.4,
        ),
      ));
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: widgets,
    );
  }

  String _formatTime(DateTime dt) {
    final h = dt.hour.toString().padLeft(2, '0');
    final m = dt.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}
