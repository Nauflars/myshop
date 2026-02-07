# Feature Specification: Conversational Context & Memory Management

**Feature Branch**: `009-context-memory`  
**Created**: 2026-02-07  
**Status**: Draft  
**Input**: User description: "Conversational Context & Memory Management"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Follow-up Questions in Customer Chat (Priority: P1)

As a customer, when I ask the AI assistant about products and then ask follow-up questions, the assistant should remember what we were discussing and answer contextually without requiring me to repeat information.

**Why this priority**: This is the most fundamental aspect of conversational AI. Without follow-up capability, the assistant feels like a search box rather than a conversation partner, severely degrading user experience.

**Independent Test**: Can be fully tested by having a conversation like "Show me laptops" → "What's the cheapest one?" → "Add it to cart". The assistant should understand "cheapest one" refers to laptops and "it" refers to the selected laptop.

**Acceptance Scenarios**:

1. **Given** a customer browses laptops, **When** they ask "What's the cheapest one?", **Then** the assistant understands "one" refers to laptops from previous context
2. **Given** a customer adds a product to cart, **When** they ask "Can you show me similar items?", **Then** the assistant remembers the product and shows related products
3. **Given** a customer discusses product A, **When** they ask "What are the specs?", **Then** the assistant shows specs for product A without asking which product
4. **Given** a customer is in checkout flow, **When** they say "Go back", **Then** the assistant returns to the previous step remembering cart state

---

### User Story 2 - Admin Multi-step Operations (Priority: P1)

As an admin, when I ask the assistant to help me with inventory management, I should be able to perform multi-step operations (query inventory, decide on changes, confirm updates) without losing context between steps.

**Why this priority**: Admin operations are often destructive and require confirmation. Without context, admins would need to repeat product names/IDs at every step, making the assistant unusable for complex workflows.

**Independent Test**: Can be fully tested by workflow: "Show low stock products" → "Update stock for Product X to 50" → "Confirm". The assistant should remember Product X across all three messages.

**Acceptance Scenarios**:

1. **Given** admin queries low stock products, **When** they say "Update stock for the first one to 50", **Then** the assistant knows which product to update
2. **Given** admin initiates a stock update, **When** they say "Confirm", **Then** the assistant executes the update remembering the pending operation
3. **Given** admin reviews sales data for Q1, **When** they ask "Compare with Q4", **Then** the assistant remembers the Q1 context and shows comparison
4. **Given** admin is managing product inventory, **When** they switch to analyzing sales, **Then** the context changes appropriately to sales analysis mode

---

### User Story 3 - Context Persistence Across Page Navigation (Priority: P2)

As a user (customer or admin), when I navigate to different pages in the application while the chat is open, the conversation context should persist so I can continue where I left off.

**Why this priority**: Users often navigate between pages while shopping or working. Losing context on page change would force them to restart conversations, which is frustrating.

**Independent Test**: Can be fully tested by: Start conversation on Products page → Navigate to Cart page → Continue conversation. The assistant should remember previous messages and context.

**Acceptance Scenarios**:

1. **Given** customer discusses a product on Products page, **When** they navigate to Cart page and ask "Is that product in my cart?", **Then** the assistant remembers which product was discussed
2. **Given** admin starts inventory review on Dashboard, **When** they navigate to Products page, **Then** the assistant maintains inventory  management context
3. **Given** customer adds item to cart via chat, **When** they refresh the page, **Then** the conversation continues with cart context intact

---

### User Story 4 - Context Recovery After Timeout (Priority: P3)

As a user who returns after being inactive, I should receive a clear message if my context has expired, and the assistant should help me restart the conversation gracefully.

**Why this priority**: While important for UX, this is a fallback scenario. Most users will have active sessions, so this enhances but isn't critical for core functionality.

**Independent Test**: Can be fully tested by: Start conversation → Wait beyond expiration time → Continue conversation. The assistant should acknowledge context loss and offer to start fresh.

**Acceptance Scenarios**:

1. **Given** a customer's context has expired, **When** they send a new message, **Then** the assistant politely indicates the conversation needs to restart
2. **Given** an admin's context expired during lunch break, **When** they return and ask a question, **Then** the assistant asks clarifying questions instead of assuming prior context
3. **Given** context has expired, **When** user starts a new conversation, **Then** system creates fresh context without errors

---

### Edge Cases

- What happens when context data becomes corrupted (e.g., invalid JSON in Redis)?
- How does system handle context when user switches between customer and admin roles?
- What occurs if context expires mid-conversation while user is typing?
- How does system handle extremely long conversations (>100 messages)?
- What happens if Redis/context storage becomes unavailable?
- How are anonymous customer sessions handled vs authenticated users?
- What occurs if two devices/tabs access the same chat simultaneously?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST persist conversational context outside the AI model (in Redis or equivalent key-value store)
- **FR-002**: System MUST maintain separate context stores for customer and admin roles, with zero cross-contamination
- **FR-003**: System MUST inject current context into AI system prompts before each interaction
- **FR-004**: System MUST update context after successful AI tool executions and user interactions
- **FR-005**: System MUST expire customer contexts after 30 minutes of inactivity (configurable)
- **FR-006**: System MUST expire admin contexts after 60 minutes of inactivity (configurable)
- **FR-007**: System MUST handle expired contexts gracefully by resetting to default state and informing the user
- **FR-008**: System MUST NOT store sensitive data (passwords, payment details) in context
- **FR-009**: System MUST scope context data per user/session to prevent data leakage
- **FR-010**: System MUST support anonymous customer sessions with temporary session identifiers
- **FR-011**: Context updates MUST be deterministic and auditable (same input = same context state)
- **FR-012**: System MUST validate context data structure before injection to prevent AI prompt corruption

