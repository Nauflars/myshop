# Feature Specification: Admin Virtual Assistant

**Feature Branch**: `007-admin-virtual-assistant`  
**Created**: February 6, 2026  
**Status**: Draft  
**Input**: User description: "Admin Virtual Assistant - AI-powered assistant exclusively for administrators to manage products, view sales statistics, and perform administrative tasks using natural language"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Basic Admin Chat Interface (Priority: P1)

An administrator logs into the admin panel and interacts with a dedicated AI assistant that communicates exclusively in Spanish, maintaining a professional business-oriented tone distinct from the customer-facing chatbot.

**Why this priority**: Foundation for all admin assistant functionality. Without the basic interface and access control, no other features can function. Establishes critical security boundary between admin and customer contexts.

**Independent Test**: Admin user can access a dedicated chat interface from the admin panel, send messages in Spanish, receive Spanish responses, and verify that non-admin users cannot access this interface. Customer chatbot remains unaffected.

**Acceptance Scenarios**:

1. **Given** an authenticated admin user is on the admin panel, **When** they access the admin assistant interface, **Then** they see a distinct chat widget labeled appropriately for internal use
2. **Given** an authenticated customer user, **When** they attempt to access the admin assistant endpoint, **Then** they receive a 403 Forbidden response
3. **Given** an admin sends a message in Spanish, **When** the assistant responds, **Then** the response is in Spanish with professional business vocabulary
4. **Given** admin and customer users are active simultaneously, **When** they use their respective chatbots, **Then** each user's conversation remains completely isolated with no cross-context leakage

---

### User Story 2 - Product Management via Natural Language (Priority: P1)

An administrator uses natural language commands to create, update, and delete products without navigating through traditional admin forms. The assistant asks clarifying questions when information is missing and confirms actions before executing them.

**Why this priority**: Core value proposition of the admin assistant. Directly addresses the stated goal of reducing "manual navigation in the admin interface" and enabling natural language product management. Delivers immediate productivity gains.

**Independent Test**: Admin can create a complete product by conversing with the assistant ("Crea un producto llamado..."), update existing products ("Actualiza el precio de..."), and delete products ("Elimina el producto...") with confirmation prompts. Each action can be tested independently and delivers standalone value.

**Acceptance Scenarios**:

1. **Given** an admin types "Crea un producto llamado Laptop Gaming", **When** the assistant processes the request, **Then** it asks for missing required information (price, description, stock, category)
2. **Given** an admin provides all product details through conversation, **When** they confirm the creation, **Then** the product is persisted and appears in the product catalog
3. **Given** an admin says "Actualiza el precio de <product> a 299 euros", **When** the assistant identifies the product, **Then** it presents the change for confirmation before applying
4. **Given** an admin requests product deletion, **When** the assistant processes the request, **Then** it requires explicit confirmation ("¿Estás seguro?") before removing the product
5. **Given** product data has business constraints (e.g. negative price), **When** the admin attempts invalid data, **Then** the assistant explains the validation error in Spanish and asks for correction

---

### User Story 3 - Sales Analytics Queries (Priority: P2)

An administrator asks questions about sales performance using natural language and receives immediate insights without needing to navigate dashboards or run reports. Questions include overall sales, product-specific performance, top sellers, and customer statistics.

**Why this priority**: High-value analytics capability that distinguishes this from a simple CRUD interface. Enables data-driven decision making through conversation. Secondary to core product management (P1) but critical for stated goal of providing "insights into sales and product performance."

**Independent Test**: Admin can ask "¿Cómo van las ventas este mes?" and receive total revenue, order count, and average order value. Can ask "¿Cuál es el producto más vendido?" and see ranked results. Each analytics query works independently without requiring product management features.

**Acceptance Scenarios**:

