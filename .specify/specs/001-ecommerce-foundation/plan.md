# Implementation Plan: Symfony 7 E-commerce with Docker, DDD & AI Chatbot

## Overview

Build a complete e-commerce application from scratch using Symfony 7, following Domain-Driven Design principles with strict layer separation. The system will include user roles (admin/seller/customer), product management, shopping cart, checkout, and an AI-powered chatbot that uses internal tools to answer questions. The entire stack runs in Docker (PHP 8.3, MySQL 8.0, Nginx) with comprehensive unit and integration tests.

## Technical Stack

- **Backend**: Symfony 7, PHP 8.3
- **Database**: MySQL 8.0
- **Web Server**: Nginx
- **Containerization**: Docker, Docker Compose
- **AI**: symfony/ai package
- **ORM**: Doctrine
- **Testing**: PHPUnit
- **Frontend**: Twig, Vanilla JavaScript, CSS

## Architecture

### Domain-Driven Design Layers

1. **Domain Layer** - Pure business logic, no infrastructure dependencies
   - Entities: User, Product, Cart, Order
   - Value Objects: Email, Money
   - Repository Interfaces

2. **Application Layer** - Use cases and orchestration
   - Use Cases: CreateUser, AddProductToCart, Checkout, SearchProduct, GenerateStats
   - DTOs for data transfer

3. **Infrastructure Layer** - Technical implementations
   - Doctrine repositories
   - Symfony controllers
   - Chatbot agents and tools
   - Database configuration

## Implementation Steps

### Phase 0: Foundation & Docker Setup

1. **Docker Environment**
   - Create docker-compose.yml with services: php-fpm (PHP 8.3), mysql (8.0), nginx
   - Configure named volumes: mysql_data, symfony_cache
   - Mount source code at /var/www/html

2. **Dockerfile for PHP**
   - Base image: php:8.3-fpm
   - Install extensions: pdo_mysql, intl, opcache, zip
   - Install Composer
   - Set working directory

3. **Nginx Configuration**
   - Create docker/nginx/default.conf
   - Configure php-fpm upstream
   - Set document root to public/
   - Handle index.php front controller

4. **Database Initialization**
   - Create docker/mysql/init.sql
   - Create myshop database with UTF8MB4
   - Create bin/docker-init.sh for migrations and fixtures

5. **Symfony Installation**
   - Initialize Symfony 7 skeleton
   - Install core bundles: framework, twig, security, validator, form, asset
   - Install doctrine bundles: doctrine-bundle, orm
   - Install dev dependencies: maker-bundle, phpunit
   - Install symfony/ai for chatbot

6. **Environment Configuration**
   - Create .env with DATABASE_URL, APP_ENV, APP_SECRET, AI_API_KEY
   - Create .env.example template
   - Create .gitignore with Symfony patterns

7. **DDD Directory Structure**
   - src/Domain/Entity, Domain/ValueObject, Domain/Repository
   - src/Application/UseCase, Application/DTO
   - src/Infrastructure/Repository, Infrastructure/Controller, Infrastructure/Chatbot

### Phase 1: Domain Layer

8. **Value Objects**
   - Email: regex validation, immutability
   - Money: amount in cents, currency, formatting

9. **User Entity**
   - Properties: id (UUID), name, email (Email VO), passwordHash, role enum, createdAt
   - Doctrine attributes for ORM mapping

10. **Product Entity**
    - Properties: id (UUID), name, description, price (Money VO), stock, category, timestamps
    - Index on category

11. **Cart & CartItem Entities**
    - Cart: items collection, calculateTotal(), user reference
    - CartItem: product, quantity, priceSnapshot

12. **Order Entity**
    - Properties: orderNumber, items, total, status enum, user, timestamps
    - Status: PENDING, CONFIRMED, SHIPPED, DELIVERED, CANCELLED

13. **Repository Interfaces**
    - UserRepositoryInterface, ProductRepositoryInterface
    - CartRepositoryInterface, OrderRepositoryInterface
    - Method signatures: findById, save, delete, custom queries

### Phase 2: Application Layer

14. **CreateUser Use Case**
    - validate email uniqueness
    - Hash password with Symfony PasswordHasher
    - Save via repository interface

15. **AddProductToCart Use Case**
    - Check product stock availability
    - Add/update cart item
    - Recalculate cart total

