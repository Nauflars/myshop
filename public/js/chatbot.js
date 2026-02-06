// Chatbot JavaScript - Updated in spec-003 with conversation persistence

class Chatbot {
    constructor() {
        this.widget = document.getElementById('chatbot-widget');
        this.trigger = document.getElementById('chatbot-trigger');
        this.messagesContainer = document.getElementById('chatbot-messages');
        this.input = document.getElementById('chatbot-input');
        this.sendBtn = document.getElementById('chatbot-send');
        this.closeBtn = document.getElementById('chatbot-close');
        this.clearBtn = document.getElementById('chatbot-clear');
        
        this.isOpen = false;
        this.conversationId = this.getConversationId();
        
        this.init();
    }

    init() {
        if (!this.widget || !this.trigger) return;

        this.attachEventListeners();
        
        // Load conversation history if exists
        if (this.conversationId) {
            this.loadConversationHistory();
        } else {
            this.addMessage('bot', '¡Hola! Soy tu asistente de compras con IA. ¿En qué puedo ayudarte hoy?');
        }
    }

    attachEventListeners() {
        this.trigger.addEventListener('click', () => this.toggle());
        this.closeBtn.addEventListener('click', () => this.close());
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        
        if (this.clearBtn) {
            this.clearBtn.addEventListener('click', () => this.clearChat());
        }
        
        this.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.widget.classList.add('open');
        this.trigger.style.display = 'none';
        this.isOpen = true;
        this.input.focus();
    }

    close() {
        this.widget.classList.remove('open');
        this.trigger.style.display = 'flex';
        this.isOpen = false;
    }

    async sendMessage() {
        const message = this.input.value.trim();
        if (!message) return;

        // Add user message to UI
        this.addMessage('user', message);
        this.input.value = '';

        // Show typing indicator
        this.showTypingIndicator();

        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ 
                    message,
                    conversationId: this.conversationId 
                }),
            });

            this.hideTypingIndicator();

            if (response.ok) {
                const data = await response.json();
                
                // Store conversation ID for continuity
                if (data.conversationId) {
                    this.conversationId = data.conversationId;
                    this.saveConversationId(this.conversationId);
                }
                
                this.addMessage('bot', data.response);
            } else {
                const error = await response.json();
                this.addMessage('bot', error.error || 'Lo siento, encontré un error. Por favor intenta de nuevo.');
            }
        } catch (error) {
            this.hideTypingIndicator();
            console.error('Chat error:', error);
            this.addMessage('bot', 'Lo siento, tengo problemas para conectarme. Por favor intenta más tarde.');
        }
    }

    async clearChat() {
        if (!this.conversationId) {
            // Just clear UI if no conversation exists
            this.messagesContainer.innerHTML = '';
            this.addMessage('bot', '¡Hola! Soy tu asistente de compras con IA. ¿En qué puedo ayudarte hoy?');
            return;
        }

        if (!confirm('¿Estás seguro de que quieres limpiar el historial de conversación?')) {
            return;
        }

        try {
            const response = await fetch('/api/chat/clear', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ conversationId: this.conversationId }),
            });

            if (response.ok) {
                // Clear local storage and UI
                this.clearConversationId();
                this.conversationId = null;
                this.messagesContainer.innerHTML = '';
                this.addMessage('bot', '¡Hola! Soy tu asistente de compras con IA. ¿En qué puedo ayudarte hoy?');
            } else {
                const error = await response.json();
                alert('Error al limpiar: ' + (error.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Clear chat error:', error);
            alert('Error al limpiar la conversación');
        }
    }

    async loadConversationHistory() {
        // Note: In a production environment, you'd fetch this from backend
        // For now, just show welcome message - messages are fetched on sendMessage
        this.addMessage('bot', '¡Hola! Continuemos donde lo dejamos. ¿En qué puedo ayudarte?');
    }

    addMessage(role, content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${role}-message`;
        messageDiv.textContent = content;
        
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }

    showTypingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'typing-indicator';
        indicator.id = 'typing-indicator';
        indicator.innerHTML = '<span></span><span></span><span></span>';
        this.messagesContainer.appendChild(indicator);
        this.scrollToBottom();
    }

    hideTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    // LocalStorage helpers for conversation persistence
    getConversationId() {
        return localStorage.getItem('chatbot_conversation_id');
    }

    saveConversationId(id) {
        localStorage.setItem('chatbot_conversation_id', id);
    }

    clearConversationId() {
        localStorage.removeItem('chatbot_conversation_id');
    }
}

// Initialize chatbot
if (document.getElementById('chatbot-widget')) {
    const chatbot = new Chatbot();
    window.chatbot = chatbot;
}
