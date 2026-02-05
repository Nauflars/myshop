// Cart Management JavaScript

class CartManager {
    constructor() {
        this.init();
    }

    init() {
        this.updateCartBadge();
        this.attachEventListeners();
    }

    async updateCartBadge() {
        try {
            const response = await fetch('/api/cart');
            if (response.ok) {
                const cart = await response.json();
                const badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.textContent = cart.totalQuantity || 0;
                }
            }
        } catch (error) {
            console.error('Error updating cart badge:', error);
        }
    }

    async addToCart(productId, quantity = 1) {
        try {
            const response = await fetch('/api/cart/items', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ productId, quantity }),
            });

            if (response.ok) {
                const cart = await response.json();
                this.updateCartBadge();
                this.showNotification('Product added to cart!');
                return cart;
            } else {
                const error = await response.json();
                this.showNotification(error.error || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            this.showNotification('Network error', 'error');
        }
    }

    async updateQuantity(productId, quantity) {
        try {
            const response = await fetch(`/api/cart/items/${productId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ quantity }),
            });

            if (response.ok) {
                const cart = await response.json();
                this.updateCartBadge();
                this.updateCartDisplay(cart);
                return cart;
            } else {
                const error = await response.json();
                this.showNotification(error.error || 'Failed to update cart', 'error');
            }
        } catch (error) {
            console.error('Error updating cart:', error);
            this.showNotification('Network error', 'error');
        }
    }

    async removeFromCart(productId) {
        try {
            const response = await fetch(`/api/cart/items/${productId}`, {
                method: 'DELETE',
            });

            if (response.ok) {
                const cart = await response.json();
                this.updateCartBadge();
                this.updateCartDisplay(cart);
                this.showNotification('Product removed from cart');
                return cart;
            } else {
                const error = await response.json();
                this.showNotification(error.error || 'Failed to remove from cart', 'error');
            }
        } catch (error) {
            console.error('Error removing from cart:', error);
            this.showNotification('Network error', 'error');
        }
    }

    updateCartDisplay(cart) {
        const cartContainer = document.getElementById('cart-items');
        if (!cartContainer) return;

        if (cart.items.length === 0) {
            cartContainer.innerHTML = '<p>Your cart is empty</p>';
            return;
        }

        cartContainer.innerHTML = cart.items.map(item => `
            <div class="cart-item" data-product-id="${item.productId}">
                <div class="item-info">
                    <h3>${item.productName}</h3>
                    <p class="price">${item.price}</p>
                </div>
                <div class="item-quantity">
                    <button class="qty-btn" data-action="decrease">-</button>
                    <span class="quantity">${item.quantity}</span>
                    <button class="qty-btn" data-action="increase">+</button>
                </div>
                <div class="item-subtotal">
                    <span>${item.subtotal}</span>
                </div>
                <button class="remove-btn" data-product-id="${item.productId}">Remove</button>
            </div>
        `).join('');

        const total = document.getElementById('cart-total');
        if (total) {
            total.textContent = cart.total;
        }

        this.attachCartItemListeners();
    }

    attachEventListeners() {
        // Add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart-btn')) {
                e.preventDefault();
                const productId = e.target.dataset.productId;
                const quantity = parseInt(e.target.dataset.quantity || 1);
                this.addToCart(productId, quantity);
            }
        });
    }

    attachCartItemListeners() {
        // Quantity buttons
        document.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const item = e.target.closest('.cart-item');
                const productId = item.dataset.productId;
                const quantitySpan = item.querySelector('.quantity');
                let quantity = parseInt(quantitySpan.textContent);

                if (e.target.dataset.action === 'increase') {
                    quantity++;
                } else if (e.target.dataset.action === 'decrease' && quantity > 1) {
                    quantity--;
                }

                await this.updateQuantity(productId, quantity);
            });
        });

        // Remove buttons
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const productId = e.target.dataset.productId;
                await this.removeFromCart(productId);
            });
        });
    }

    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Initialize cart manager
const cartManager = new CartManager();

// Export for use in other scripts
window.cartManager = cartManager;