16. **Checkout Use Case**
    - Validate cart not empty
    - Create Order from Cart items
    - Decrement product stock
    - Clear cart

17. **SearchProduct Use Case**
    - Filter by: query, category, minPrice, maxPrice
    - Return product list

18. **GenerateStats Use Case**
    - Return: totalSales, productCount, userCount, topProducts
    - Enforce role-based access (admin/seller only)

19. **DTOs**
    - UserDTO, ProductDTO, CartDTO, OrderDTO, StatsDTO
    - Decouple from entities

### Phase 3: Infrastructure Layer

20. **Doctrine Configuration**
    - Configure doctrine.yaml with MySQL DSN
    - Entity paths for Domain/Entity
    - UUID type support
    - Auto mapping

21. **Doctrine Entity Mappings**
    - Use ORM attributes in Domain entities
    - Define relations: ManyToOne, OneToMany
    - Add indexes on email, category

22. **Repository Implementations**
    - DoctrineUserRepository, DoctrineProductRepository
    - DoctrineCartRepository, DoctrineOrderRepository
    - Extend ServiceEntityRepository
    - Implement domain interfaces

23. **Database Migrations**
    - Generate migrations for all entities
    - Create tables with foreign keys and indexes

24. **UserController - API Endpoints**
    - POST /api/users (register)
    - POST /api/login (authenticate)
    - POST /api/logout
    - GET /api/users/me (profile)

25. **ProductController - CRUD Endpoints**
    - GET /api/products (list with pagination & filters)
    - GET /api/products/{id} (show)
    - POST /api/products (create - admin/seller)
    - PUT /api/products/{id} (update - admin/seller)
    - DELETE /api/products/{id} (delete - admin)

26. **CartController - Cart Operations**
    - GET /api/cart (view cart)
    - POST /api/cart/items (add to cart)
    - PUT /api/cart/items/{productId} (update quantity)
    - DELETE /api/cart/items/{productId} (remove)
    - DELETE /api/cart (clear cart)

27. **OrderController - Order Management**
    - POST /api/orders (checkout)
    - GET /api/orders (order history)
    - GET /api/orders/{orderNumber} (order details)
    - PUT /api/orders/{orderNumber}/status (update - admin/seller)

28. **Symfony Security Configuration**
    - Password hashing: bcrypt
    - Session-based authentication
    - Role hierarchy: ADMIN > SELLER > CUSTOMER
    - Access control rules for /api routes

### Phase 4: AI Chatbot

29. **Chatbot Configuration Files**
    - config/chatbot/system-prompt.yaml - personality, role detection, tool selection
    - config/chatbot/tools-config.yaml - tool descriptions, parameters, usage

30. **ChatbotAgent**
    - Initialize LLM with system prompt
    - Register all tools
    - Handle user messages with context

31. **StatsTool**
    - Calls GenerateStats use case
    - Role-based access control
    - Returns formatted statistics

32. **SearchProductTool**
    - Parameters: query, category (optional)
    - Calls SearchProduct use case
    - Returns product list with prices and stock

33. **StockTool**
    - Parameter: productId
    - Check stock level
    - Generate low-stock alert if < 10 units

34. **OrderTool**
    - Actions: add_to_cart, view_cart, get_orders
    - Delegates to appropriate use cases
    - Returns operation result

35. **Tool Registration**
    - Configure tagged services in services.yaml
    - Define tool schemas for LLM

36. **SessionManager**
    - Store conversation history in Symfony session
    - Methods: addMessage, getHistory, clear

37. **ChatbotController**
    - POST /api/chat endpoint
    - Receive {message}
    - Invoke agent with context
    - Return {response, role}

38. **Symfony AI Configuration**
    - Configure symfony_ai.yaml
    - Provider: openai/anthropic
    - Model: gpt-4 or claude-3
    - Temperature and max_tokens

### Phase 5: Frontend

39. **Base Layout**
    - templates/base.html.twig
    - Navigation, user info, cart badge, chatbot button
    - Asset links

40. **Homepage**
    - templates/home.html.twig
    - Featured products grid
    - Welcome message

41. **Product List**
    - templates/product/list.html.twig
    - Product cards, filters, pagination

42. **Product Detail**
    - templates/product/show.html.twig
    - Details, price, stock, "Add to Cart" button

