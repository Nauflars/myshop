import '../../domain/entities/chat_message.dart';

/// Model for deserializing chat message from API JSON.
class ChatMessageModel {
  /// Parse a single message from the history endpoint.
  static ChatMessage fromJson(Map<String, dynamic> json) {
    return ChatMessage(
      id: '${json['role']}_${json.hashCode}',
      role: json['role'] as String? ?? 'assistant',
      content: json['content'] as String? ?? '',
      timestamp: json['timestamp'] != null
          ? DateTime.tryParse(json['timestamp'] as String) ?? DateTime.now()
          : DateTime.now(),
    );
  }
}

/// Response model for POST /api/chat.
class ChatResponse {
  final String response;
  final String conversationId;
  final String role;

  const ChatResponse({
    required this.response,
    required this.conversationId,
    required this.role,
  });

  factory ChatResponse.fromJson(Map<String, dynamic> json) {
    return ChatResponse(
      response: json['response'] as String? ?? '',
      conversationId: json['conversationId'] as String? ?? '',
      role: json['role'] as String? ?? 'assistant',
    );
  }
}

/// Response model for GET /api/chat/history/{id}.
class ChatHistoryResponse {
  final bool success;
  final List<ChatMessage> messages;

  const ChatHistoryResponse({
    required this.success,
    required this.messages,
  });

  factory ChatHistoryResponse.fromJson(Map<String, dynamic> json) {
    final messageList = (json['messages'] as List<dynamic>?)
            ?.map((m) => ChatMessageModel.fromJson(m as Map<String, dynamic>))
            .toList() ??
        [];

    return ChatHistoryResponse(
      success: json['success'] as bool? ?? false,
      messages: messageList,
    );
  }
}
