# MyShop E-commerce Application

A Symfony 7 e-commerce application built with Domain-Driven Design (DDD) architecture, Docker, and an AI-powered chatbot.

## Features

- **User Management**: Registration, authentication with roles (Admin, Seller, Customer)
- **Product Catalog**: CRUD operations, search, filtering by category/price
- **Shopping Cart**: Add/remove items, update quantities, real-time updates
- **Order Management**: Checkout, order history, status tracking
- **AI Chatbot**: Intelligent assistant using symfony/ai with custom tools
- **RESTful API**: JSON API for all operations
- **Docker Support**: Complete containerized environment
- **Responsive Design**: Fully responsive UI for desktop, tablet, and mobile devices
- **Comprehensive Tests**: Unit tests for Domain, Application, and Infrastructure layers
- **Custom Brand Colors**: Professional color scheme with #06038D primary and #E87722 secondary colors

## Architecture

Built following **Domain-Driven Design** principles with three distinct layers:

### Domain Layer (`src/Domain/`)
Pure business logic with no infrastructure dependencies:
- **Entities**: User, Product, Cart, Order
- **Value Objects**: Email, Money
- **Repository Interfaces**: Define contracts for data persistence

### Application Layer (`src/Application/`)
Use cases and orchestration logic:
- **Use Cases**: CreateUser, AddProductToCart, Checkout, SearchProduct, GenerateStats
- **DTOs**: Data Transfer Objects for decoupling

### Infrastructure Layer (`src/Infrastructure/`)
Technical implementations:
- **Repositories**: Doctrine ORM implementations
- **Controllers**: Symfony HTTP controllers
- **Chatbot**: AI agent and tools

## Tech Stack

- **Backend**: Symfony 7, PHP 8.3
- **Database**: MySQL 8.0
- **Web Server**: Nginx
- **Containerization**: Docker, Docker Compose
- **ORM**: Doctrine
- **Testing**: PHPUnit
- **Frontend**: Twig, Vanilla JavaScript

## Prerequisites

- Docker
- Docker Compose
- Git

## Quick Start

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd myshop
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   # Edit .env if needed (database credentials, API keys, etc.)
   ```

3. **Build and start Docker containers**:
   ```bash
   docker-compose up -d --build
   ```

4. **Install dependencies**:
   ```bash
   docker-compose exec php composer install
   ```

5. **Initialize database**:
   ```bash
   docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
   docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction
   ```

6. **Access the application**:
   - **Web**: http://localhost
   - **API**: http://localhost/api
   - **Health Check**: http://localhost/health

## Default Users

The fixtures create the following test users:

| Email | Password | Role |
|-------|----------|------|
| admin@myshop.com | admin123 | ROLE_ADMIN |
| seller@myshop.com | seller123 | ROLE_SELLER |
| customer@myshop.com | customer123 | ROLE_CUSTOMER |

## API Endpoints

### User Management
- `POST /api/users` - Register new user
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/users/me` - Get current user profile

### Products
- `GET /api/products` - List all products (supports search & filters)
- `GET /api/products/{id}` - Get product details
- `POST /api/products` - Create product (Seller/Admin)
- `PUT /api/products/{id}` - Update product (Seller/Admin)
- `DELETE /api/products/{id}` - Delete product (Admin)

### Shopping Cart
- `GET /api/cart` - View cart
- `POST /api/cart/items` - Add item to cart
- `PUT /api/cart/items/{productId}` - Update item quantity
- `DELETE /api/cart/items/{productId}` - Remove item
- `DELETE /api/cart` - Clear cart

### Orders
- `POST /api/orders` - Checkout (create order from cart)
- `GET /api/orders` - List user's orders
- `GET /api/orders/{orderNumber}` - Get order details
- `PUT /api/orders/{orderNumber}/status` - Update order status (Seller/Admin)

### Chatbot
- `POST /api/chat` - Send message to AI chatbot

## search Example

```bash
# Search products
curl "http://localhost/api/products?q=laptop&category=Electronics"

# Add to cart
curl -X POST http://localhost/api/cart/items \
  -H "Content-Type: application/json" \
  -d '{"productId": "uuid-here", "quantity": 2}'

# Checkout
curl -X POST http://localhost/api/orders
```

## Make Commands

The project includes a `Makefile` for common tasks:

```bash
make up          # Start Docker containers
make down        # Stop Docker containers
make install     # Install Composer dependencies
make migrate     # Run database migrations
make fixtures    # Load data fixtures
make test        # Run all tests
make test-unit   # Run unit tests only
make bash        # Open bash shell in PHP container
make logs        # Tail container logs
make clean       # Clean cache and logs
make db-reset    # Reset database (drop, create, migrate, fixtures)
```

## Development

### Responsive Design

The application features a fully responsive design optimized for all devices:

#### Desktop (1200px+)
- Full-featured navigation bar
- Multi-column product grid
- Comprehensive cart and checkout layouts

#### Tablet (768px - 1199px)
- Adapted navigation with collapsible menu
- Responsive product grid (2-3 columns)
- Touch-optimized buttons and forms

