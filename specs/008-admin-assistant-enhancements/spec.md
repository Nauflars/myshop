# Feature Specification: Admin AI Assistant Enhancements

**Feature Branch**: `008-admin-assistant-enhancements`  
**Created**: February 7, 2026  
**Status**: Draft  
**Input**: User description: "Admin AI Assistant Enhancements - Improve visibility and usability with floating UI, expand capabilities with inventory, pricing, sales analytics, order management, customer insights tools"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Floating Admin Assistant UI (Priority: P1)

An administrator accesses any admin panel page and sees a floating assistant button (similar to the customer chatbot) that opens a contextual AI assistant panel. The floating UI remains accessible across all admin pages without navigation loss, matching the UX patterns users already know from the customer interface while being visually distinct as an admin tool.

**Why this priority**: Foundation for all enhanced capabilities. The floating UI is the entry point that makes all other assistant features accessible and usable. Without this, administrators must navigate to a dedicated page, breaking workflow. This aligns the admin experience with the familiar customer chatbot pattern while maintaining role separation.

**Independent Test**: Admin user logs in, sees floating ball on any admin page (products, orders, users), clicks to open chat panel, sends message, closes panel, navigates to different admin page, opens panel again and sees conversation history persisted. Non-admin users should never see this floating button.

**Acceptance Scenarios**:

1. **Given** an authenticated admin user is on any admin panel page, **When** they view the page, **Then** a floating action button (FAB) appears in a consistent position (e.g., bottom-right corner)
2. **Given** the floating assistant button is visible, **When** admin clicks it, **Then** a chat panel opens displaying "Admin Assistant" header and conversation history
3. **Given** the assistant panel is open, **When** admin clicks outside the panel or presses close button, **Then** the panel closes without losing conversation state
4. **Given** the assistant panel is open on one admin page, **When** admin navigates to another admin page, **Then** the panel closes but conversation context is preserved for next open
5. **Given** an authenticated customer user browsing the store, **When** they view any page, **Then** the admin floating assistant button does NOT appear (only their customer chatbot is visible)
6. **Given** the admin assistant panel is open, **When** viewed visually, **Then** it has distinct styling from customer chatbot (different color scheme, "Admin" label) to indicate internal tool

---

### User Story 2 - Inventory Management via Natural Language (Priority: P1)

An administrator asks the assistant to check stock levels, update inventory, or identify low-stock products without opening inventory management screens. The assistant confirms changes before applying and provides immediate feedback.

**Why this priority**: Core operational task that administrators perform daily. Stock management is time-sensitive and benefits most from rapid conversational access. Delivers immediate productivity gains and represents clear ROI for assistant enhancement.

**Independent Test**: Admin opens assistant and says "¿Cuántos productos tienen stock bajo?" and receives list of products below threshold. Then "Aumenta el stock de Laptop HP en 50 unidades" and assistant confirms before applying change. Can be tested independently of other features.

**Acceptance Scenarios**:

1. **Given** admin opens assistant, **When** they ask "¿Qué productos tienen stock bajo?", **Then** assistant lists products below configured threshold with current stock levels
2. **Given** admin identifies low-stock product, **When** they say "Aumenta el stock de [producto] en [cantidad]", **Then** assistant confirms current stock, proposed new stock, and asks for confirmation
3. **Given** admin confirms stock increase, **When** confirmation is given, **Then** stock is updated and assistant shows success message with new stock level
4. **Given** admin asks "¿Cuál es el stock actual de [producto]?", **When** assistant processes request, **Then** it returns current stock number without requiring screen navigation
5. **Given** admin requests stock update for non-existent product, **When** assistant processes request, **Then** it responds "No encontré el producto '[nombre]'. ¿Quieres ver productos similares?"

---

### User Story 3 - Sales Analytics via Conversation (Priority: P2)

An administrator asks business intelligence questions like "¿Cómo van las ventas esta semana?" or "¿Cuáles son los productos más vendidos?" and receives immediate insights without opening analytics dashboards. The assistant can compare products, identify trends, and provide context for decision-making.

**Why this priority**: High-value feature that transforms assistant from operational tool to strategic advisor. Enables data-driven decisions without leaving conversation flow. Secondary to core operations (P1) but critical for business intelligence needs.

**Independent Test**: Admin asks "¿Cómo van las ventas hoy?" and receives total revenue, order count, average order value. Then asks "¿Cuáles son los 5 productos más vendidos?" and gets ranked list. Can be tested with historical sales data without dependency on inventory features.

**Acceptance Scenarios**:

