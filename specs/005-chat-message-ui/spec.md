# Feature Specification: Enhanced Chat Message Display & Content

**Feature Branch**: `005-chat-message-ui`  
**Created**: February 6, 2026  
**Status**: Draft  
**Input**: User description: "mejorar el contenido que se ve en el chat, ahora los mensajes no se ven bien, ni intuitivos, entonces quiero mejorarlo bien para que se entiendan mejor"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Structured Product Information Display (Priority: P1)

When the chatbot shows product information (prices, names, availability), users can quickly scan and understand the information through structured formatting with clear visual hierarchy.

**Why this priority**: Product information is the most common chatbot response. Poor formatting makes users miss critical details like prices or availability, directly impacting purchasing decisions.

**Independent Test**: Can be fully tested by asking "muéstrame laptops" and verifying that product information displays with clear formatting (name, price, stock status) in an easy-to-read structure.

**Acceptance Scenarios**:

1. **Given** user asks about products, **When** chatbot responds with product list, **Then** each product displays with: name in bold, price with currency symbol, and stock status clearly visible
2. **Given** user asks for product details, **When** chatbot shows a single product, **Then** information displays in a card-like format with clear labels for each attribute
3. **Given** chatbot lists multiple products, **When** displaying the list, **Then** products are separated with visual dividers and numbered for easy reference

---

### User Story 2 - Action Confirmation Messages (Priority: P1)

When users perform cart actions (add, remove items), they receive clear visual confirmation with structured summary of what changed.

**Why this priority**: Users need immediate feedback that their action succeeded. Current generic text messages create uncertainty about what actually happened to their cart.

**Independent Test**: Can be fully tested by saying "agrega 2 laptops al carrito" and verifying the response shows: (1) success confirmation, (2) items added with quantities, (3) updated cart total.

**Acceptance Scenarios**:

1. **Given** user adds item to cart, **When** action completes, **Then** chatbot shows: ✓ confirmation icon, item name, quantity added, new cart total in highlighted format
2. **Given** user removes item from cart, **When** action completes, **Then** chatbot shows structured message with removed item details and updated cart total
3. **Given** cart action fails, **When** error occurs, **Then** chatbot shows clear error message with icon and suggested next action

---

### User Story 3 - Clear User vs Bot Message Distinction (Priority: P2)

Users can instantly distinguish their messages from chatbot responses through visual styling (colors, alignment, spacing).

**Why this priority**: Conversation history becomes confusing when user and bot messages look similar. This is crucial for reviewing past interactions but less critical than content clarity.

**Independent Test**: Can be fully tested by sending 3 messages and reviewing the conversation history - user messages should be visually distinct from bot messages at a glance.

**Acceptance Scenarios**:

1. **Given** user sends a message, **When** viewing chat history, **Then** user messages align right with distinct background color
2. **Given** chatbot responds, **When** viewing chat history, **Then** bot messages align left with different background color and bot icon
3. **Given** conversation has 10+ messages, **When** scrolling through history, **Then** user can instantly identify who sent each message without reading content

---

### User Story 4 - Typing Indicator for Bot Responses (Priority: P2)

While waiting for chatbot response, users see a "typing..." indicator so they know the system is processing their request.

**Why this priority**: Reduces perceived wait time and prevents users from thinking the system is broken, but doesn't affect core functionality.

**Independent Test**: Can be fully tested by sending a message and observing that a typing indicator appears before the bot response shows.

**Acceptance Scenarios**:

1. **Given** user sends a message, **When** waiting for response, **Then** chatbot shows animated "..." typing indicator
2. **Given** typing indicator is visible, **When** bot response is ready, **Then** typing indicator disappears and response appears smoothly
3. **Given** bot response takes more than 2 seconds, **When** waiting, **Then** typing indicator remains visible throughout

---

### User Story 5 - Rich Product Cards with Quick Actions (Priority: P3)

When chatbot shows products, users see visual cards with product image thumbnails and action buttons ("Ver detalles", "Agregar al carrito") for direct interaction.

**Why this priority**: Nice-to-have enhancement that reduces friction, but basic text format with structured information (P1) is sufficient for MVP.

**Independent Test**: Can be fully tested by asking "muéstrame laptops" and verifying products display as interactive cards with images and buttons.

**Acceptance Scenarios**:

1. **Given** user asks about products, **When** chatbot lists products, **Then** each product shows as a card with: thumbnail image, name, price, and "Ver detalles" button
2. **Given** product card is displayed, **When** user clicks "Ver detalles", **Then** browser navigates to product detail page
3. **Given** product card is displayed, **When** user clicks "Agregar al carrito", **Then** item adds to cart and chatbot confirms with updated cart total

