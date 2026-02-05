# MyShop API Documentation

Complete API reference for the MyShop e-commerce platform.

## Base URL

```
http://localhost:8080/api
```

## Authentication

Most endpoints require authentication via Symfony's session-based authentication. 
Log in through the web interface (`/login`) before making API requests.

## Response Format

All responses are in JSON format.

### Success Response
```json
{
  "data": { ... },
  "status": "success"
}
```

### Error Response
```json
{
  "error": "Error message",
  "code": 400
}
```

---

## Product Endpoints

### List All Products

`GET /api/products`

Retrieve a list of all products with optional filtering.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search query (searches name and description) |
| `category` | string | Filter by category (e.g., "Electronics", "Books") |
| `minPrice` | float | Minimum price in dollars |
| `maxPrice` | float | Maximum price in dollars |

**Example Request:**
```bash
curl "http://localhost:8080/api/products?category=Electronics&minPrice=10&maxPrice=100"
```

**Example Response:**
```json
[
  {
    "id": "018e1234-5678-7abc-9def-1234567890ab",
    "name": "Wireless Headphones",
    "description": "High-quality wireless headphones with noise cancellation",
    "price": 79.99,
    "currency": "USD",
    "stock": 45,
    "category": "Electronics",
    "createdAt": "2026-02-05T10:30:00+00:00",
    "updatedAt": "2026-02-05T15:45:00+00:00"
  }
]
```

### Get Single Product

`GET /api/products/{id}`

Retrieve details of a specific product.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Product identifier |

**Example Request:**
```bash
curl "http://localhost:8080/api/products/018e1234-5678-7abc-9def-1234567890ab"
```

**Example Response:**
```json
{
  "id": "018e1234-5678-7abc-9def-1234567890ab",
  "name": "Wireless Headphones",
  "description": "High-quality wireless headphones with noise cancellation",
  "price": 79.99,
  "currency": "USD",
  "stock": 45,
  "category": "Electronics",
  "createdAt": "2026-02-05T10:30:00+00:00",
  "updatedAt": "2026-02-05T15:45:00+00:00"
}
```

**Error Response (404):**
```json
{
  "error": "Product not found"
}
```

### Create Product (ROLE_SELLER required)

`POST /api/products`

Create a new product. Requires ROLE_SELLER or ROLE_ADMIN.

**Request Body:**
```json
{
  "name": "New Laptop",
  "description": "High-performance laptop with 16GB RAM",
  "price": 999.99,
  "stock": 20,
  "category": "Electronics"
}
```

**Example Request:**
```bash
curl -X POST http://localhost:8080/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Laptop",
    "description": "High-performance laptop with 16GB RAM",
    "price": 999.99,
    "stock": 20,
    "category": "Electronics"
  }'
```

**Example Response (201):**
```json
{
  "id": "018e5678-90ab-7cde-9fgh-567890abcdef",
  "name": "New Laptop",
  "description": "High-performance laptop with 16GB RAM",
  "price": 999.99,
  "currency": "USD",
  "stock": 20,
  "category": "Electronics",
  "createdAt": "2026-02-05T16:00:00+00:00",
  "updatedAt": "2026-02-05T16:00:00+00:00"
}
```

### Update Product (ROLE_SELLER required)

`PUT /api/products/{id}`

Update an existing product. Requires ROLE_SELLER or ROLE_ADMIN.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Product identifier |

**Request Body (partial update allowed):**
```json
{
  "name": "Updated Laptop",
  "price": 899.99,
  "stock": 15
}
```

**Example Response (200):**
```json
{
  "id": "018e5678-90ab-7cde-9fgh-567890abcdef",
  "name": "Updated Laptop",
  "description": "High-performance laptop with 16GB RAM",
  "price": 899.99,
  "currency": "USD",
  "stock": 15,
  "category": "Electronics",
  "createdAt": "2026-02-05T16:00:00+00:00",
  "updatedAt": "2026-02-05T16:30:00+00:00"
}
```

---

## Cart Endpoints

### View Cart

`GET /api/cart`

Retrieve the current user's shopping cart.

**Example Request:**
```bash
curl http://localhost:8080/api/cart
```

**Example Response:**
```json
{
  "id": "018e9abc-def1-7234-9567-890abcdef123",
  "items": [
    {
      "productId": "018e1234-5678-7abc-9def-1234567890ab",
      "productName": "Wireless Headphones",
      "quantity": 2,
      "priceInCents": 7999,
      "subtotalInCents": 15998
    },
    {
      "productId": "018e5678-90ab-7cde-9fgh-567890abcdef",
      "productName": "Laptop",
      "quantity": 1,
      "priceInCents": 89999,
      "subtotalInCents": 89999
    }
  ],
  "total": "$1,059.97",
  "totalQuantity": 3,
  "updatedAt": "2026-02-05T17:00:00+00:00"
}
```

**Empty Cart Response:**
```json
{
  "id": null,
  "items": [],
  "total": "$0.00",
  "totalQuantity": 0
}
```

### Add Item to Cart

`POST /api/cart/items`

Add a product to the cart or increase quantity if already exists.

**Request Body:**
```json
{
  "productId": "018e1234-5678-7abc-9def-1234567890ab",
  "quantity": 2
}
```

**Example Request:**
```bash
curl -X POST http://localhost:8080/api/cart/items \
  -H "Content-Type: application/json" \
  -d '{
    "productId": "018e1234-5678-7abc-9def-1234567890ab",
    "quantity": 2
  }'
```

