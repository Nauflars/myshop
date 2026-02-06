// Admin Assistant JavaScript Module
// Part of spec-007: Admin Virtual Assistant

document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatMessages = document.getElementById('chatMessages');
    const sendBtn = document.getElementById('sendBtn');
    const statusMessage = document.getElementById('statusMessage');
    const messageCount = document.getElementById('messageCount');
    const clearContextBtn = document.getElementById('clearContextBtn');
    const newConversationBtn = document.getElementById('newConversationBtn');

    let conversationId = null;
    let isProcessing = false;

    // Auto-scroll to bottom of messages
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Show status message
    function showStatus(message, type = 'info') {
        statusMessage.textContent = message;
        statusMessage.className = `status-message show ${type}`;
        setTimeout(() => {
            statusMessage.classList.remove('show');
        }, 5000);
    }

    // Add message to chat
    function addMessage(sender, text, time = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${sender}`;
        
        const displayTime = time || new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        const senderName = sender === 'admin' ? 'Tú' : 'Asistente';
        
        messageDiv.innerHTML = `
            <div class="message-header">
                <strong>${senderName}</strong>
                <span class="message-time">${displayTime}</span>
            </div>
            <div class="message-content">${text.replace(/\n/g, '<br>')}</div>
        `;
        
        // Remove welcome message if present
        const welcomeMessage = chatMessages.querySelector('.welcome-message');
        if (welcomeMessage) {
            welcomeMessage.remove();
        }
        
        chatMessages.appendChild(messageDiv);
        scrollToBottom();
        
        // Update message count
        const currentCount = parseInt(messageCount.textContent);
        messageCount.textContent = currentCount + 1;
    }

    // Add loading indicator
    function addLoadingIndicator() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'message message-assistant';
        loadingDiv.id = 'loadingIndicator';
        loadingDiv.innerHTML = `
            <div class="message-header">
                <strong>Asistente</strong>
            </div>
            <div class="message-content">
                <em>Procesando...</em>
                <span class="loading-dots">
                    <span>.</span><span>.</span><span>.</span>
                </span>
            </div>
        `;
        chatMessages.appendChild(loadingDiv);
        scrollToBottom();
        
        // Add animation for loading dots
        let dotCount = 0;
        const loadingInterval = setInterval(() => {
            const dots = loadingDiv.querySelectorAll('.loading-dots span');
            dots.forEach((dot, index) => {
                dot.style.opacity = index <= (dotCount % 3) ? '1' : '0.3';
            });
            dotCount++;
        }, 500);
        
        return () => {
            clearInterval(loadingInterval);
            loadingDiv.remove();
        };
    }

    // Send message
    async function sendMessage(message) {
        if (isProcessing) {
            showStatus('Ya hay un mensaje en proceso...', 'info');
            return;
        }

        isProcessing = true;
        sendBtn.disabled = true;
        chatInput.disabled = true;

        // Add user message to chat
        addMessage('admin', message);

        // Clear input
        chatInput.value = '';
        chatInput.style.height = 'auto';

        // Show loading
        const removeLoading = addLoadingIndicator();

        try {
            const response = await fetch('/admin/assistant/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: conversationId,
                }),
            });

            const data = await response.json();
            removeLoading();

            if (data.success) {
                conversationId = data.conversation_id;
                addMessage('assistant', data.reply);
            } else {
                throw new Error(data.error || 'Error desconocido');
            }
        } catch (error) {
            removeLoading();
            showStatus(`Error: ${error.message}`, 'error');
            // Add error message to chat
            addMessage('assistant', `❌ Lo siento, ocurrió un error al procesar tu mensaje: ${error.message}`);
        } finally {
            isProcessing = false;
            sendBtn.disabled = false;
            chatInput.disabled = false;
            chatInput.focus();
        }
    }

    // Handle form submission
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = chatInput.value.trim();
        if (!message) {
            showStatus('Por favor escribe un mensaje', 'error');
            return;
        }

        sendMessage(message);
    });

    // Auto-resize textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Handle Enter key (Shift+Enter for new line)
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });

    // Clear context
    clearContextBtn.addEventListener('click', async function() {
        if (!confirm('¿Limpiar el contexto conversacional? (Referencias a productos, usuarios, etc. se olvidarán)')) {
            return;
        }

        try {
            const response = await fetch('/admin/assistant/clear-context', {
                method: 'POST',
            });

            const data = await response.json();
            if (data.success) {
                showStatus('Contexto limpiado correctamente', 'success');
            } else {
                throw new Error(data.error || 'Error al limpiar contexto');
            }
        } catch (error) {
            showStatus(`Error: ${error.message}`, 'error');
        }
    });

    // New conversation
    newConversationBtn.addEventListener('click', async function() {
        if (!confirm('¿Iniciar una nueva conversación? Se perderá el historial actual.')) {
            return;
        }

        try {
            const response = await fetch('/admin/assistant/new', {
                method: 'POST',
            });

            const data = await response.json();
            if (data.success) {
                conversationId = data.conversation_id;
                
                // Clear messages
                chatMessages.innerHTML = `
                    <div class="welcome-message">
                        <h3>👋 ¡Nueva conversación iniciada!</h3>
                        <p>El historial anterior ha sido archivado. ¿En qué puedo ayudarte?</p>
                    </div>
                `;
                
                // Reset message count
                messageCount.textContent = '0';
                
                showStatus('Nueva conversación iniciada', 'success');
                chatInput.focus();
            } else {
                throw new Error(data.error || 'Error al crear conversación');
            }
        } catch (error) {
            showStatus(`Error: ${error.message}`, 'error');
        }
    });

    // Initial focus
    chatInput.focus();
    scrollToBottom();
});