1. **Given** admin opens assistant, **When** they ask "¿Cómo van las ventas hoy?", **Then** assistant provides sales summary: total revenue, number of orders, average order value
2. **Given** admin wants product insights, **When** they ask "¿Cuáles son los productos más vendidos?", **Then** assistant lists top products with units sold and revenue generated
3. **Given** admin asks "¿Cuál es el rendimiento de [producto]?", **When** assistant processes request, **Then** it shows units sold, revenue, and comparison to store average
4. **Given** admin asks "¿Cuáles productos no se venden?", **When** assistant processes request, **Then** it identifies bottom performers with sales data
5. **Given** no sales data exists for requested period, **When** admin asks sales question, **Then** assistant responds "No hay ventas registradas en este período" instead of technical error
6. **Given** admin asks follow-up "¿Y la semana pasada?", **When** assistant processes request, **Then** it understands context refers to same metric for different time period

---

### User Story 4 - Order Management via Assistant (Priority: P2)

An administrator checks recent orders, views order details, and updates order status through natural language without navigating order management interface. The assistant supports common workflows like marking orders as shipped or cancelled with confirmation.

**Why this priority**: Frequent administrative task that benefits from conversational access. Complements inventory management (P1) but less time-critical. Order fulfillment workflows can utilize this for faster status updates.

**Independent Test**: Admin asks "¿Cuáles son los últimos 10 pedidos?" and sees order list. Then "Muestra detalles del pedido ORD-20260207-001" and gets full order info. Then "Marca el pedido ORD-20260207-001 como enviado" with confirmation flow.

**Acceptance Scenarios**:

1. **Given** admin opens assistant, **When** they ask "¿Cuáles son los últimos pedidos?", **Then** assistant lists recent orders with order ID, customer, total, and status
2. **Given** admin wants order details, **When** they say "Muestra detalles del pedido [ORDER_ID]", **Then** assistant displays order items, customer info, shipping address, payment status
3. **Given** admin needs to update order, **When** they say "Actualiza el estado del pedido [ORDER_ID] a enviado", **Then** assistant confirms current status and asks for confirmation before changing
4. **Given** admin confirms status change, **When** confirmation is given, **Then** order status is updated and customer receives notification (if configured)
5. **Given** admin references invalid order ID, **When** assistant processes request, **Then** it responds "No encontré el pedido con ID [ORDER_ID]"

---

### User Story 5 - Customer Insights and Patterns (Priority: P3)

An administrator asks about customer behavior patterns, identifies top customers by value, and understands repeat purchase rates through conversational queries. The assistant provides strategic insights about the customer base.

**Why this priority**: Strategic intelligence feature with high value but less urgent than operational tasks. Supports business planning and customer relationship decisions. Can be implemented after core operations are enhanced.

**Independent Test**: Admin asks "¿Cuántos clientes tenemos?" and gets total count with breakdown (guests vs registered, active vs inactive). Then "¿Quiénes son nuestros mejores clientes?" and receives ranked list by lifetime value.

**Acceptance Scenarios**:

1. **Given** admin opens assistant, **When** they ask "¿Cuántos clientes tenemos?", **Then** assistant provides total customer count, breakdown by type (guest/registered), and active users
2. **Given** admin wants customer rankings, **When** they ask "¿Quiénes son los mejores clientes?", **Then** assistant lists top customers by lifetime value with purchase count and total spend
3. **Given** admin asks "¿Cuál es la tasa de clientes recurrentes?", **When** assistant processes request, **Then** it calculates and provides percentage of customers with multiple orders
4. **Given** admin asks about specific customer, **When** they provide customer email or name, **Then** assistant shows purchase history summary without exposing sensitive payment data

---

### User Story 6 - Unanswered Questions Review (Priority: P3)

An administrator reviews questions that the customer assistant couldn't answer, allowing them to identify knowledge gaps, improve assistant responses, and address customer pain points.

**Why this priority**: Quality improvement and customer experience enhancement. Valuable for assistant evolution but not urgent for daily operations. Leverages existing unanswered questions feature (spec-006).

**Independent Test**: Admin asks "¿Hay preguntas sin respuesta?" and gets list with date, question text, and status. Can filter by date range.

**Acceptance Scenarios**:

1. **Given** admin opens assistant, **When** they ask "¿Hay preguntas sin respuesta?", **Then** assistant lists recent unanswered questions with date and original question text
2. **Given** admin wants filtered view, **When** they say "Muestra preguntas sin responder de esta semana", **Then** assistant filters list by specified date range
3. **Given** no unanswered questions exist, **When** admin asks, **Then** assistant responds "No hay preguntas sin responder en este momento"

---

### Edge Cases

- **What happens when admin has both admin and customer roles concurrently?**  
  System must show admin floating assistant only on admin panel pages (URLs starting with /admin/). Customer pages show customer chatbot. Each maintains separate conversation contexts.

