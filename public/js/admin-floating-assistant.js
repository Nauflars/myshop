/**
 * Admin Floating Assistant - Module JavaScript para el asistente flotante del admin
 * Parte de spec-008: Admin AI Assistant Enhancements
 * 
 * Proporciona:
 * - Botón flotante (FAB) accesible en todas las páginas admin
 * - Panel de chat persistente
 * - Gestión de estado en sessionStorage
 * - Comunicación con endpoint AdminAssistantController
 */

class AdminFloatingAssistant {
    constructor() {
        this.isOpen = false;
        this.conversationId = null;
        this.messageHistory = [];
        
        this.fab = null;
        this.panel = null;
        this.messageForm = null;
        this.messageInput = null;
        this.messagesContainer = null;
        this.closeButton = null;
        
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
        
        if (!this.fab || !this.panel) {
            console.error('Admin floating assistant elements not found');
            return;
        }
        
        // Restaurar estado de sessionStorage
        this.restoreState();
        
        // Bind event listeners
        this.fab.addEventListener('click', () => this.togglePanel());
        this.closeButton?.addEventListener('click', () => this.closePanel());
        this.messageForm?.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Click fuera del panel para cerrar
        document.addEventListener('click', (e) => this.handleOutsideClick(e));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Cargar historial si existe conversationId
        if (this.conversationId) {
            this.loadHistory();
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
        this.saveState();
        
        // Scroll to bottom
        this.scrollToBottom();
    }
    
    closePanel() {
        this.isOpen = false;
        this.panel.classList.remove('open');
        this.fab.classList.remove('hidden');
        this.saveState();
    }
    
    handleOutsideClick(e) {
        if (!this.isOpen) return;
        
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
        this.addMessage('admin', message);
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
                // Guardar conversation ID
                if (data.conversation_id) {
                    this.conversationId = data.conversation_id;
                    this.saveState();
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
        
        // Guardar en historial
        this.messageHistory.push({ sender, text, time: now.toISOString() });
        this.saveState();
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
    
    async loadHistory() {
        try {
            const response = await fetch(`/admin/assistant/history?conversation_id=${this.conversationId}`);
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.messages) {
                // Limpiar mensajes actuales
                this.messagesContainer.innerHTML = '';
                this.messageHistory = [];
                
                // Agregar mensajes del historial
                data.messages.forEach(msg => {
                    this.addMessage(msg.sender === 'admin' ? 'admin' : 'assistant', msg.message);
                });
            }
        } catch (error) {
            console.error('Error loading history:', error);
        }
    }
    
    saveState() {
        const state = {
            isOpen: this.isOpen,
            conversationId: this.conversationId,
            messageHistory: this.messageHistory
        };
        
        sessionStorage.setItem('adminFloatingAssistant', JSON.stringify(state));
    }
    
    restoreState() {
        const savedState = sessionStorage.getItem('adminFloatingAssistant');
        if (!savedState) return;
        
        try {
            const state = JSON.parse(savedState);
            
            this.conversationId = state.conversationId;
            this.messageHistory = state.messageHistory || [];
            
            // Restaurar mensajes en el UI
            if (this.messageHistory.length > 0) {
                this.messageHistory.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `admin-floating-message admin-floating-message-${msg.sender}`;
                    
                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'admin-floating-message-content';
                    contentDiv.textContent = msg.text;
                    
                    const timeDiv = document.createElement('div');
                    timeDiv.className = 'admin-floating-message-time';
                    const time = new Date (msg.time);
                    timeDiv.textContent = time.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                    
                    messageDiv.appendChild(contentDiv);
                    messageDiv.appendChild(timeDiv);
                    
                    this.messagesContainer.appendChild(messageDiv);
                });
                
                this.scrollToBottom();
            }
            
            // NO auto-abrir el panel, solo restaurar estado interno
            // El admin decide cuándo abrir el panel
            
        } catch (error) {
            console.error('Error restoring state:', error);
        }
    }
    
    clearConversation() {
        this.conversationId = null;
        this.messageHistory = [];
        this.messagesContainer.innerHTML = '';
        
        // Agregar mensaje de bienvenida
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
        
        this.saveState();
    }
}

// Inicializar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    window.adminFloatingAssistant = new AdminFloatingAssistant();
});