### Customer Context Attributes

The customer conversational context MUST include:

- **User identifier** (authenticated user ID or anonymous session ID)
- **Current interaction flow**: One of: browsing, product_selection, cart_management, checkout, order_review
- **Selected product(s)**: Product names or references from recent conversation
- **Cart snapshot**: Concise summary of cart (product names, quantities, approximate total)
- **Last executed tool**: Name of most recent AI tool called (e.g., "add_to_cart", "search_products")
- **Pending confirmations**: Flags for actions awaiting user confirmation
- **Language preference**: User's preferred language (es-ES, en-US, etc.)
- **Conversation turn count**: Number of messages exchanged (for summarization triggers)

### Admin Context Attributes

The admin conversational context MUST include:

- **Admin identifier**: Authenticated admin user ID
- **Current operational flow**: One of: product_management, inventory_management, sales_analysis, order_management, customer_support
- **Active entities**: Product names, order IDs, or customer references currently being discussed
- **Time period**: Date range for analytics queries (e.g., "Q1 2026", "last 30 days")
- **Pending actions**: Destructive operations awaiting confirmation (e.g., stock update, price change)
- **Last executed tool**: Name of most recent admin AI tool called
- **Conversation turn count**: Number of messages exchanged

### Context Storage Keys

- **Customer context key format**: `chat:customer:{userId}` or `chat:customer:anon:{sessionId}`
- **Admin context key format**: `chat:admin:{adminId}`
- **TTL (Time To Live)**: Must be configurable via environment variables
- **Data format**: JSON-serialized context object

### Context Injection Rules

- **Injection point**: Context must be appended to system prompt before user message
- **Format**: Human-readable summary format, not raw JSON
- **Size limit**: Context summary must not exceed 500 tokens to avoid overwhelming AI
- **Isolation**: Customer assistant must never receive admin context and vice versa

### Security & Privacy

- **FR-013**: Admin context MUST be protected by role checks (ROLE_ADMIN required)
- **FR-014**: Context data MUST NOT be exposed to frontend JavaScript directly
- **FR-015**: Context keys MUST include user/admin ID to prevent unauthorized access
- **FR-016**: System MUST log context access and mutations for audit purposes

### Key Entities *(include if feature involves data)*

- **ConversationContext**: Abstract base representing shared context structure
  - Attributes: userId, flow, lastTool, turnCount, createdAt, updatedAt, expiresAt
  
- **CustomerConversationContext**: Customer-specific context (extends ConversationContext)
  - Additional Attributes: selectedProducts, cartSnapshot, pendingConfirmations, language
  
- **AdminConversationContext**: Admin-specific context (extends ConversationContext)
  - Additional Attributes: activeEntities, timePeriod, pendingActions

## Success Criteria *(mandatory)*

1. **Follow-up Accuracy**: 95% of follow-up questions must be answered correctly using context from previous messages (measured via test suite)
2. **Multi-step Completion**: Users can complete 3-step workflows (query → action → confirm) without repeating information
3. **Context Persistence**: Context survives page navigation 100% of the time when within expiration window
4. **Recovery Time**: System recovers from expired context within 1 second and provides clear feedback to user
5. **Zero Context Leakage**: 0 incidents of admin context visible to customers or vice versa (security  requirement)
6. **Performance**: Context load and save operations complete in <50ms at 95th percentile
7. **User Satisfaction**: Conversational coherence score improves by at least 40% (measured via user feedback or A/B testing)

## Assumptions *(optional - document if making assumptions)*

1. Redis or equivalent key-value store is available and configured in production environment
2. AI assistant already has basic conversation handling infrastructure (conversation manager, message storage)
3. User authentication system provides stable user IDs for context keying
4. Anonymous users receive session IDs from existing session management system
5. AI prompts can accommodate an additional 300-500 tokens for context injection without performance degradation
6. Current AI tools return structured data that can be parsed for context updates
7. Application already has logging infrastructure for audit trail

## Out of Scope

- Context summarization for extremely long conversations (>100 messages) - will be addressed in future spec
- Cross-session memory (remembering user preferences across days/weeks) - future enhancement
- Proactive AI suggestions based on context - future feature
- Multi-channel context sharing (web + mobile app) - future enhancement
- Real-time context synchronization across multiple browser tabs - not in MVP
- Context export/import functionality - not needed for MVP
- Advanced analytics on context patterns - future enhancement

## Dependencies *(optional)*

- Redis server (or compatible key-value store) must be provisioned
- User authentication system must provide user IDs
- Session management system must provide anonymous session IDs
- AI agent infrastructure must support system prompt modification
- Logging infrastructure must support context audit trails

## Testing Requirements

### Unit Tests

- Context serialization and deserialization
- Context expiration logic
- Context key generation for different user types
- Context validation (schema validation)
- Role isolation (admin vs customer)

### Integration Tests

- Context persistence to Redis
- Context retrieval from Redis
- Context injection into AI prompts
- Context updates after tool executions
- Expired context handling

### End-to-End Tests

- **Test 1**: Complete follow-up conversation flow (3+ messages with context references)
- **Test 2**: Multi-step admin workflow with pending confirmation
- **Test 3**: Context persistence across page navigation
- **Test 4**: Context expiration and recovery scenario
- **Test 5**: Role isolation test (verify admin context never leaks to customer and vice versa)
- **Test 6**: Anonymous user context handling
- **Test 7**: Concurrent context access (same user, multiple tabs)

## Notes

- This specification establishes the foundation for stateful AI conversations
- Context management is invisible to users when working correctly
- The true measure of success is users NOT noticing they're being managed by a stateless system
- Context design should prioritize privacy (minimal data) and security (role isolation)
- Consider implementing context versioning for future schema migrations