- **How does the system handle ambiguous product/order references?**  
  Assistant must present numbered disambiguation options: "Encontré 3 productos: 1) Laptop HP Pro, 2) Laptop HP Gaming, 3) Laptop HP Basic. ¿Cuál te refieres?"

- **What happens if OpenAI API is unavailable?**  
  Assistant shows user-friendly error: "El asistente no está disponible temporalmente. Intenta de nuevo en unos minutos." Admin can still use traditional admin interface.

- **How does the assistant handle concurrent admin sessions modifying same inventory?**  
  Standard optimistic locking applies. If assistant tries to update stock that changed since query, it shows current value and asks admin to confirm with new information.

- **What happens when sales data queries span very large datasets?**  
  Assistant must limit results (e.g., top 50) and offer drill-down: "Mostrando los 50 productos más vendidos. ¿Quieres filtrar por categoría?"

- **How does the system handle malicious input trying to expose system details?**  
  Assistant must sanitize inputs, never expose internal IDs, stack traces, or infrastructure details. Log suspicious queries for security review.

- **What happens when admin asks for functionality not yet implemented?**  
  Assistant clearly states limitations: "Aún no puedo [acción solicitada], pero puedo ayudarte con [lista de capacidades disponibles]."

## Requirements *(mandatory)*

### Functional Requirements

#### UI and Accessibility

- **FR-001**: System MUST display a floating action button (FAB) on all admin panel pages for users with ROLE_ADMIN
- **FR-002**: Floating assistant button MUST NOT appear on customer-facing pages or to non-admin users
- **FR-003**: Floating assistant button MUST be visually distinct from customer chatbot (different color scheme, admin indicator)
- **FR-004**: Clicking floating button MUST open a chat panel with "Admin Assistant" header
- **FR-005**: Chat panel MUST persist conversation context within the session
- **FR-006**: Chat panel MUST be dismissible without losing conversation state
- **FR-007**: Chat panel MUST remain accessible across admin page navigation
- **FR-008**: System MUST position floating button consistently (e.g., bottom-right corner) without obscuring critical admin UI elements

#### Inventory Management Tools

- **FR-009**: System MUST provide GetLowStockProducts tool returning products below configurable threshold
- **FR-010**: GetLowStockProducts MUST include product name, current stock, and threshold value
- **FR-011**: System MUST provide UpdateProductStock tool accepting product name and stock delta or absolute value
- **FR-012**: UpdateProductStock MUST confirm current stock, proposed change, and new stock before applying
- **FR-013**: UpdateProductStock MUST require explicit admin confirmation before updating database
- **FR-014**: System MUST validate stock updates (non-negative values, reasonable limits)

#### Pricing Tools

- **FR-015**: System MUST provide UpdateProductPrice tool for changing product prices
- **FR-016**: UpdateProductPrice MUST show current price and proposed price before applying change
- **FR-017**: UpdateProductPrice MUST require confirmation before persisting changes
- **FR-018**: System MUST provide GetPriceHistory tool showing historical price changes for a product
- **FR-019**: GetPriceHistory MUST include date, old price, new price, and admin user who made change

#### Sales Analytics Tools

- **FR-020**: System MUST provide GetSalesSummary tool for time period analysis
- **FR-021**: GetSalesSummary MUST return total revenue, order count, and average order value
- **FR-022**: System MUST provide GetProductPerformance tool comparing multiple products
- **FR-023**: GetProductPerformance MUST show units sold and revenue generated per product
- **FR-024**: System MUST provide GetTopAndBottomProducts tool identifying best and worst performers
- **FR-025**: GetTopAndBottomProducts MUST return ranked lists with sales metrics
- **FR-026**: All analytics tools MUST handle empty datasets with user-friendly messages ("No hay ventas registradas")

#### Order Management Tools

- **FR-027**: System MUST provide ListRecentOrders tool showing recent orders with summary info
- **FR-028**: ListRecentOrders MUST include order ID, customer identifier, total, and current status
- **FR-029**: System MUST provide GetOrderDetails tool retrieving full order information
- **FR-030**: GetOrderDetails MUST show items, customer info, shipping address, and payment status without exposing sensitive payment data
- **FR-031**: System MUST provide UpdateOrderStatus tool for changing order status
- **FR-032**: UpdateOrderStatus MUST support statuses: pending, processing, shipped, delivered, cancelled
- **FR-033**: UpdateOrderStatus MUST require explicit confirmation before applying changes
- **FR-034**: UpdateOrderStatus MUST validate status transitions (e.g., cannot ship a cancelled order)

#### Customer Insights Tools

