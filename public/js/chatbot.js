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
            this.addMessage('bot', 'Hello! I\'m your AI shopping assistant. How can I help you today?');
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
                
                // Parse and structure the response intelligently
                this.processAndDisplayResponse(data.response);
                
                // Trigger cart badge update if response mentions cart-related actions
                if (this.isCartAction(data.response)) {
                    window.dispatchEvent(new Event('cartUpdated'));
                }
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
    
    processAndDisplayResponse(text) {
        // Try to detect and parse structured content from plain text
        const structured = this.detectStructuredContent(text);
        
        if (structured) {
            this.addMessage('bot', text, structured);
        } else {
            this.addMessage('bot', text);
        }
    }
    
    detectStructuredContent(text) {
        // Detect product list (numbered with **name** - $price pattern)
        if (this.isProductList(text)) {
            return {
                type: 'product_list',
                data: this.parseProductList(text)
            };
        }
        
        // Detect action confirmation (contains ✓ or mentions "añadido/agregado al carrito")
        if (this.isActionConfirmation(text)) {
            return {
                type: 'action_confirmation',
                data: this.parseActionConfirmation(text)
            };
        }
        
        return null;
    }
    
    isProductList(text) {
        // Check if text contains numbered list with product pattern: 1. **name** - $price
        const productPattern = /\d+\.\s*\*\*[^*]+\*\*\s*-\s*\$[\d,.]+/;
        return productPattern.test(text);
    }
    
    parseProductList(text) {
        const products = [];
        
        // Extract intro text (everything before first numbered item)
        const introMatch = text.match(/^([\s\S]*?)(?=\d+\.\s*\*\*)/);
        const intro = introMatch ? introMatch[1].trim() : '';
        
        // Extract outro text (everything after last product)
        const productSection = text.match(/\d+\.\s*\*\*[\s\S]*/)[0];
        const outro = text.substring(text.indexOf(productSection) + productSection.length).trim();
        
        // Parse each product: 1. **Name** - $Price *Description*
        const productRegex = /\d+\.\s*\*\*([^*]+)\*\*\s*-\s*\$([\d,.]+)(?:\s*\*([^*]*)\*)?/g;
        let match;
        
        while ((match = productRegex.exec(text)) !== null) {
            products.push({
                name: match[1].trim(),
                price: parseFloat(match[2].replace(',', '')),
                currency: 'USD',
                description: match[3] ? match[3].trim() : null
            });
        }
        
        return {
            intro,
            products,
            outro
        };
    }
    
    isActionConfirmation(text) {
        const confirmationKeywords = [
            'added to cart',
            'added to the cart',
            'I added',
            'I\'ve added',
            'I have added',
            'removed from cart',
            'removed from the cart',
            'deleted from cart'
        ];
        
        const lowerText = text.toLowerCase();
        return confirmationKeywords.some(keyword => lowerText.includes(keyword));
    }
    
    parseActionConfirmation(text) {
        const isSuccess = !/error|problem|could not|failed/i.test(text);
        
        // Try to extract quantity and product name
        const quantityMatch = text.match(/(\d+)\s*(?:unit|units)?\s*(?:of)?\s*([^.]+?)(?:\s*to cart|\s*has|\s*were)/i);
        
        let items = [];
        if (quantityMatch) {
            items.push({
                name: quantityMatch[2].trim(),
                quantity: parseInt(quantityMatch[1])
            });
        }
        
        // Try to extract total
        const totalMatch = text.match(/total[^$]*\$([\d,.]+)/i);
        const total = totalMatch ? parseFloat(totalMatch[1].replace(',', '')) : undefined;
        
        return {
            success: isSuccess,
            message: text.split('.')[0] + '.',
            items,
            total,
            currency: 'USD'
        };
    }

    isCartAction(message) {
        // Check if the bot response indicates a cart action occurred
        const cartKeywords = [
            'added to cart',
            'added to the cart',
            'I added',
            'I\'ve added',
            'I have added',
            'cart updated',
            'product added',
            'removed from cart',
            'removed from the cart',
            'deleted from cart',
            'I removed',
            'I deleted',
            'cart emptied',
            'order created',
            'purchase completed',
            'add',
            'total',
            'item',
            'quantity'
        ];
        
        const lowerMessage = message.toLowerCase();
        // Check if message mentions cart-related words
        const hasCartKeyword = cartKeywords.some(keyword => lowerMessage.includes(keyword));
        
        // Also trigger on success messages that mention numbers (likely quantities)
        const hasNumber = /\d+/.test(message);
        const mentionsCart = lowerMessage.includes('cart');
        
        return hasCartKeyword || (hasNumber && mentionsCart);
    }

    async clearChat() {
        if (!this.conversationId) {
            // Just clear UI if no conversation exists
            this.messagesContainer.innerHTML = '';
            this.addMessage('bot', 'Hello! I\'m your AI shopping assistant. How can I help you today?');
            return;
        }

        if (!confirm('Are you sure you want to clear the conversation history?')) {
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
                this.addMessage('bot', 'Hello! I\'m your AI shopping assistant. How can I help you today?');
            } else {
                const error = await response.json();
                alert('Error clearing: ' + (error.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Clear chat error:', error);
            alert('Error clearing conversation');
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
                    this.addMessage('bot', 'Hello! I\'m your AI shopping assistant. How can I help you today?');
                }
            } else {
                // Error loading, show welcome
                this.addMessage('bot', 'Hello! Let\'s continue where we left off. How can I help you?');
            }
        } catch (error) {
            console.error('Error loading conversation history:', error);
            this.addMessage('bot', 'Hello! Let\'s continue where we left off. How can I help you?');
        }
    }

    addMessage(role, content, structured = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${role}-message`;
        
        // Add avatar for bot messages
        if (role === 'bot') {
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.textContent = '🤖';
            messageDiv.appendChild(avatar);
        }
        
        // Create message content container
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        // Render structured content if provided, otherwise plain text
        if (structured) {
            contentDiv.innerHTML = this.renderStructuredContent(structured);
        } else {
            contentDiv.textContent = content;
        }
        
        messageDiv.appendChild(contentDiv);
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    renderStructuredContent(structured) {
        switch (structured.type) {
            case 'product_list':
                return this.renderProductList(structured.data);
            case 'product_info':
                return this.renderProductInfo(structured.data);
            case 'action_confirmation':
                return this.renderActionConfirmation(structured.data);
            case 'cart_summary':
                return this.renderCartSummary(structured.data);
            default:
                return structured.data.message || '';
        }
    }
    
    renderProductList(data) {
        // Handle both formats: array of products or {intro, products, outro}
        let intro = '';
        let products = [];
        let outro = '';
        
        if (Array.isArray(data)) {
            products = data;
        } else if (data.products) {
            intro = data.intro || '';
            products = data.products;
            outro = data.outro || '';
        }
        
        if (!products || products.length === 0) {
            return '<p>No products found.</p>';
        }
        
        let html = '';
        
        // Add intro text if present
        if (intro) {
            html += `<p style="margin-bottom: 1rem;">${this.escapeHtml(intro)}</p>`;
        }
        
        // Render products
        const maxDisplay = 10;
        products.slice(0, maxDisplay).forEach((product, index) => {
            if (index > 0) html += '<div class="product-divider"></div>';
            html += `
                <div class="product-info">
                    <div class="product-name">${index + 1}. ${this.escapeHtml(product.name)}</div>
                    <div class="product-price">${this.formatPrice(product.price, product.currency)}</div>
                    ${product.description ? `<div class="product-stock">${this.escapeHtml(product.description)}</div>` : ''}
                    ${product.stock ? `<div class="product-stock">Stock: ${product.stock}</div>` : ''}
                </div>
            `;
        });
        
        if (products.length > maxDisplay) {
            html += '<div class="product-divider"></div>';
            html += `<p style="color: var(--text-light); font-size: 0.9em;">... and ${products.length - maxDisplay} more products</p>`;
        }
        
        // Add outro text if present
        if (outro) {
            html += `<p style="margin-top: 1rem; color: var(--text-light); font-size: 0.95em;">${this.escapeHtml(outro)}</p>`;
        }
        
        return html;
    }
    
    renderProductInfo(product) {
        return `
            <div class="product-info">
                <div class="product-name">${this.escapeHtml(product.name)}</div>
                <div class="product-price">${this.formatPrice(product.price, product.currency)}</div>
                ${product.description ? `<p style="margin-top: 0.5rem; color: var(--text-light);">${this.escapeHtml(product.description)}</p>` : ''}
                ${product.stock ? `<div class="product-stock">Available stock: ${product.stock} units</div>` : ''}
            </div>
        `;
    }
    
    renderActionConfirmation(data) {
        const iconClass = data.success ? 'success' : 'error';
        const icon = data.success ? '✓' : '✗';
        
        let html = `
            <div class="confirmation-message">
                <div class="confirmation-icon ${iconClass}">${icon}</div>
                <div class="confirmation-details">
                    <div><strong>${this.escapeHtml(data.message)}</strong></div>
        `;
        
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                html += `
                    <div class="confirmation-item">
                        ${this.escapeHtml(item.name)} × ${item.quantity}
                        ${item.price ? ` - ${this.formatPrice(item.price, item.currency)}` : ''}
                    </div>
                `;
            });
        }
        
        if (data.total !== undefined) {
            html += `
                <div class="confirmation-total">
                    Cart total: ${this.formatPrice(data.total, data.currency || 'USD')}
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        return html;
    }
    
    renderCartSummary(data) {
        if (!data.items || data.items.length === 0) {
            return '<p>Your cart is empty. <a href="/products" style="color: var(--main-color);">View products</a></p>';
        }
        
        let html = '<div><strong>Your cart:</strong></div>';
        data.items.forEach(item => {
            html += `
                <div class="confirmation-item">
                    ${this.escapeHtml(item.name)} × ${item.quantity} - ${this.formatPrice(item.price, item.currency)}
                </div>
            `;
        });
        
        html += `
            <div class="confirmation-total">
                Total: ${this.formatPrice(data.total, data.currency || 'USD')}
            </div>
        `;
        
        return html;
    }
    
    formatPrice(amount, currency = 'USD') {
        // If amount is an object with 'amount' property (from API), use that value in cents
        if (typeof amount === 'object' && amount !== null && 'amount' in amount) {
            amount = amount.amount / 100;
        }
        // If amount is a number > 1000, assume it's in cents
        else if (typeof amount === 'number' && amount > 1000) {
            amount = amount / 100;
        }
        
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showTypingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'typing-indicator';
        indicator.id = 'typing-indicator';
        
        const dots = document.createElement('div');
        dots.className = 'typing-indicator-dots';
        dots.innerHTML = '<span></span><span></span><span></span>';
        
        indicator.appendChild(dots);
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
