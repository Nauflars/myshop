/// Represents a single chat message in a conversation.
class ChatMessage {
  final String id;
  final String role; // 'user' or 'assistant'
  final String content;
  final DateTime timestamp;
  final bool isError;

  const ChatMessage({
    required this.id,
    required this.role,
    required this.content,
    required this.timestamp,
    this.isError = false,
  });

  bool get isUser => role == 'user';
  bool get isAssistant => role == 'assistant';

  /// Create a user message (optimistic, before API response).
  factory ChatMessage.user(String content) {
    return ChatMessage(
      id: 'user_${DateTime.now().millisecondsSinceEpoch}',
      role: 'user',
      content: content,
      timestamp: DateTime.now(),
    );
  }

  /// Create an assistant message from API response.
  factory ChatMessage.assistant(String content) {
    return ChatMessage(
      id: 'assistant_${DateTime.now().millisecondsSinceEpoch}',
      role: 'assistant',
      content: content,
      timestamp: DateTime.now(),
    );
  }

  /// Create an error message.
  factory ChatMessage.error(String message) {
    return ChatMessage(
      id: 'error_${DateTime.now().millisecondsSinceEpoch}',
      role: 'assistant',
      content: message,
      timestamp: DateTime.now(),
      isError: true,
    );
  }
}