1. **Given** historical sales data exists, **When** admin asks "¿Cómo van las ventas?", **Then** the assistant returns total revenue, order count, average order value, and time period
2. **Given** multiple products have sales, **When** admin asks "¿Cuál es el producto más vendido?", **Then** the assistant shows a ranked list with units sold and revenue per product
3. **Given** an admin asks "¿Cuánto ha vendido el producto <name>?", **When** the product is identified, **Then** the assistant returns units sold, revenue, and percentage of total sales
4. **Given** customer purchase data exists, **When** admin asks "¿Quiénes son nuestros mejores clientes?", **Then** the assistant shows top customers by order count and total spend
5. **Given** an admin asks a follow-up question about a previously mentioned product, **When** the assistant uses conversational context, **Then** it correctly interprets the reference without requiring full product name again

---

### User Story 4 - Conversational Context and Multi-Turn Analytics (Priority: P3)

The assistant maintains conversational context across multiple questions, allowing administrators to naturally drill down into data, compare products, and switch topics fluidly within the same session.

**Why this priority**: Enhances user experience significantly but not essential for core functionality. Builds upon P2 analytics by making conversations more natural and efficient. Can be delivered after basic analytics are proven.

**Independent Test**: Admin asks "¿Cómo va el producto X?" then follows with "¿Y comparado con Y?" without repeating context. Assistant correctly maintains reference to both products across multiple turns.

**Acceptance Scenarios**:

1. **Given** an admin asks about "Producto X", **When** they follow with "¿Cuánto stock queda?", **Then** the assistant refers to Producto X without requiring clarification
2. **Given** discussion about sales performance, **When** admin says "Compáralo con el mes pasado", **Then** the assistant understands the implicit reference and provides comparative data
3. **Given** an admin shifts from product questions to customer questions, **When** they return to the original product topic, **Then** the assistant retrieves the context appropriately
4. **Given** a conversation spans multiple tool invocations, **When** the admin references "el producto mencionado antes", **Then** the assistant resolves the reference correctly

---

### Edge Cases

- What happens when an admin requests deletion of a product that has existing orders?  
  System should prevent deletion and explain the constraint ("No se puede eliminar porque tiene pedidos asociados").

- How does the system handle ambiguous product names during updates?  
  Assistant should present matching options for admin to choose from: "Encontré 3 productos: 1) Laptop HP, 2) Laptop Dell, 3) Laptop Gaming. ¿Cuál deseas actualizar?"

- What happens when sales data queries return no results?  
  Assistant provides helpful context: "No hay ventas registradas en este período" rather than technical errors.

- How does the assistant handle requests outside its scope (e.g., "process a refund", "send email to customer")?  
  Assistant politely declines: "No tengo capacidad para procesar reembolsos. Te recomiendo usar el panel de gestión de pedidos."

- What happens if an admin attempts destructive actions in rapid succession?  
  Each action requires individual confirmation; no batch confirmation shortcuts to prevent accidental data loss.

- How does the system handle concurrent admin sessions modifying the same product?  
  Standard database transaction handling applies; last write wins, with assistant optionally warning "Este producto fue modificado recientemente por otro administrador."

## Requirements *(mandatory)*

### Functional Requirements

#### Access Control
- **FR-001**: System MUST restrict access to the Admin Virtual Assistant to users with ADMIN role only
- **FR-002**: System MUST return 403 Forbidden to any non-admin user attempting to access admin assistant endpoints
- **FR-003**: System MUST maintain complete isolation between admin assistant and customer chatbot (separate contexts, no shared conversation history)

#### Assistant Identity & Behavior
- **FR-004**: Admin Assistant MUST communicate exclusively in Spanish
- **FR-005**: Admin Assistant MUST use professional, business-oriented vocabulary appropriate for internal operations
- **FR-006**: Admin Assistant MUST NOT expose infrastructure details (database names, server architecture, etc.)
- **FR-007**: Admin Assistant MUST proactively ask for missing information when required fields are not provided

