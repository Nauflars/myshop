# Tasks: Symfony 7 E-commerce with Docker, DDD & AI Chatbot

## Phase 0: Setup

- [X] T001 [P] Create docker-compose.yml with php-fpm, mysql, nginx services and volumes | docker-compose.yml
- [X] T002 [P] Create Dockerfile for PHP 8.3-fpm with extensions | Dockerfile
- [X] T003 [P] Create nginx configuration | docker/nginx/default.conf
- [X] T004 [P] Create MySQL init script | docker/mysql/init.sql
- [X] T005 [P] Create Docker initialization script | bin/docker-init.sh
- [X] T006 Create .gitignore with Symfony patterns | .gitignore
- [X] T007 Create .dockerignore for Docker builds | .dockerignore
- [X] T008 Create composer.json with Symfony 7 and dependencies | composer.json
- [X] T009 Create .env and .env.example with configuration | .env, .env.example
- [X] T010 Create Symfony directory structure (bin/, config/, public/, src/, templates/, var/) | [directories]

## Phase 1: Domain Layer

- [X] T011 [P] Create Email value object with validation | src/Domain/ValueObject/Email.php
- [X] T012 [P] Create Money value object | src/Domain/ValueObject/Money.php
- [X] T013 Create User entity with Doctrine mapping | src/Domain/Entity/User.php
- [X] T014 Create Product entity with Doctrine mapping | src/Domain/Entity/Product.php
- [X] T015 Create Cart entity with Doctrine mapping | src/Domain/Entity/Cart.php
- [X] T016 Create CartItem entity | src/Domain/Entity/CartItem.php
- [X] T017 Create Order entity with Doctrine mapping | src/Domain/Entity/Order.php
- [X] T018 Create OrderItem entity | src/Domain/Entity/OrderItem.php
- [X] T019 [P] Create UserRepositoryInterface | src/Domain/Repository/UserRepositoryInterface.php
- [X] T020 [P] Create ProductRepositoryInterface | src/Domain/Repository/ProductRepositoryInterface.php
- [X] T021 [P] Create CartRepositoryInterface | src/Domain/Repository/CartRepositoryInterface.php
- [X] T022 [P] Create OrderRepositoryInterface | src/Domain/Repository/OrderRepositoryInterface.php

## Phase 2: Application Layer

- [X] T023 [P] Create UserDTO | src/Application/DTO/UserDTO.php
- [X] T024 [P] Create ProductDTO | src/Application/DTO/ProductDTO.php
- [X] T025 [P] Create CartDTO | src/Application/DTO/CartDTO.php
- [X] T026 [P] Create OrderDTO | src/Application/DTO/OrderDTO.php
- [X] T027 [P] Create StatsDTO | src/Application/DTO/StatsDTO.php
- [X] T028 Create CreateUser use case | src/Application/UseCase/CreateUser.php
- [X] T029 Create AddProductToCart use case | src/Application/UseCase/AddProductToCart.php
- [X] T030 Create Checkout use case | src/Application/UseCase/Checkout.php
- [X] T031 Create SearchProduct use case | src/Application/UseCase/SearchProduct.php
- [X] T032 Create GenerateStats use case | src/Application/UseCase/GenerateStats.php

## Phase 3: Infrastructure - Configuration

- [X] T033 Create Doctrine configuration | config/packages/doctrine.yaml
- [X] T034 Create Symfony routes configuration | config/routes.yaml
- [X] T035 Create Symfony security configuration | config/packages/security.yaml
- [X] T036 Create services configuration | config/services.yaml
- [X] T037 Create Symfony framework configuration | config/packages/framework.yaml
- [X] T038 Create Twig configuration | config/packages/twig.yaml
- [ ] T039 Create symfony/ai configuration | config/packages/symfony_ai.yaml

## Phase 4: Infrastructure - Repositories

- [X] T040 [P] Create DoctrineUserRepository | src/Infrastructure/Repository/DoctrineUserRepository.php
- [X] T041 [P] Create DoctrineProductRepository | src/Infrastructure/Repository/DoctrineProductRepository.php
- [X] T042 [P] Create DoctrineCartRepository | src/Infrastructure/Repository/DoctrineCartRepository.php
- [X] T043 [P] Create DoctrineOrderRepository | src/Infrastructure/Repository/DoctrineOrderRepository.php

## Phase 5: Infrastructure - Controllers

- [X] T044 Create UserController with auth endpoints | src/Infrastructure/Controller/UserController.php
- [X] T045 Create ProductController with CRUD endpoints | src/Infrastructure/Controller/ProductController.php
- [X] T046 Create CartController with cart operations | src/Infrastructure/Controller/CartController.php
- [X] T047 Create OrderController with order management | src/Infrastructure/Controller/OrderController.php
- [X] T048 Create HealthController | src/Infrastructure/Controller/HealthController.php

## Phase 6: Infrastructure - Chatbot