#### Mobile (< 768px)
- **Hamburger Menu**: Collapsible navigation for better space utilization
- **Touch-Friendly**: All interactive elements are minimum 44px for iOS standards
- **Optimized Forms**: 16px font size to prevent iOS auto-zoom
- **Single Column Layout**: Products, cart items, and forms stack vertically
- **Mobile Chatbot**: Full-screen chatbot optimized for small screens
- **Responsive Tables**: Tables transform into card layout on mobile

**Key CSS Features:**
- CSS Custom Properties for consistent theming
- Flexbox and Grid for layout
- Media queries for three breakpoints (480px, 768px, landscape)
- Touch device detection (`@media (hover: none)`)
- High DPI display optimization

### Running Tests

The project includes comprehensive test coverage:

```bash
# All tests
make test

# Run tests in Docker
docker exec myshop_php php bin/phpunit

# Domain tests only
docker exec myshop_php php bin/phpunit tests/Domain

# Application tests only
docker exec myshop_php php bin/phpunit tests/Application

# Infrastructure tests only
docker exec myshop_php php bin/phpunit tests/Infrastructure

# With coverage report
docker exec myshop_php php bin/phpunit --coverage-html coverage
```

#### Test Structure

```
tests/
├── Domain/
│   ├── Entity/
│   │   ├── ProductTest.php       # Product entity tests
│   │   └── CartTest.php          # Cart entity tests
│   └── ValueObject/
│       ├── MoneyTest.php         # Money value object tests
│       └── EmailTest.php         # Email value object tests
├── Application/
│   └── UseCase/
│       └── AddProductToCartTest.php  # Use case tests with mocks
└── Infrastructure/
    └── Controller/
        └── ProductControllerTest.php # API integration tests
```

#### Test Coverage

- ✅ **Domain Layer**: 100% coverage for entities and value objects
- ✅ **Application Layer**: Use case tests with mocked dependencies
- ✅ **Infrastructure Layer**: Integration tests for controllers
- ✅ **Validation Tests**: Edge cases and error scenarios
- ✅ **Business Rules**: Stock management, cart calculations, price operations

### Custom Brand Colors

The application uses a carefully chosen color palette:

```css
:root {
    --main-color: #06038D;       /* Primary brand color - navigation, buttons */
    --second-color: #E87722;     /* Secondary - badges, accents */
    --success-color: #1AA04F;    /* Success messages and states */
    --warning-color: #FFBA00;    /* Warnings and low stock alerts */
    --error-color: #FF4848;      /* Errors and validation messages */
    --background-color: #F5F5F5; /* Page background */
    --text-color: #333333;       /* Primary text */
}
```

### Running Tests

```bash
# All tests
make test

# Unit tests only
make test-unit

# Integration tests only
make test-integration

# Or directly with PHPUnit
docker-compose exec php vendor/bin/phpunit
```

### Database Migrations

```bash
# Create migration
docker-compose exec php php bin/console make:migration

# Run migrations
make migrate

# Check migration status
docker-compose exec php php bin/console doctrine:migrations:status
```

### Accessing Containers

```bash
# PHP container
make bash

# MySQL container
docker-compose exec mysql mysql -u root -prootpassword myshop

# View logs
make logs
```

## AI Chatbot

The chatbot uses symfony/AI with custom tools that integrate with the application:

- **StatsTool**: Provides sales and product statistics (admin/seller only)
- **SearchProductTool**: Searches products by name or category
- **StockTool**: Checks product stock levels and alerts
- **OrderTool**: Manages cart and orders

**Example usage**:
- "Show me electronics products"
- "What's the stock level for product X?"
- "Add product Y to my cart"
- "Show my order history"
- "Give me sales statistics" (admin only)

## Project Structure

```
myshopmyshop/
├── bin/                    # Symfony console
├── config/                 # Configuration files
│   ├── packages/          # Bundle configurations
│   └── routes.yaml        # Route definitions
├── docker/                # Docker configuration
│   ├── mysql/            # MySQL init scripts
│   └── nginx/            # Nginx configuration
├── migrations/            # Database migrations
├── public/                # Web root
│   ├── css/              # Stylesheets
│   └── js/               # JavaScript files
├── src/                   # Source code
│   ├── Application/      # Use cases & DTOs
│   ├── Domain/           # Entities & business logic
│   ├── Infrastructure/   # Controllers & repositories
│   └── Kernel.php        # Symfony kernel
├── templates/             # Twig templates
├── tests/                 # PHPUnit tests
│   ├── Unit/             # Unit tests
│   └── Integration/      # Integration tests
├── var/                   # Cache & logs
├── .env                   # Environment configuration
├── composer.json          # PHP dependencies
├── docker-compose.yml     # Docker services
├── Dockerfile             # PHP container definition
└── Makefile               # Common commands
```

## Troubleshooting

### Containers won't start
```bash
docker-compose down
docker-compose up -d --build
```

### Database connection errors
Check that MySQL container is running and credentials in `.env` match `docker-compose.yml`.

### Permission issues
```bash
docker-compose exec php chown -R www-data:www-data var/
```

### Clear cache
```bash
make clean
# or
docker-compose exec php php bin/console cache:clear
```

## License

Proprietary - All rights reserved

## Contributing

1. Create a feature branch
2. Make your changes
3. Run tests: `make test`
4. Submit a pull request

---

Built with ❤️ using Symfony 7 and Domain-Driven Design
