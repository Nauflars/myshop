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
        
        const displayTime = time || new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
        const senderName = sender === 'admin' ? 'You' : 'Assistant';
        
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
                <strong>Assistant</strong>
            </div>
            <div class="message-content">
                <em>Processing...</em>
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
            showStatus('A message is already being processed...', 'info');
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
                throw new Error(data.error || 'Unknown error');
            }
        } catch (error) {
            removeLoading();
            showStatus(`Error: ${error.message}`, 'error');
            // Add error message to chat
            addMessage('assistant', `❌ Sorry, an error occurred while processing your message: ${error.message}`);
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
            showStatus('Please write a message', 'error');
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
        if (!confirm('Clear conversational context? (References to products, users, etc. will be forgotten)')) {
            return;
        }

        try {
            const response = await fetch('/admin/assistant/clear-context', {
                method: 'POST',
            });

            const data = await response.json();
            if (data.success) {
                showStatus('Context cleared successfully', 'success');
            } else {
                throw new Error(data.error || 'Error clearing context');
            }
        } catch (error) {
            showStatus(`Error: ${error.message}`, 'error');
        }
    });

    // New conversation
    newConversationBtn.addEventListener('click', async function() {
        if (!confirm('Start a new conversation? Current history will be lost.')) {
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
                        <h3>👋 New conversation started!</h3>
                        <p>Previous history has been archived. How can I help you?</p>
                    </div>
                `;
                
                // Reset message count
                messageCount.textContent = '0';
                
                showStatus('New conversation started', 'success');
                chatInput.focus();
            } else {
                throw new Error(data.error || 'Error creating conversation');
            }
        } catch (error) {
            showStatus(`Error: ${error.message}`, 'error');
        }
    });

    // Initial focus
    chatInput.focus();
    scrollToBottom();
});
