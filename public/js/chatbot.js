// Chatbot JavaScript

class Chatbot {
    constructor() {
        this.widget = document.getElementById('chatbot-widget');
        this.trigger = document.getElementById('chatbot-trigger');
        this.messagesContainer = document.getElementById('chatbot-messages');
        this.input = document.getElementById('chatbot-input');
        this.sendBtn = document.getElementById('chatbot-send');
        this.closeBtn = document.getElementById('chatbot-close');
        
        this.isOpen = false;
        this.init();
    }

    init() {
        if (!this.widget || !this.trigger) return;

        this.attachEventListeners();
        this.addMessage('bot', 'Hello! I\'m your AI shopping assistant. How can I help you today?');
    }

    attachEventListeners() {
        this.trigger.addEventListener('click', () => this.toggle());
        this.closeBtn.addEventListener('click', () => this.close());
        this.sendBtn.addEventListener('click', () => this.sendMessage());
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
                body: JSON.stringify({ message }),
            });

            this.hideTypingIndicator();

            if (response.ok) {
                const data = await response.json();
                this.addMessage('bot', data.response);
            } else {
                const error = await response.json();
                this.addMessage('bot', error.error || 'Sorry, I encountered an error. Please try again.');
            }
        } catch (error) {
            this.hideTypingIndicator();
            console.error('Chat error:', error);
            this.addMessage('bot', 'Sorry, I\'m having trouble connecting. Please try again later.');
        }
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
}

// Initialize chatbot
if (document.getElementById('chatbot-widget')) {
    const chatbot = new Chatbot();
    window.chatbot = chatbot;
}
