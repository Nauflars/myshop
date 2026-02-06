# Feature Specification: Enhanced Chatbot UX

**Feature Branch**: `004-enhanced-chatbot-ux`  
**Created**: 2026-02-06  
**Status**: Draft  
**Input**: User requirements for improved chatbot experience with floating UI, persistent conversations, real-time cart updates, Spanish product names, and optional user profile completion

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Floating Draggable Chatbot Widget (Priority: P1)

As a user, I want a floating chatbot widget that I can drag anywhere on the screen, so that it doesn't block important content while I'm shopping.

**Why this priority**: Core UX improvement that immediately enhances user experience on all pages. Essential foundation for other chat features.

**Independent Test**: Can be fully tested by opening the chat widget and dragging it to different screen positions. Success means the widget moves smoothly, stays within viewport bounds, and maintains its position until moved again or page refreshed.

**Acceptance Scenarios**:

1. **Given** the chat widget is visible, **When** I click and drag the widget header, **Then** the widget follows my mouse cursor smoothly
2. **Given** I drag the widget near the edge, **When** I release it, **Then** it stays within the viewport boundaries
3. **Given** the widget is positioned somewhere, **When** I open/close the widget, **Then** it maintains its position
4. **Given** I'm on any page with the widget positioned, **When** I navigate to another page, **Then** the widget appears in the same position

---

### User Story 2 - Persistent Conversation Across Pages (Priority: P1)

As a user, I want my chat conversation to persist when navigating between pages, so that I don't lose context or have to repeat myself.

**Why this priority**: Critical for maintaining conversation flow. Without this, users lose their chat history every time they navigate, making the chatbot frustrating to use.

**Independent Test**: Start a conversation on the home page, add items to cart via chat, navigate to products page, then orders page. The chat should maintain the entire conversation history across all page changes.

**Acceptance Scenarios**:

1. **Given** I have an active conversation, **When** I navigate to a different page, **Then** the chat history remains intact
2. **Given** I'm chatting on one page, **When** I add a product to cart via chat and navigate to products page, **Then** the chat shows the complete history including the add-to-cart exchange
3. **Given** the chat widget is open, **When** I navigate between pages, **Then** the widget stays open with the same conversation
4. **Given** I close the browser and return later (same session), **When** I open the chat, **Then** my previous conversation loads automatically

---

### User Story 3 - Real-Time Cart Badge Updates (Priority: P2)

As a user, when I add products via chat, I want to see the cart badge update immediately, so that I have visual confirmation the action worked.

**Why this priority**: Provides immediate visual feedback and builds user confidence. Important for UX but not blocking core chat functionality.

**Independent Test**: With cart badge visible (value at 0), use chat to add 2 products. The badge should update to 2 without requiring page refresh.

**Acceptance Scenarios**:

1. **Given** my cart has 0 items, **When** the chat adds a product for me, **Then** the cart badge updates to 1 immediately
2. **Given** my cart has 2 items, **When** the chat adds 3 more of the same product, **Then** the badge updates to 5
3. **Given** my cart has items, **When** the chat removes a product, **Then** the badge decreases accordingly
4. **Given** the chat completes my checkout, **When** the order is created, **Then** the cart badge resets to 0

---

### User Story 4 - Spanish Product Names and Consistency (Priority: P2)

As a Spanish-speaking user, I want the chatbot to use Spanish product names consistently, so that the shopping experience feels natural and professional.

**Why this priority**: Improves user experience for Spanish speakers but doesn't break functionality if not perfect. Can be refined over time.

**Independent Test**: Ask the chatbot "¿Qué productos tienes?" and verify all product names appear in Spanish. Add products using Spanish names and verify they're correctly identified.

**Acceptance Scenarios**:

1. **Given** I ask about products in Spanish, **When** the chatbot lists products, **Then** all names are in Spanish
2. **Given** I ask to add "Portátil HP Pavilion", **When** the chatbot processes the request, **Then** it finds and adds the correct product
3. **Given** the underlying database has English names, **When** the chatbot displays products, **Then** it shows the Spanish translation consistently
4. **Given** I'm viewing my cart via chat, **When** the chatbot lists items, **Then** product names are in Spanish

---

### User Story 5 - Optional User Profile Completion at Checkout (Priority: P3)

As a new user, I want to provide minimal information during registration and complete my profile details during my first checkout, so that I can start browsing quickly without filling lengthy forms.

**Why this priority**: Nice-to-have improvement. Registration already works, this just makes it more user-friendly. Can be added after core chat improvements.

**Independent Test**: Register with only email/password. On first checkout via chat, provide address details when prompted. On second checkout, chat should not ask for already-saved details.

**Acceptance Scenarios**:

1. **Given** I registered with only email/password, **When** I attempt my first checkout via chat, **Then** the chat asks for shipping address, phone, name
2. **Given** I provided my address during first checkout, **When** I attempt a second checkout, **Then** the chat confirms my saved address without asking again
3. **Given** my profile is incomplete, **When** the chat needs missing info, **Then** it asks politely and saves my response
4. **Given** I update my address via chat, **When** I checkout next time, **Then** the chat uses the updated address

---

### User Story 6 - Chat Transparency/Opacity Control (Priority: P4)

As a user, I want to adjust the chat widget's transparency, so that I can see content behind it while still interacting with the chat.

**Why this priority**: Nice visual enhancement but not essential. Can be added as polish after core features work.

**Independent Test**: Open chat settings and adjust opacity slider. Widget should become semi-transparent while remaining functional.

**Acceptance Scenarios**:

1. **Given** the chat widget is open, **When** I adjust the opacity slider, **Then** the widget becomes more/less transparent
2. **Given** the widget is semi-transparent, **When** I interact with chat controls, **Then** they remain fully functional
3. **Given** I set a specific transparency level, **When** I navigate to another page, **Then** the transparency setting persists

---

### User Story 7 - User Confirmation for Actions (Priority: P1)

As a user, I want the chatbot to confirm important actions before executing them, so that I don't accidentally add wrong products or place unwanted orders.

**Why this priority**: Critical for user trust and preventing mistakes. Should be implemented from the start for any action-based features.

**Independent Test**: Ask chat to add expensive items to cart. Chat should display item details and ask for confirmation before adding.

**Acceptance Scenarios**:

1. **Given** I ask to add a product, **When** the chat identifies it, **Then** it shows product details and asks "¿Deseas añadir este producto?"
2. **Given** I ask to process checkout, **When** the chat is ready, **Then** it summarizes the order and asks for final confirmation
3. **Given** I ask to clear my cart, **When** the chat understands, **Then** it warns about removing all items and asks for confirmation
4. **Given** I say "no" to a confirmation, **When** the chat receives my response, **Then** it cancels the action and asks what else I need

---

### Edge Cases

- **Navigation during chat interaction**: What happens when user navigates to another page while the chatbot is typing a response?
- **Multiple browser tabs**: If user has the shop open in two tabs with different chat positions, how does positioning sync?
- **Cart updates from UI and Chat**: If user adds items via regular UI, how quickly does chat recognize the updated cart?
- **Network failure during cart update**: If adding via chat fails, does the badge revert? Does chat notify the user?
- **Spanish name not found**: If a product has no Spanish translation, does chat use English name or report that it can't find the product?
- **Incomplete profile at checkout**: If user skips optional fields during checkout prompt, does it save partial info or require all fields?
- **Very long conversations**: Do older messages get pruned? Is there a conversation history limit?
- **Widget dragged off-screen**: If user somehow drags widget beyond viewport, how does it recover?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Chat widget MUST be draggable by clicking and holding its header
- **FR-002**: Chat widget position MUST persist across page navigations using localStorage
- **FR-003**: Chat widget MUST stay within viewport boundaries (prevent dragging off-screen)
- **FR-004**: Conversation history MUST persist when user navigates between pages
- **FR-005**: Conversation ID MUST be stored in localStorage and tied to the user session
- **FR-006**: Cart badge counter MUST update immediately when products are added/removed via chat
- **FR-007**: Chat MUST listen for cart update events and refresh the badge without page reload
- **FR-008**: Product names displayed by chatbot MUST be in Spanish throughout the conversation
- **FR-009**: Product mapping table MUST maintain English-to-Spanish translations for all products
- **FR-010**: Chatbot MUST accept product requests in Spanish and map them to correct database products
- **FR-011**: User registration MUST require only email, password, and username
- **FR-012**: Additional user fields (full name, address, phone, city, postal code) MUST be optional
- **FR-013**: During first checkout via chat, system MUST prompt for missing profile information
- **FR-014**: Once user provides profile details, system MUST NOT prompt for the same information in future checkouts
- **FR-015**: Chatbot MUST show confirmation dialog before executing actions (add to cart, checkout, clear cart)
- **FR-016**: Confirmation prompts MUST include relevant details (product name, price, quantity, etc.)
- **FR-017**: User MUST be able to cancel any action during the confirmation step
- **FR-018**: Chat widget MUST have an opacity/transparency control (slider or preset levels)
- **FR-019**: Transparency setting MUST persist across page navigations
- **FR-020**: Widget MUST remain interactive at any transparency level
- **FR-021**: Conversation state MUST be maintained until user explicitly clears it or logs out
- **FR-022**: Cart count in navigation badge MUST always reflect the actual cart item quantity
- **FR-023**: System MUST synchronize cart state between UI actions and chat actions in real-time

### Key Entities