#### Product Management Tools
- **FR-008**: System MUST provide CreateProduct capability accepting: name, description, price, stock, category, visibility status
- **FR-009**: System MUST validate product data (positive prices, non-negative stock, required fields) before persistence
- **FR-010**: System MUST present product creation data for admin confirmation before finalizing
- **FR-011**: System MUST provide UpdateProduct capability to modify: price, stock, description, category, visibility
- **FR-012**: System MUST resolve products by name when updating, presenting options if multiple matches exist
- **FR-013**: System MUST require explicit confirmation before applying product updates
- **FR-014**: System MUST provide DeleteProduct capability with mandatory confirmation step
- **FR-015**: System MUST prevent deletion of products with associated orders, providing clear explanation

#### Sales Analytics Tools
- **FR-016**: System MUST provide GetSalesOverview returning: total revenue, order count, average order value, time period
- **FR-017**: System MUST provide GetProductSalesStats for specific products returning: units sold, revenue, percentage of total sales
- **FR-018**: System MUST provide GetTopSellingProducts returning ranked list with: product name, units sold, revenue
- **FR-019**: System MUST provide GetUserPurchaseStats returning: order count per user, total spend per user, top customers
- **FR-020**: Analytics queries MUST handle empty result sets gracefully with user-friendly Spanish messages

#### Conversational Capabilities
- **FR-021**: System MUST maintain short-term conversational context to resolve implicit references (e.g., "el producto" referring to previously mentioned product)
- **FR-022**: Assistant MUST handle ambiguous product references by presenting disambiguation options
- **FR-023**: Assistant MUST support follow-up questions building on previous context
- **FR-024**: Assistant MUST refuse out-of-scope requests with polite explanations and alternative suggestions

#### Security & Validation
- **FR-025**: Every tool invocation MUST verify ADMIN role before execution
- **FR-026**: System MUST validate all inputs to prevent injection attacks and malformed data
- **FR-027**: Destructive actions (delete, updates affecting availability) MUST require explicit confirmation
- **FR-028**: System MUST log all admin assistant actions for audit trail

#### Frontend Integration
- **FR-029**: Admin Virtual Assistant interface MUST be accessible from admin panel navigation
- **FR-030**: Admin Assistant interface MUST be visually distinct from customer chatbot
- **FR-031**: Chat history MUST be visible only to the current admin user session
- **FR-032**: System MUST render admin assistant UI using Twig templates consistent with existing admin interface design

### Key Entities *(include if feature involves data)*

- **AdminAssistantConversation**: Represents a chat session between an admin and the assistant. Attributes include admin user reference, session start time, message history, and conversational context state. Each conversation is isolated per admin session.

- **AdminAssistantMessage**: Individual message within a conversation. Attributes include sender (admin or assistant), message text, timestamp, and associated tool invocations if any.

- **AdminAssistantAction**: Audit log of actions performed via the assistant. Attributes include admin user, action type (create_product, update_product, delete_product, query_sales), timestamp, affected entities (product IDs), and action parameters.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can create a complete product through natural language conversation in under 2 minutes (compared to 3-5 minutes via traditional forms)
- **SC-002**: Administrators can retrieve sales insights (overview, top products, customer stats) in under 30 seconds without leaving the chat interface
- **SC-003**: Product modifications via assistant complete successfully 95% of the time on first attempt (with remaining 5% due to user clarification needs, not system errors)
- **SC-004**: Zero security incidents where non-admin users access admin assistant or admin context leaks to customer chatbot (verified through penetration testing)
- **SC-005**: Administrators report 80% satisfaction with assistant's ability to understand natural language commands (measured via post-interaction survey)
- **SC-006**: Average time to complete routine product management tasks reduces by 40% compared to traditional admin interface navigation
- **SC-007**: Admin assistant handles conversational context correctly in 90% of multi-turn conversations (measured by successful resolution of implicit references)
- **SC-008**: All destructive operations (deletions, significant updates) receive explicit confirmation before execution, with zero accidental data loss incidents
