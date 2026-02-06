// Chatbot JavaScript - Updated in spec-004 with draggable floating widget

class Chatbot {
    constructor() {
        this.widget = document.getElementById('chatbot-widget');
        this.trigger = document.getElementById('chatbot-trigger');
        this.messagesContainer = document.getElementById('chatbot-messages');
        this.input = document.getElementById('chatbot-input');
        this.sendBtn = document.getElementById('chatbot-send');
        this.closeBtn = document.getElementById('chatbot-close');
        this.clearBtn = document.getElementById('chatbot-clear');
        this.header = this.widget?.querySelector('.chatbot-header');
        
        this.isOpen = false;
        this.conversationId = this.getConversationId();
        
        // Dragging state
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        
        this.init();
    }

    init() {
        if (!this.widget || !this.trigger) return;

        this.restorePosition();
        this.attachEventListeners();
        this.makeDraggable();
        
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
    
    makeDraggable() {
        if (!this.header) return;
        
        this.header.style.cursor = 'move';
        
        this.header.addEventListener('mousedown', (e) => {
            // Don't drag if clicking on buttons
            if (e.target.tagName === 'BUTTON') return;
            
            this.isDragging = true;
            const rect = this.widget.getBoundingClientRect();
            this.dragOffset = {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
            
            this.widget.style.transition = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!this.isDragging) return;
            
            let newX = e.clientX - this.dragOffset.x;
            let newY = e.clientY - this.dragOffset.y;
            
            // Keep within viewport bounds
            const maxX = window.innerWidth - this.widget.offsetWidth;
            const maxY = window.innerHeight - this.widget.offsetHeight;
            
            newX = Math.max(0, Math.min(newX, maxX));
            newY = Math.max(0, Math.min(newY, maxY));
            
            this.widget.style.left = `${newX}px`;
            this.widget.style.top = `${newY}px`;
            this.widget.style.right = 'auto';
            this.widget.style.bottom = 'auto';
        });
        
        document.addEventListener('mouseup', () => {
            if (this.isDragging) {
                this.isDragging = false;
                this.widget.style.transition = '';
                this.savePosition();
            }
        });
    }
    
    savePosition() {
        const rect = this.widget.getBoundingClientRect();
        localStorage.setItem('chatbot_position', JSON.stringify({
            x: rect.left,
            y: rect.top
        }));
    }
    
    restorePosition() {
        const savedPos = localStorage.getItem('chatbot_position');
        if (savedPos) {
            try {
                const { x, y } = JSON.parse(savedPos);
                
                // Ensure position is within current viewport
                const maxX = window.innerWidth - 400; // approx widget width
                const maxY = window.innerHeight - 500; // approx widget height
                
                const validX = Math.max(0, Math.min(x, maxX));
                const validY = Math.max(0, Math.min(y, maxY));
                
                this.widget.style.left = `${validX}px`;
                this.widget.style.top = `${validY}px`;
                this.widget.style.right = 'auto';
                this.widget.style.bottom = 'auto';
            } catch (e) {
                console.error('Error restoring position:', e);
            }
        }
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
                
                // Trigger cart badge update if response mentions cart-related actions
                if (this.isCartAction(data.response)) {
                    window.dispatchEvent(new Event('cartUpdated'));
                }
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
    
    isCartAction(message) {
        // Check if the bot response indicates a cart action occurred
        const cartKeywords = [
            'añadido al carrito',
            'agregado al carrito',
            'añadí',
            'agregué',
            'he añadido',
            'he agregado',
            'carrito actualizado',
            'producto añadido',
            'producto agregado',
            'eliminado del carrito',
            'removido del carrito',
            'eliminé',
            'removí',
            'carrito vaciado',
            'pedido creado',
            'orden creada',
            'compra completada',
            'añadir',
            'total',
            'item',
            'cantidad'
        ];
        
        const lowerMessage = message.toLowerCase();
        // Check if message mentions cart-related words
        const hasCartKeyword = cartKeywords.some(keyword => lowerMessage.includes(keyword));
        
        // Also trigger on success messages that mention numbers (likely quantities)
        const hasNumber = /\d+/.test(message);
        const mentionsCart = lowerMessage.includes('carrito') || lowerMessage.includes('cart');
        
        return hasCartKeyword || (hasNumber && mentionsCart);
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
        if (!this.conversationId) return;
        
        try {
            const response = await fetch(`/api/chat/history/${this.conversationId}`, {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.success && data.messages && data.messages.length > 0) {
                    // Clear existing messages
                    this.messagesContainer.innerHTML = '';
                    
                    // Load all messages from history
                    data.messages.forEach(msg => {
                        this.addMessage(msg.role === 'user' ? 'user' : 'bot', msg.content);
                    });
                } else {
                    // No messages yet, show welcome
                    this.addMessage('bot', '¡Hola! Soy tu asistente de compras con IA. ¿En qué puedo ayudarte hoy?');
                }
            } else {
                // Error loading, show welcome
                this.addMessage('bot', '¡Hola! Continuemos donde lo dejamos. ¿En qué puedo ayudarte?');
            }
        } catch (error) {
            console.error('Error loading conversation history:', error);
            this.addMessage('bot', '¡Hola! Continuemos donde lo dejamos. ¿En qué puedo ayudarte?');
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
