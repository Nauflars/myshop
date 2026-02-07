/**
 * Admin Floating Assistant - Module JavaScript para el asistente flotante del admin
 * Parte de spec-008: Admin AI Assistant Enhancements
 * 
 * Proporciona:
 * - Botón flotante (FAB) accesible en todas las páginas admin
 * - Panel de chat persistente
 * - Gestión de estado en localStorage (siguiendo patrón del chatbot cliente)
 * - Comunicación con endpoint AdminAssistantController
 */

class AdminFloatingAssistant {
    constructor() {
        this.isOpen = false;
        this.conversationId = this.getConversationId(); // Load from localStorage
        
        this.fab = null;
        this.panel = null;
        this.messageForm = null;
        this.messageInput = null;
        this.messagesContainer = null;
        this.closeButton = null;
        this.clearButton = null;
        
        // Dragging state
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        
        this.init();
    }
    
    init() {
        // Buscar elementos del DOM
        this.fab = document.getElementById('admin-floating-fab');
        this.panel = document.getElementById('admin-floating-panel');
        this.messageForm = document.getElementById('admin-floating-form');
        this.messageInput = document.getElementById('admin-floating-input');
        this.messagesContainer = document.getElementById('admin-floating-messages');
        this.closeButton = document.getElementById('admin-floating-close');
        this.clearButton = document.getElementById('admin-floating-clear');
        
        if (!this.fab || !this.panel) {
            console.error('Admin floating assistant elements not found');
            return;
        }
        
        // Restaurar posición del FAB
        this.restorePosition();
        
        // Bind event listeners (FAB click is handled in makeFabDraggable)
        this.closeButton?.addEventListener('click', () => this.closePanel());
        this.clearButton?.addEventListener('click', () => this.handleClearConversation());
        this.messageForm?.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Make FAB draggable
        this.makeFabDraggable();
        
        // Click fuera del panel para cerrar
        document.addEventListener('click', (e) => this.handleOutsideClick(e));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Cargar historial desde servidor si existe conversationId
        if (this.conversationId) {
            this.loadConversationHistory();
        }
    }
    
    togglePanel() {
        if (this.isOpen) {
            this.closePanel();
        } else {
            this.openPanel();
        }
    }
    
    openPanel() {
        this.isOpen = true;
        this.panel.classList.add('open');
        this.fab.classList.add('hidden');
        this.messageInput?.focus();
        
        // Scroll to bottom
        this.scrollToBottom();
    }
    
    closePanel() {
        this.isOpen = false;
        this.panel.classList.remove('open');
        this.fab.classList.remove('hidden');
    }
    
    handleOutsideClick(e) {
        if (!this.isOpen) return;
        
        // No cerrar si estamos arrastrando
        if (this.isDragging) return;
        
        // Si el click es fuera del panel y no es el FAB
        if (!this.panel.contains(e.target) && !this.fab.contains(e.target)) {
            this.closePanel();
        }
    }
    
    handleKeyboard(e) {
        // Esc para cerrar
        if (e.key === 'Escape' && this.isOpen) {
            this.closePanel();
        }
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        const message = this.messageInput.value.trim();
        if (!message) return;
        
        // Agregar mensaje del usuario al UI
        this.addMessage('user', message);
        this.messageInput.value = '';
        
        // Mostrar indicador de escritura
        const typingId = this.showTypingIndicator();
        
        try {
            const response = await fetch('/admin/assistant/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: message,
                    conversation_id: this.conversationId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            // Remover typing indicator
            this.removeTypingIndicator(typingId);
            
            if (data.success) {
                // Guardar conversationId en localStorage si es nuevo
                if (data.conversation_id) {
                    this.conversationId = data.conversation_id;
                    this.saveConversationId(this.conversationId);
                }
                
                // Agregar respuesta del asistente
                this.addMessage('assistant', data.reply);
            } else {
                this.addMessage('system', `Error: ${data.error || 'Error desconocido'}`);
            }
            
        } catch (error) {
            console.error('Error sending message:', error);
            this.removeTypingIndicator(typingId);
            this.addMessage('system', 'Error al enviar mensaje. Por favor intenta de nuevo.');
        }
    }
    
    addMessage(sender, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `admin-floating-message admin-floating-message-${sender}`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'admin-floating-message-content';
        contentDiv.textContent = text;
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'admin-floating-message-time';
        const now = new Date();
        timeDiv.textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        
        messageDiv.appendChild(contentDiv);
        messageDiv.appendChild(timeDiv);
        
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    showTypingIndicator() {
        const typing = document.createElement('div');
        typing.className = 'admin-floating-typing';
        typing.id = `typing-${Date.now()}`;
        typing.innerHTML = '<span></span><span></span><span></span>';
        
        this.messagesContainer.appendChild(typing);
        this.scrollToBottom();
        
        return typing.id;
    }
    
    removeTypingIndicator(id) {
        const typing = document.getElementById(id);
        if (typing) {
            typing.remove();
        }
    }
    
    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }
    
    // LocalStorage helpers for conversation persistence (siguiendo patrón del cliente)
    getConversationId() {
        return localStorage.getItem('admin_assistant_conversation_id');
    }

    saveConversationId(id) {
        localStorage.setItem('admin_assistant_conversation_id', id);
    }

    clearConversationId() {
        localStorage.removeItem('admin_assistant_conversation_id');
    }
    
    async loadConversationHistory() {
        if (!this.conversationId) return;
        
        try {
            const response = await fetch('/admin/assistant/history', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.success && data.messages && data.messages.length > 0) {
                    // Limpiar mensajes existentes
                    this.messagesContainer.innerHTML = '';
                    
                    // Cargar todos los mensajes desde el historial
                    data.messages.forEach(msg => {
                        const sender = msg.sender === 'user' || msg.sender === 'admin' ? 'user' : 'assistant';
                        this.addMessage(sender, msg.text);
                    });
                } else {
                    // No hay mensajes, mostrar bienvenida
                    this.showWelcomeMessage();
                }
            } else {
                // Error al cargar, mostrar mensaje de continuación
                this.addMessage('assistant', '¡Hola! Continuemos donde lo dejamos. ¿En qué puedo ayudarte?');
            }
        } catch (error) {
            console.error('Error loading conversation history:', error);
            this.addMessage('assistant', '¡Hola! Continuemos donde lo dejamos. ¿En qué puedo ayudarte?');
        }
    }
    
