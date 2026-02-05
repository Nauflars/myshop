# Project MyShop: Symfony + Docker + DDD + AI Chatbot

## Overview
This is an e-commerce application with the following features:
- Users with roles: admin, seller, customer
- Products CRUD
- Shopping cart and checkout
- AI-powered chatbot using symfony/AI with agents and tools

The project must:
- Use Symfony 7 inside Docker
- Follow DDD architecture: Domain, Application, Infrastructure
- Apply SOLID principles
- Include unit and integration tests for all layers
- Frontend with Twig templates and JavaScript for interactivity
- Chatbot able to use internal tools based on user questions

---

## Chatbot Tools
- **StatsTool**: provides sales, product, and user statistics
- **SearchProductTool**: searches products by name or category
- **StockTool**: checks stock and alerts
- **OrderTool**: manages shopping cart and orders

### Chatbot Functionality
- Detect user role (admin, seller, customer)
- Determine which tool(s) to use based on the question
- Execute the tool and generate a natural language response
- Maintain session context for follow-up questions
- Provide recommendations for users based on previous purchases

---

## Domain Layer
Entities and Value Objects:
- `User` (id, name, email, password hash, role)
- `Product` (id, name, description, price, stock, category)
- `Cart` (items, total, user)
- `Order` (products, total, status, user)
- Value Objects: `Email`, `Money`

Domain logic must be **pure**, without dependencies on infrastructure.

---

## Application Layer
- Use Cases / Services:
  - `CreateUser`
  - `AddProductToCart`
  - `Checkout`
  - `SearchProduct`
  - `GenerateStats`
- Handles orchestration of domain entities and business rules
- Depends only on interfaces for repositories and tools

---

## Infrastructure Layer
- Concrete repositories using Doctrine ORM
- Database integration (MySQL / PostgreSQL)
- Chatbot agent and tools implementation
- Symfony services configuration
- Docker configuration

---

## Tests
- Unit tests for Domain and Application layers
- Integration tests for Infrastructure, API endpoints, and chatbot
- Automated test execution with PHPUnit

---

## Frontend
- Twig templates for UI
- JavaScript for cart interactivity and chatbot interface
- AJAX / Fetch for frontend-backend communication

---

## Docker Configuration
- Containers:
  - PHP-FPM for Symfony
  - MySQL for database
  - Nginx for web server
- Volumes for persistence
- Ready to run with `docker-compose up`

---

## Additional Notes
- Follow DDD layering strictly
- Apply SOLID principles across all classes
- Generate all code, tests, and configuration using Spec Kit
- The AI will handle Symfony installation, Docker setup, entity creation, frontend, chatbot, and tools
- Iterative development: Spec Kit can refine and add features by updating the project.md and rerunning `/plan`, `/tasks`, `/implement`