**Example Response (200):**
```json
{
  "message": "Item added to cart",
  "cart": {
    "id": "018e9abc-def1-7234-9567-890abcdef123",
    "totalItems": 1,
    "totalQuantity": 2,
    "total": "$159.98"
  }
}
```

**Error Response (400) - Out of Stock:**
```json
{
  "error": "Product is out of stock"
}
```

**Error Response (400) - Insufficient Stock:**
```json
{
  "error": "Insufficient stock. Available: 10"
}
```

### Update Cart Item

`PUT /api/cart/items/{productId}`

Update the quantity of an item in the cart.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `productId` | UUID | Product identifier |

**Request Body:**
```json
{
  "quantity": 5
}
```

**Example Request:**
```bash
curl -X PUT http://localhost:8080/api/cart/items/018e1234-5678-7abc-9def-1234567890ab \
  -H "Content-Type: application/json" \
  -d '{"quantity": 5}'
```

**Example Response (200):**
```json
{
  "message": "Cart updated",
  "cart": {
    "id": "018e9abc-def1-7234-9567-890abcdef123",
    "totalItems": 1,
    "totalQuantity": 5,
    "total": "$399.95"
  }
}
```

### Remove Item from Cart

`DELETE /api/cart/items/{productId}`

Remove a product from the cart.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `productId` | UUID | Product identifier |

**Example Request:**
```bash
curl -X DELETE http://localhost:8080/api/cart/items/018e1234-5678-7abc-9def-1234567890ab
```

**Example Response (200):**
```json
{
  "message": "Item removed from cart"
}
```

### Clear Cart

`DELETE /api/cart`

Remove all items from the cart.

**Example Request:**
```bash
curl -X DELETE http://localhost:8080/api/cart
```

**Example Response (200):**
```json
{
  "message": "Cart cleared"
}
```

---

## Order Endpoints

### Create Order (Checkout)

`POST /api/orders`

Create an order from the current cart contents.

**Request Body:**
```json
{
  "shippingAddress": "123 Main St, Apt 4B, New York, NY 10001, USA"
}
```

**Example Request:**
```bash
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "shippingAddress": "123 Main St, Apt 4B, New York, NY 10001, USA"
  }'
```

**Example Response (201):**
```json
{
  "orderNumber": "ORD-20260205-0001",
  "totalAmount": "$1,059.97",
  "status": "pending",
  "shippingAddress": "123 Main St, Apt 4B, New York, NY 10001, USA",
  "items": [
    {
      "productName": "Wireless Headphones",
      "quantity": 2,
      "priceInCents": 7999,
      "subtotalInCents": 15998
    }
  ],
  "createdAt": "2026-02-05T17:30:00+00:00"
}
```

**Error Response (400) - Empty Cart:**
```json
{
  "error": "Cannot checkout with an empty cart"
}
```

### List User Orders

`GET /api/orders`

Retrieve all orders for the current user.

**Example Request:**
```bash
curl http://localhost:8080/api/orders
```

**Example Response:**
```json
[
  {
    "orderNumber": "ORD-20260205-0001",
    "totalAmount": "$1,059.97",
    "status": "pending",
    "itemCount": 2,
    "createdAt": "2026-02-05T17:30:00+00:00"
  },
  {
    "orderNumber": "ORD-20260204-0023",
    "totalAmount": "$249.99",
    "status": "completed",
    "itemCount": 1,
    "createdAt": "2026-02-04T14:20:00+00:00"
  }
]
```

### Get Order Details

`GET /api/orders/{orderNumber}`

Retrieve details of a specific order.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `orderNumber` | string | Order number (e.g., "ORD-20260205-0001") |

**Example Request:**
```bash
curl http://localhost:8080/api/orders/ORD-20260205-0001
```

**Example Response:**
```json
{
  "orderNumber": "ORD-20260205-0001",
  "totalAmount": "$1,059.97",
  "status": "pending",
  "shippingAddress": "123 Main St, Apt 4B, New York, NY 10001, USA",
  "items": [
    {
      "productName": "Wireless Headphones",
      "quantity": 2,
      "priceInCents": 7999,
      "subtotalInCents": 15998
    },
    {
      "productName": "Laptop",
      "quantity": 1,
      "priceInCents": 89999,
      "subtotalInCents": 89999
    }
  ],
  "createdAt": "2026-02-05T17:30:00+00:00",
  "updatedAt": "2026-02-05T17:30:00+00:00"
}
```

**Error Response (404):**
```json
{
  "error": "Order not found"
}
```

---

## Chatbot Endpoint

### Send Message

`POST /api/chat`

Send a message to the AI chatbot assistant.

**Request Body:**
```json
{
  "message": "What products do you have in Electronics?"
}
```

**Example Request:**
```bash
curl -X POST http://localhost:8080/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "What products do you have in Electronics?"}'
```

**Example Response:**
```json
{
  "response": "I found 15 products in the Electronics category, including laptops, headphones, smartphones, and tablets. Would you like to see specific products?"
}
```

---

## Health Check

### Check API Health

`GET /health`

Check if the API is running and database is connected.

**Example Request:**
```bash
curl http://localhost:8080/health
```

**Example Response (200):**
```json
{
  "status": "ok",
  "timestamp": "2026-02-05T18:00:00+00:00",
  "database": "connected"
}
```

---

## Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found |
| 500 | Internal Server Error |

---

## Rate Limiting

Currently no rate limiting is implemented. This may be added in future versions.

---

## Pagination

Product and order lists support pagination:

```
GET /api/products?page=2&limit=20
```

## Categories

Available product categories:

- Electronics
- Clothing
- Books
- Home

---

For more information, see the main [README.md](README.md) file.