    showWelcomeMessage() {
        const welcome = document.createElement('div');
        welcome.className = 'admin-floating-welcome';
        welcome.innerHTML = `
            <h4>Asistente Virtual Admin</h4>
            <p>¿En qué puedo ayudarte hoy?</p>
            <ul>
                <li>Gestión de inventario</li>
                <li>Actualización de precios</li>
                <li>Análisis de ventas</li>
                <li>Gestión de pedidos</li>
                <li>Estadísticas de clientes</li>
            </ul>
        `;
        this.messagesContainer.appendChild(welcome);
    }
    
    handleClearConversation() {
        if (confirm('¿Estás seguro de que deseas limpiar la conversación? Esta acción no se puede deshacer.')) {
            this.clearConversation();
        }
    }
    
    async clearConversation() {
        // Limpiar en el servidor si existe conversationId
        if (this.conversationId) {
            try {
                await fetch('/admin/assistant/clear-context', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        conversation_id: this.conversationId
                    })
                });
            } catch (error) {
                console.error('Error clearing conversation on server:', error);
            }
        }
        
        // Limpiar conversationId de localStorage
        this.clearConversationId();
        this.conversationId = null;
        this.messagesContainer.innerHTML = '';
        
        // Mostrar mensaje de bienvenida
        this.showWelcomeMessage();
    }
    
    showWelcomeMessage() {
        const welcome = document.createElement('div');
        welcome.className = 'admin-floating-welcome';
        welcome.innerHTML = `
            <h4>Asistente Virtual Admin</h4>
            <p>¿En qué puedo ayudarte hoy?</p>
            <ul>
                <li>Gestión de inventario</li>
                <li>Actualización de precios</li>
                <li>Análisis de ventas</li>
                <li>Gestión de pedidos</li>
                <li>Estadísticas de clientes</li>
            </ul>
        `;
        this.messagesContainer.appendChild(welcome);
    }
    
    makeFabDraggable() {
        this.fab.style.cursor = 'grab';
        
        let startX = 0;
        let startY = 0;
        let hasMoved = false;
        
        const onMouseDown = (e) => {
            // Prevenir comportamiento por defecto
            e.preventDefault();
            e.stopPropagation();
            
            this.isDragging = true;
            hasMoved = false;
            
            const rect = this.fab.getBoundingClientRect();
            this.dragOffset = {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
            
            startX = e.clientX;
            startY = e.clientY;
            
            this.fab.style.transition = 'none';
            this.fab.style.cursor = 'grabbing';
        };
        
        const onMouseMove = (e) => {
            if (!this.isDragging) return;
            
            // Detectar movimiento
            const deltaX = Math.abs(e.clientX - startX);
            const deltaY = Math.abs(e.clientY - startY);
            
            if (deltaX > 5 || deltaY > 5) {
                hasMoved = true;
            }
            
            // Mover siempre, no esperar a hasMoved
            let newX = e.clientX - this.dragOffset.x;
            let newY = e.clientY - this.dragOffset.y;
            
            // Keep within viewport bounds
            const maxX = window.innerWidth - this.fab.offsetWidth;
            const maxY = window.innerHeight - this.fab.offsetHeight;
            
            newX = Math.max(0, Math.min(newX, maxX));
            newY = Math.max(0, Math.min(newY, maxY));
            
            this.fab.style.left = `${newX}px`;
            this.fab.style.top = `${newY}px`;
            this.fab.style.right = 'auto';
            this.fab.style.bottom = 'auto';
        };
        
        const onMouseUp = (e) => {
            if (!this.isDragging) return;
            
            this.isDragging = false;
            this.fab.style.transition = '';
            this.fab.style.cursor = 'grab';
            
            if (hasMoved) {
                // Fue un drag - guardar posición
                this.savePosition();
            } else {
                // Fue un click - abrir panel
                this.togglePanel();
            }
            
            hasMoved = false;
        };
        
        this.fab.addEventListener('mousedown', onMouseDown);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    }
    
    savePosition() {
        const position = {
            left: this.fab.style.left,
            top: this.fab.style.top,
            right: this.fab.style.right,
            bottom: this.fab.style.bottom
        };
        
        localStorage.setItem('adminFabPosition', JSON.stringify(position));
    }
    
    restorePosition() {
        const savedPosition = localStorage.getItem('adminFabPosition');
        if (!savedPosition) return;
        
        try {
            const position = JSON.parse(savedPosition);
            
            if (position.left !== 'auto' && position.left) {
                this.fab.style.left = position.left;
                this.fab.style.right = 'auto';
            }
            
            if (position.top !== 'auto' && position.top) {
                this.fab.style.top = position.top;
                this.fab.style.bottom = 'auto';
            }
        } catch (error) {
            console.error('Error restoring FAB position:', error);
        }
    }
}

// Inicializar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    window.adminFloatingAssistant = new AdminFloatingAssistant();
});