43. **Cart View**
    - templates/cart/view.html.twig
    - Items table, quantity controls, total, checkout button

44. **Checkout**
    - templates/checkout/index.html.twig
    - Order summary, delivery form, "Place Order" button

45. **User Forms**
    - templates/user/register.html.twig
    - templates/user/login.html.twig

46. **Chatbot Widget**
    - templates/chatbot/widget.html.twig
    - Messages container, input, send button

47. **Cart JavaScript**
    - public/js/cart.js
    - Fetch API for cart operations
    - Update UI without reload

48. **Chatbot JavaScript**
    - public/js/chatbot.js
    - Send messages, display responses
    - Maintain chat history UI

49. **Styles**
    - public/css/style.css
    - Responsive layout
    - Chatbot widget positioning

### Phase 6: Testing

50. **PHPUnit Configuration**
    - phpunit.xml.dist
    - Test suites: unit, integration
    - Test database config

51. **Domain Value Object Tests**
    - tests/Unit/Domain/ValueObject/EmailTest.php
    - tests/Unit/Domain/ValueObject/MoneyTest.php

52. **Domain Entity Tests**
    - tests/Unit/Domain/Entity/CartTest.php
    - tests/Unit/Domain/Entity/OrderTest.php

53. **Application Use Case Tests**
    - CreateUserTest, AddProductToCartTest
    - CheckoutTest, SearchProductTest, GenerateStatsTest
    - Mock repositories

54. **Repository Integration Tests**
    - tests/Integration/Infrastructure/Repository/ProductRepositoryTest.php
    - Test CRUD operations, search queries
    - Use real test database

55. **Controller Integration Tests**
    - tests/Integration/Infrastructure/Controller/ProductControllerTest.php
    - Test API endpoints with authentication
    - Validate JSON responses

56. **Chatbot Tool Tests**
    - tests/Integration/Infrastructure/Chatbot/ToolsTest.php
    - Test tool invocations
    - Verify role-based access

### Phase 7: Data & Finalization

57. **User Fixtures**
    - src/Infrastructure/DataFixtures/UserFixtures.php
    - Admin, seller, 3 customers with credentials

58. **Product Fixtures**
    - src/Infrastructure/DataFixtures/ProductFixtures.php
    - 20 products, 4 categories
    - Varied prices and stock

59. **Cart Fixtures**
    - src/Infrastructure/DataFixtures/CartFixtures.php
    - Pre-populated carts for customers

60. **Order Fixtures**
    - src/Infrastructure/DataFixtures/OrderFixtures.php
    - 5 sample orders with various statuses

61. **Fixtures Bundle**
    - Install doctrine/doctrine-fixtures-bundle
    - Configure in bundles.php

62. **Database Init Script**
    - Update bin/docker-init.sh
    - Run migrations and fixtures

63. **Documentation**
    - README.md with setup, architecture, API docs
    - Default user credentials

64. **Makefile**
    - Shortcuts: up, down, install, migrate, fixtures, test, logs, bash

65. **Health Check**
    - src/Infrastructure/Controller/HealthController.php
    - GET /health - DB status, Symfony version

## Verification Checklist

- [ ] All Docker containers start successfully
- [ ] Database initialized with schema and fixtures
- [ ] Homepage accessible at http://localhost
- [ ] User authentication works (login/logout)
- [ ] Product CRUD API endpoints functional
- [ ] Cart operations work without page reload
- [ ] Checkout creates orders and updates stock
- [ ] Chatbot responds to queries and invokes correct tools
- [ ] Role-based access control enforced
- [ ] All tests pass (unit + integration)
- [ ] Migrations applied successfully
- [ ] Fixtures loaded correctly

## Technical Decisions

- **MySQL 8.0**: Common in Symfony ecosystem, simpler setup
- **PHP 8.3**: Latest stable, best performance
- **Session-based auth**: Traditional web app, simpler with Twig
- **Vanilla JavaScript**: No build step, easier maintenance
- **Symfony/AI tool pattern**: Extensible, testable chatbot
- **Named volumes**: Better performance on Windows/WSL
- **YAML prompts**: Editable without code changes
- **Doctrine fixtures**: Referential integrity, reproducible data
- **Repository interfaces in Domain**: SOLID dependency inversion