- **FR-035**: System MUST provide GetCustomerOverview tool showing high-level customer metrics
- **FR-036**: GetCustomerOverview MUST return total customers, registered vs guest breakdown, and active user count
- **FR-037**: System MUST provide GetTopCustomers tool ranking customers by lifetime value
- **FR-038**: GetTopCustomers MUST show purchase count and total spend per customer
- **FR-039**: Customer tools MUST respect privacy by NOT exposing sensitive personal data (passwords, full payment details)

#### System Feedback Tools

- **FR-040**: System MUST provide ListUnansweredQuestions tool retrieving questions customer assistant couldn't answer
- **FR-041**: ListUnansweredQuestions MUST support filtering by date range and status
- **FR-042**: ListUnansweredQuestions MUST show question text, date asked, and current resolution status

#### Conversational Behavior

- **FR-043**: Admin Assistant MUST communicate exclusively in Spanish
- **FR-044**: Admin Assistant MUST use concise, professional, business-oriented language
- **FR-045**: Admin Assistant MUST ask for missing required information when admin requests incomplete actions
- **FR-046**: Admin Assistant MUST confirm sensitive/destructive actions before execution with explicit confirmation prompt
- **FR-047**: Admin Assistant MUST NOT expose internal identifiers (database IDs, UUIDs) in responses
- **FR-048**: Admin Assistant MUST support multi-step conversations with context retention
- **FR-049**: Admin Assistant MUST handle topic switching gracefully (e.g., from inventory to sales analytics)
- **FR-050**: Admin Assistant MUST provide action summaries at end of conversation turns

#### Security and Authorization

- **FR-051**: Every AI tool invocation MUST verify ROLE_ADMIN before execution
- **FR-052**: System MUST return 403 Forbidden if non-admin attempts to invoke admin tools
- **FR-053**: System MUST maintain complete isolation between admin assistant and customer assistant contexts
- **FR-054**: System MUST log all admin assistant actions for audit trail including user, action, timestamp, and parameters
- **FR-055**: System MUST sanitize all inputs to prevent injection attacks
- **FR-056**: System MUST rate-limit admin assistant requests to prevent abuse

#### Architecture and Implementation

- **FR-057**: All AI tools MUST act as thin adapters delegating to Application layer use cases
- **FR-058**: All business logic MUST reside in Application use cases, not in AI tools
- **FR-059**: All use cases MUST respect Domain entity invariants
- **FR-060**: Admin Assistant MUST remain channel-agnostic (web now, potentially API/mobile later)

### Key Entities *(existing, extended)*

- **AdminAssistantConversation** (existing from spec-007): Represents chat session between admin and assistant. Extended to support new tool invocations from additional admin capabilities.

- **AdminAssistantMessage** (existing from spec-007): Individual message in conversation. Extended to log tool invocations for new inventory, pricing, analytics, order, and customer tools.

- **AdminAssistantAction** (existing from spec-007): Audit log of actions. Extended with new action types: update_stock, update_price, update_order_status, query_analytics, query_customers.

- **Product** (existing): Extended use cases to support stock updates and price history tracking.

- **Order** (existing): New use cases for order retrieval and status updates via assistant.

- **User** (existing): New use cases for customer analytics and lifetime value calculations.

- **UnansweredQuestion** (existing from spec-006): Integration with assistant for reviewing customer knowledge gaps.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can access assistant and receive response within 3 seconds on 95% of requests
- **SC-002**: Administrators can check inventory, update stock, and confirm changes in under 30 seconds via conversation (compared to 60+ seconds via traditional UI navigation)
- **SC-003**: Administrators can retrieve sales insights (daily summary, top products) in under 15 seconds without opening analytics dashboard
- **SC-004**: 90% of admin conversational requests are correctly interpreted on first attempt without need for rephrasing
- **SC-005**: Admin assistant correctly enforces authorization on 100% of tool invocations (zero security bypass incidents)
- **SC-006**: Floating assistant UI adapts correctly across admin pages without layout conflicts or obscured controls
- **SC-007**: Admin assistant maintains conversation context across topic switches in 90% of multi-turn conversations
- **SC-008**: All destructive operations (stock updates, price changes, order status changes) receive explicit confirmation before execution with zero accidental data modifications
- **SC-009**: Time to complete routine admin tasks (checking stock, updating prices, reviewing orders) reduces by 40% compared to traditional admin interface
- **SC-010**: Administrators report 85% satisfaction with assistant's ability to understand natural language admin queries (measured via post-interaction survey)
- **SC-011**: Admin assistant handles API failures gracefully with user-friendly error messages on 100% of OpenAI outages
- **SC-012**: System successfully logs all admin assistant actions for audit trail with complete parameter capture on 100% of operations
