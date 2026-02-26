import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import 'models/chat_models.dart';
import '../domain/entities/chat_message.dart';

/// Repository handling all chatbot API calls.
class ChatRepository {
  final ApiClient _apiClient;

  ChatRepository(this._apiClient);

  /// Send a message to the AI assistant.
  /// Returns the assistant's response and the conversation ID.
  Future<ChatResponse> sendMessage({
    required String message,
    String? conversationId,
  }) async {
    final data = <String, dynamic>{
      'message': message,
    };
    if (conversationId != null) {
      data['conversationId'] = conversationId;
    }

    final response = await _apiClient.dio.post(
      ApiEndpoints.chat,
      data: data,
    );

    return ChatResponse.fromJson(response.data as Map<String, dynamic>);
  }

  /// Load conversation history by ID.
  Future<List<ChatMessage>> loadHistory(String conversationId) async {
    final response = await _apiClient.dio.get(
      ApiEndpoints.chatHistory(conversationId),
    );

    final historyResponse =
        ChatHistoryResponse.fromJson(response.data as Map<String, dynamic>);
    return historyResponse.messages;
  }

  /// Clear/delete a conversation.
  Future<void> clearConversation(String conversationId) async {
    await _apiClient.dio.post(
      ApiEndpoints.chatClear,
      data: {'conversationId': conversationId},
    );
  }

  /// Reset conversation context (keep messages but reset AI state).
  Future<void> resetContext(String conversationId) async {
    await _apiClient.dio.post(
      ApiEndpoints.chatResetContext,
      data: {'conversationId': conversationId},
    );
  }
}