- **ChatWidgetState**: Tracks widget position (x, y coordinates), opacity level, open/closed state, stored in localStorage
- **ConversationHistory**: Already exists (spec-003), continues to store messages and persist across sessions
- **ProductTranslation**: Maps English product names to Spanish equivalents (or vice versa)
- **UserProfile**: Extended to include optional fields: fullName, phoneNumber, shippingAddress, city, postalCode, profileComplete flag
- **CartUpdateEvent**: Event emitted when cart changes (via UI or chat) to trigger badge refresh
- **ActionConfirmation**: Temporary state tracking pending actions awaiting user confirmation

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can drag the chat widget to any position on screen within 2 seconds
- **SC-002**: Widget position persists across 100% of page navigations during a session
- **SC-003**: Conversation history is retained across page changes with 0% data loss
- **SC-004**: Cart badge updates within 500ms of a product being added/removed via chat
- **SC-005**: 100% of product names display in Spanish when user interacts in Spanish
- **SC-006**: New users can complete registration in under 1 minute (reduced from previous 2-3 minutes)
- **SC-007**: First-time checkout users provide address details once and never need to re-enter them
- **SC-008**: 100% of critical actions (checkout, clear cart, add expensive items) require user confirmation
- **SC-009**: Zero accidentally placed orders due to missing confirmations
- **SC-010**: Chat widget remains fully usable at any transparency setting from 30% to 100% opacity

### User Experience Targets

- Chat widget does not obscure important content (cart, checkout button, product images)
- Users report that conversation flow feels natural and uninterrupted across pages
- Spanish-speaking users do not encounter English product names unexpectedly
- New users can start browsing immediately without completing lengthy registration forms
- Users feel in control of their actions with clear confirmation dialogs

##  Assumptions

- Users are accessing the application in modern browsers with localStorage support (Chrome 80+, Firefox 75+, Safari 13+, Edge 80+)
- Product catalog already has Spanish names available (either in database or via translation mapping)
- Cart badge update mechanism can be implemented with JavaScript event listeners without full page reloads
- Users primarily browse on desktop/laptop (mobile responsive design is desirable but not mandatory for this spec)
- Existing authentication/session management is sufficient to tie conversations and user profiles together
- Database schema can be extended to add optional user profile fields without breaking existing user accounts

## Out of Scope

- Mobile-specific touch gestures for dragging chat (mouse-only for this iteration)
- Multiple simultaneous chat conversations
- Automatic translation of user input from English to Spanish
- Voice input/output for chat
- Chat widget themes or color customization
- Export/download of conversation history
- Admin panel for managing product translations
- Real-time cart synchronization across multiple devices
- Progressive Web App (PWA) offline support for chat

## Dependencies

- spec-003 (Conversation Persistence) must be complete - this spec builds on existing conversation database
- User entity and authentication system from spec-002
- Cart management system from spec-002
- Product catalog with readable names (spec-002)

## Risks & Mitigations

**Risk**: Draggable widget may conflict with page scrolling on touch devices  
**Mitigation**: Use CSS `touch-action: none` on widget header, implement touch-specific event handlers if mobile support is added later

**Risk**: localStorage has size limits (~5-10MB), widget position and conversation IDs may exceed this with heavy usage  
**Mitigation**: Store only essential data (coordinates, opacity, conversationId reference). Actual conversation messages are in database, not localStorage

**Risk**: Product name translations may be incomplete or incorrect  
**Mitigation**: Start with manual translation table for existing products. Fall back to English name with note if Spanish unavailable. Plan for gradual improvement.

**Risk**: Real-time badge updates require event-driven architecture not yet implemented  
**Mitigation**: Implement simple custom JavaScript events. When cart changes via fetch API, trigger 'cart Updated' even

t that badge listener catches.

**Risk**: User may ignore confirmation dialogues and click through quickly  
**Mitigation**: Make confirmation buttons visually distinct (green for confirm, red for cancel). Use clear, concise language. Don't allow enter key to auto-confirm without explicit button click.

## Technical Notes (Non-Prescriptive)

- Chat widget position could be stored as `{ x: number, y: number }` in localStorage under key `chatWidgetPosition`  
- Drag functionality could use native HTML5 drag API or mouse event listeners (mousedown, mousemove, mouseup)
- Cart badge update could use CustomEvent in JavaScript: `window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { count: newCount }}))`  
- Product translation could be a simple JSON map initially: `{ "Laptop HP Pavilion": "Portátil HP Pavilion", ... }`
- User profile fields could be added to existing User entity with nullable columns: `full_name`, `phone_number`, `shipping_address`, etc.
- Transparency control could be CSS `opacity` property adjusted via slider input
- Persistence across pages achieved by reading localStorage on every page load and applying saved position/state

These are suggestions to guide implementation planning, not requirements.