- [ ] T049 Create chatbot system prompt configuration | config/chatbot/system-prompt.yaml
- [ ] T050 Create chatbot tools configuration | config/chatbot/tools-config.yaml
- [ ] T051 Create ChatbotAgent | src/Infrastructure/Chatbot/Agent/ChatbotAgent.php
- [ ] T052 Create StatsTool | src/Infrastructure/Chatbot/Tool/StatsTool.php
- [ ] T053 Create SearchProductTool | src/Infrastructure/Chatbot/Tool/SearchProductTool.php
- [ ] T054 Create StockTool | src/Infrastructure/Chatbot/Tool/StockTool.php
- [ ] T055 Create OrderTool | src/Infrastructure/Chatbot/Tool/OrderTool.php
- [ ] T056 Create SessionManager | src/Infrastructure/Chatbot/SessionManager.php
- [X] T057 Create ChatbotController | src/Infrastructure/Controller/ChatbotController.php

## Phase 7: Frontend - Templates

- [X] T058 Create base layout template | templates/base.html.twig
- [X] T059 Create homepage template | templates/home.html.twig
- [X] T060 Create product list template | templates/product/list.html.twig
- [X] T061 Create product detail template | templates/product/show.html.twig
- [X] T062 Create cart view template | templates/cart/view.html.twig
- [X] T063 Create checkout template | templates/checkout/index.html.twig
- [X] T064 Create register template | templates/user/register.html.twig
- [X] T065 Create login template | templates/user/login.html.twig
- [X] T066 Create chatbot widget template | templates/chatbot/widget.html.twig

## Phase 8: Frontend - Assets

- [X] T067 Create cart JavaScript | public/js/cart.js
- [X] T068 Create chatbot JavaScript | public/js/chatbot.js
- [X] T069 Create main stylesheet | public/css/style.css

## Phase 9: Testing - Unit Tests

- [X] T070 [P] Configure PHPUnit | phpunit.xml.dist
- [ ] T071 [P] Create EmailTest | tests/Unit/Domain/ValueObject/EmailTest.php
- [ ] T072 [P] Create MoneyTest | tests/Unit/Domain/ValueObject/MoneyTest.php
- [ ] T073 [P] Create CartTest | tests/Unit/Domain/Entity/CartTest.php
- [ ] T074 [P] Create OrderTest | tests/Unit/Domain/Entity/OrderTest.php
- [ ] T075 Create CreateUserTest | tests/Unit/Application/UseCase/CreateUserTest.php
- [ ] T076 Create AddProductToCartTest | tests/Unit/Application/UseCase/AddProductToCartTest.php
- [ ] T077 Create CheckoutTest | tests/Unit/Application/UseCase/CheckoutTest.php
- [ ] T078 Create SearchProductTest | tests/Unit/Application/UseCase/SearchProductTest.php
- [ ] T079 Create GenerateStatsTest | tests/Unit/Application/UseCase/GenerateStatsTest.php

## Phase 10: Testing - Integration Tests

- [ ] T080 [P] Create ProductRepositoryTest | tests/Integration/Infrastructure/Repository/ProductRepositoryTest.php
- [ ] T081 [P] Create UserRepositoryTest | tests/Integration/Infrastructure/Repository/UserRepositoryTest.php
- [ ] T082 [P] Create CartRepositoryTest | tests/Integration/Infrastructure/Repository/CartRepositoryTest.php
- [ ] T083 [P] Create OrderRepositoryTest | tests/Integration/Infrastructure/Repository/OrderRepositoryTest.php
- [ ] T084 Create ProductControllerTest | tests/Integration/Infrastructure/Controller/ProductControllerTest.php
- [ ] T085 Create CartControllerTest | tests/Integration/Infrastructure/Controller/CartControllerTest.php
- [ ] T086 Create OrderControllerTest | tests/Integration/Infrastructure/Controller/OrderControllerTest.php
- [ ] T087 Create ChatbotToolsTest | tests/Integration/Infrastructure/Chatbot/ToolsTest.php

## Phase 11: Data & Finalization

- [X] T088 Install doctrine-fixtures-bundle | composer.json
- [X] T089 Create UserFixtures | src/Infrastructure/DataFixtures/UserFixtures.php
- [X] T090 Create ProductFixtures | src/Infrastructure/DataFixtures/ProductFixtures.php
- [X] T091 Create CartFixtures | src/Infrastructure/DataFixtures/CartFixtures.php
- [X] T092 Create OrderFixtures | src/Infrastructure/DataFixtures/OrderFixtures.php
- [X] T093 Create Symfony console bin | bin/console
- [X] T094 Create README.md with documentation | README.md
- [X] T095 Create Makefile with shortcuts | Makefile
- [X] T096 Create .env.test for testing | .env.test

## Execution Notes

- Tasks marked [P] can be executed in parallel
- Phases must be completed sequentially  
- Domain layer has no infrastructure dependencies
- Application layer depends only on Domain interfaces
- Infrastructure implements Domain contracts
- Tests require the corresponding implementation to be complete
- Fixtures require migrations to be run first