---

### User Story 6 - Message Timestamps (Priority: P3)

Users can see when each message was sent with subtle timestamps below each message.

**Why this priority**: Helpful for context when reviewing old conversations, but not essential for core chat functionality.

**Independent Test**: Can be fully tested by sending a message and verifying a timestamp appears below it.

**Acceptance Scenarios**:

1. **Given** user sends a message, **When** message displays, **Then** timestamp shows below message in small, gray text (format: "14:32")
2. **Given** conversation spans multiple days, **When** viewing history, **Then** messages from different days show date in addition to time
3. **Given** user views fresh conversation, **When** all messages are from today, **Then** only time displays (no date needed)

---

### Edge Cases

- What happens when chatbot response contains no structured data (e.g., plain greeting)? → Display as regular text without forced formatting
- How does system handle product lists with 20+ items? → Show first 5 products with "Ver todos los productos" link, avoid overwhelming chat
- What if product image fails to load in card view? → Show placeholder image with product icon
- How to display cart total when user has no items? → Show "Carrito vacío" message with suggestion to browse products
- What happens when message is very long (e.g., detailed product description)? → Truncate after 3 lines with "Leer más" expansion option

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST format product information responses with clear labels (Nombre, Precio, Stock) using bold text for product names
- **FR-002**: System MUST display cart action confirmations with structured format including: confirmation icon, item details, quantity, and cart total
- **FR-003**: System MUST distinguish user messages from bot messages using different background colors and alignment (user: right-aligned, bot: left-aligned)
- **FR-004**: System MUST display typing indicator (animated dots) while bot generates response
- **FR-005**: System MUST format prices consistently with currency symbol (e.g., "$1,299.99 USD")
- **FR-006**: System MUST separate multiple products in list responses with visual dividers
- **FR-007**: System MUST show success/error icons (✓/✗) for action confirmations
- **FR-008**: System MUST truncate long messages after 3 lines with expandable "Leer más" functionality
- **FR-009**: System MUST limit product lists to maximum 5 items in chat, with link to view all
- **FR-010**: Bot messages MUST include bot avatar/icon on the left side
- **FR-011**: User messages MUST NOT display avatar (minimal design)
- **FR-012**: (P3 - Optional) Product cards MAY display thumbnail images if feature is implemented
- **FR-013**: (P3 - Optional) Product cards MAY include action buttons ("Ver detalles", "Agregar") if feature is implemented
- **FR-014**: (P3 - Optional) Messages MAY display timestamps if feature is implemented

### Key Entities

- **ChatMessage**: Represents a single message in conversation with attributes: content (text/structured), role (user/bot), timestamp, format type (plain_text, product_list, action_confirmation, error)
- **StructuredContent**: Represents formatted content types with attributes: type (product_info, cart_summary, product_list), data (structured JSON with labels and values)
- **ProductDisplayInfo**: Product data optimized for chat display with attributes: name, price (formatted with currency), stock_status, thumbnail_url (optional for P3), product_url

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can identify product prices in chatbot responses within 2 seconds of message appearing (vs. current 5+ seconds scanning plain text)
- **SC-002**: 90% of users correctly interpret cart action confirmations without needing to check cart page separately
- **SC-003**: New users can distinguish user vs bot messages at first glance (100% success rate in usability testing)
- **SC-004**: Perceived chatbot response time reduces by 30% through typing indicator (measured via user surveys)
- **SC-005**: Support tickets related to "chatbot unclear" or "didn't know if action worked" reduce by 60%
- **SC-006**: Users can scan a list of 5 products and identify the cheapest one in under 5 seconds

## Assumptions

- Users primarily interact with chatbot via desktop/mobile web browsers (responsive design needed)
- Most common chatbot interactions involve product queries and cart actions
- Spanish is primary language for messages (formatting must work with Spanish text)
- Existing chat widget infrastructure (chatbot.js) can be extended to support structured message formats
- Product images are already available in system for optional P3 card feature
- Current message rendering uses simple text appending; needs upgrade to template-based rendering for structured content

## Dependencies

- Existing chatbot widget (chatbot.js) from spec-004
- Product entity with name_es field from spec-004
- Cart API endpoints for fetching updated totals after actions
- AI agent response format may need enhancement to return structured data instead of plain text

## Out of Scope

- Voice interactions or audio messages
- Video content in chat messages
- Real-time translation to other languages (Spanish only)
- Message editing or deletion
- Message reactions (emoji, likes)
- File/image uploads from users
- Chat message search functionality
- Conversation export/download
- Integration with external messaging platforms (WhatsApp, Messenger, etc.)
