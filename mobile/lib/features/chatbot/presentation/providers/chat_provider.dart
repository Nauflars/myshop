import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/error/error_handler.dart';
import '../../../../core/network/api_client.dart';
import '../../data/chat_repository.dart';
import '../../domain/entities/chat_message.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

// -- Repository Provider --
final chatRepositoryProvider = Provider<ChatRepository>((ref) {
  return ChatRepository(ref.watch(apiClientProvider));
});

// -- Chat State --
class ChatState {
  final List<ChatMessage> messages;
  final String? conversationId;
  final bool isSending;
  final bool isLoadingHistory;
  final String? error;

  const ChatState({
    this.messages = const [],
    this.conversationId,
    this.isSending = false,
    this.isLoadingHistory = false,
    this.error,
  });

  ChatState copyWith({
    List<ChatMessage>? messages,
    String? conversationId,
    bool? isSending,
    bool? isLoadingHistory,
    String? error,
    bool clearError = false,
    bool clearConversation = false,
  }) {
    return ChatState(
      messages: messages ?? this.messages,
      conversationId:
          clearConversation ? null : (conversationId ?? this.conversationId),
      isSending: isSending ?? this.isSending,
      isLoadingHistory: isLoadingHistory ?? this.isLoadingHistory,
      error: clearError ? null : (error ?? this.error),
    );
  }

  bool get isEmpty => messages.isEmpty;
  bool get hasConversation => conversationId != null;
}

// -- Chat Notifier --
class ChatNotifier extends StateNotifier<ChatState> {
  final ChatRepository _repository;

  ChatNotifier(this._repository) : super(const ChatState());

  /// Send a message to the AI assistant.
  Future<void> sendMessage(String text) async {
    if (text.trim().isEmpty || state.isSending) return;

    final userMessage = ChatMessage.user(text.trim());

    // Optimistic: add user message immediately
    state = state.copyWith(
      messages: [...state.messages, userMessage],
      isSending: true,
      clearError: true,
    );

    try {
      final response = await _repository.sendMessage(
        message: text.trim(),
        conversationId: state.conversationId,
      );

      final assistantMessage = ChatMessage.assistant(response.response);

      state = state.copyWith(
        messages: [...state.messages, assistantMessage],
        conversationId: response.conversationId,
        isSending: false,
      );
    } catch (e) {
      final errorMsg = AppErrorHandler.getMessage(e);
      final errorMessage = ChatMessage.error(
        'Sorry, something went wrong. Please try again.\n$errorMsg',
      );

      state = state.copyWith(
        messages: [...state.messages, errorMessage],
        isSending: false,
        error: errorMsg,
      );
    }
  }

  /// Load conversation history from server.
  Future<void> loadHistory(String conversationId) async {
    state = state.copyWith(
      isLoadingHistory: true,
      conversationId: conversationId,
      clearError: true,
    );

    try {
      final messages = await _repository.loadHistory(conversationId);
      state = state.copyWith(
        messages: messages,
        isLoadingHistory: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoadingHistory: false,
        error: AppErrorHandler.getMessage(e),
      );
    }
  }

  /// Clear conversation and start fresh.
  Future<void> clearConversation() async {
    final conversationId = state.conversationId;

    // Reset state immediately (optimistic)
    state = const ChatState();

    // Also clear on server if we had a conversation
    if (conversationId != null) {
      try {
        await _repository.clearConversation(conversationId);
      } catch (_) {
        // Ignore errors on clear — local state is already reset
      }
    }
  }

  /// Set conversation ID (e.g. restored from storage).
  void setConversationId(String? id) {
    if (id != null && id != state.conversationId) {
      state = state.copyWith(conversationId: id);
    }
  }
}

// -- Provider --
final chatProvider = StateNotifierProvider<ChatNotifier, ChatState>((ref) {
  return ChatNotifier(ref.watch(chatRepositoryProvider));
});
