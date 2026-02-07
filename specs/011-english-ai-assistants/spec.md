# Feature Specification: English AI Assistants with Context Persistence

**Feature Branch**: `011-english-ai-assistants`  
**Created**: 2026-02-07  
**Status**: Draft  
**Input**: User description: "Adapt customer and admin virtual assistants to speak in English with English system prompts, fix context memory persistence issues, and investigate conversation storage location for proper context retrieval across sessions"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - English-Speaking Customer Assistant (Priority: P1)

A customer visits the shop and interacts with the AI chatbot. The assistant communicates entirely in English, understands English queries, and provides product recommendations in English regardless of the customer's input language.

**Why this priority**: The customer assistant is the primary user-facing AI feature. English is the target language for international customers and testing.

**Independent Test**: Can be fully tested by opening the customer chatbot, sending messages in English/Spanish, and verifying all responses are in English with proper grammar and natural phrasing.

**Acceptance Scenarios**:

1. **Given** a customer opens the chatbot, **When** they type "hello", **Then** the assistant responds in English with a greeting
2. **Given** a customer asks "show me laptops" in English, **When** the search executes, **Then** product descriptions and recommendations are presented in English
3. **Given** a customer writes in Spanish "buscar portátiles", **When** the assistant processes the request, **Then** the response is in English ("Here are the laptops I found...")
4. **Given** a customer asks about stock or pricing, **When** the assistant retrieves product data, **Then** all monetary values and availability messages are formatted for English (e.g., "In stock", "$99.99")

---

### User Story 2 - English-Speaking Admin Assistant (Priority: P1)

An administrator uses the admin panel AI assistant to manage inventory, check stock, and perform administrative tasks. The assistant communicates entirely in English with proper business terminology.

**Why this priority**: Admin assistant is critical for administrative efficiency and must use professional English for business operations.

**Independent Test**: Can be tested by logging into the admin panel, invoking the assistant, and verifying all commands, confirmations, and error messages are in English.

**Acceptance Scenarios**:

1. **Given** an admin opens the floating assistant, **When** they type "check stock for webcam", **Then** the assistant responds in English with current stock levels
2. **Given** an admin requests "update price of laptop to 899", **When** the operation completes, **Then** the confirmation message is in English ("Price updated successfully to $899.00")
3. **Given** an admin asks for help, **When** the assistant displays available commands, **Then** all command descriptions are in English
4. **Given** an error occurs during an operation, **When** the assistant reports it, **Then** the error message is in clear, professional English

---

### User Story 3 - Persistent Context Across Sessions (Priority: P2)

A customer has a conversation with the AI assistant, leaves the site, and returns later. The assistant remembers the previous conversation context and can refer back to prior interactions.

**Why this priority**: Context persistence greatly improves user experience by eliminating repetitive questions and enabling personalized recommendations based on history.

**Independent Test**: Can be tested by having a conversation about specific products, closing the browser, returning to the site, and verifying the assistant recalls the previous discussion.

**Acceptance Scenarios**:

1. **Given** a customer discusses "gaming laptops" in a session, **When** they return 1 hour later, **Then** the assistant can reference "the gaming laptops we discussed earlier"
2. **Given** a customer asks "what did I look at last time?", **When** conversation history exists, **Then** the assistant lists previous products viewed
3. **Given** a customer's context has expired (>7 days), **When** they return, **Then** the assistant treats them as a new visitor with fresh context
4. **Given** a customer has multiple devices, **When** they use a different device with the same user ID, **Then** context is shared across devices

---

### User Story 4 - Context Storage Investigation & Documentation (Priority: P3)

Developers can locate and query conversation storage to debug context issues, understand data retention, and implement context-aware features.

**Why this priority**: Understanding storage mechanism is essential for debugging and future enhancements, but doesn't directly impact user experience.

**Independent Test**: Can be tested by reviewing documentation that shows exact database/collection names, schemas, and example queries to retrieve conversation history.

**Acceptance Scenarios**:

1. **Given** a developer needs to debug context issues, **When** they consult the documentation, **Then** they find the exact MongoDB collection or MySQL table storing conversations
2. **Given** a developer queries the storage, **When** they search by user ID, **Then** they retrieve all conversation messages with timestamps
3. **Given** a support team member needs to review a customer's AI interactions, **When** they use the documented query, **Then** they can view the full conversation history
4. **Given** a developer wants to implement new context features, **When** they review the schema documentation, **Then** they understand all stored fields and relationships

---

### Edge Cases

- What happens when a user switches between English and Spanish mid-conversation? (Assistant continues in English but understands both)
- How does system handle context when user clears cookies/browser data? (Context reset, treated as new user)
- What if conversation storage is unavailable (MongoDB down)? (Assistant works but without persistent context, logs warning)
- How are conversations cleaned up? (Retention policy: 90 days for inactive users, configurable)
- What if a user has extremely long conversation history (>1000 messages)? (Load last 50 messages for context, summarize older)

## Requirements *(mandatory)*

### Functional Requirements

#### English Language Support

- **FR-001**: Customer chatbot system prompts MUST be written entirely in English
- **FR-002**: Admin assistant system prompts MUST be written entirely in English
- **FR-003**: All AI-generated responses (greetings, product descriptions, confirmations, errors) MUST be in English
- **FR-004**: System MUST support English input processing while understanding common Spanish queries (translate intent, respond in English)
- **FR-005**: Currency formatting MUST use English conventions (e.g., "USD $99.99" not "99,99 USD")
- **FR-006**: Date/time formatting MUST use English formats (e.g., "February 7, 2026" not "7 de febrero de 2026")

#### Context Persistence

- **FR-007**: System MUST persist customer conversation history across browser sessions
- **FR-008**: System MUST persist admin conversation history across browser sessions
- **FR-009**: Context MUST be retrievable by user identifier (customer ID or session ID for anonymous users)
- **FR-010**: System MUST load previous conversation context when user returns within retention window
- **FR-011**: System MUST include timestamp metadata with each message for chronological ordering
- **FR-012**: Context retention policy MUST be configurable (default: 90 days)

#### Storage Investigation

- **FR-013**: Documentation MUST specify exact storage location for customer conversations (database/collection/table name)
- **FR-014**: Documentation MUST specify exact storage location for admin conversations
- **FR-015**: Documentation MUST provide schema definition including all fields and data types
- **FR-016**: Documentation MUST include example queries for retrieving conversation history
- **FR-017**: System MUST log conversation storage failures with detailed error information

### Key Entities

- **Conversation**: Represents a complete chat session with unique ID, user ID, creation timestamp, last activity timestamp, and retention status
- **Message**: Individual message within a conversation including message ID, conversation ID, role (user/assistant/system), content, timestamp, and metadata (tool calls, tokens used)
- **ConversationContext**: Aggregated context from conversation including user preferences, product interests, past queries, and session state (managed by CustomerContextManager/AdminContextManager)
- **User**: Customer or admin user with unique ID linking to their conversation history

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of customer chatbot responses are in grammatically correct English
- **SC-002**: 100% of admin assistant responses are in professional English with appropriate business terminology
- **SC-003**: Context is successfully retrieved for returning users 95% of the time (within retention window)
- **SC-004**: Average conversation continuity score improves by 50% (measured by relevant context references in assistant responses)
- **SC-005**: Zero Spanish words or phrases appear in system-generated English responses (excluding proper nouns and technical terms)
- **SC-006**: Documentation enables developers to query conversation storage successfully on first attempt (measured by team feedback)
- **SC-007**: Context load time is under 200ms for conversations with up to 100 messages

## Scope & Constraints *(optional)*

### In Scope

- Converting all existing Spanish system prompts to English
- Implementing or fixing conversation persistence for customer chatbot
- Implementing or fixing conversation persistence for admin assistant
- Documenting conversation storage schema and access patterns
- Testing context retrieval across sessions
- Configuring default context retention policy

### Out of Scope

- Multi-language support with user-selectable language (future feature)
- Real-time translation of user inputs from Spanish to English
- Voice/speech interface in English
- Historical conversation migration (existing Spanish conversations remain as-is)
- Advanced context summarization algorithms (beyond simple truncation)

### Constraints

- Must not break existing Symfony AI Platform integration
- Must maintain compatibility with current MongoDB and MySQL databases
- English proficiency level: Business English (B2-C1), professional but conversational
- No changes to UI/frontend text (only AI assistant responses)

## Assumptions *(optional)*

1. Current system prompts are stored in PHP class attributes or configuration files (not database)
2. Conversation storage already exists but may have bugs or missing persistence logic
3. CustomerContextManager and AdminContextManager services exist and handle context
4. MongoDB is the likely storage for conversations (based on existing vector embedding storage)
5. Context retention of 90 days is acceptable for business/privacy requirements
6. Users primarily interact in English or Spanish only

## Dependencies *(optional)*

- Symfony AI Platform (existing integration must continue working)
- OpenAI API (for English language generation)
- MongoDB (for conversation storage if confirmed)
- CustomerContextManager service
- AdminContextManager service
- Existing authentication system (for user ID retrieval)

## Non-Functional Requirements *(optional)*

### Performance

- Context retrieval: < 200ms for 100 messages
- Context save: < 100ms per message
- No impact on chatbot response time (context operations must be async or cached)

### Security

- Conversation data must be encrypted at rest (follow existing database encryption)
- Access to conversation storage must be restricted to authorized services only
- PII in conversations must follow GDPR retention policies (auto-delete after retention window)

### Reliability

- Context storage failures must not crash the chatbot (graceful degradation)
- Failed context saves must be logged for later retry or investigation
- System must continue functioning if conversation storage is temporarily unavailable

## Open Questions *(optional)*

1. **Storage Confirmation**: Are conversations currently stored in MongoDB (`conversations` collection) or MySQL (`conversations` table)? [NEEDS INVESTIGATION]
2. **Existing Bug**: What is the specific context persistence bug? (Not saving, not loading, or data corruption?) [REQUIRES USER CLARIFICATION]
3. **User Identification**: How are anonymous users tracked for context? (Session ID, browser fingerprint, or generate temporary user ID?) [NEEDS CLARIFICATION]
4. **Context Scope**: Should context include only current session or also previous purchase history/browsing behavior? [NEEDS CLARIFICATION]
5. **Retention Policy**: Is 90-day retention acceptable or should it be configurable per user type (customer vs admin)? [NEEDS CLARIFICATION]

## Technical Notes *(optional)*

### Current System Architecture

Based on codebase analysis:
- Customer chatbot uses `CustomerContextManager` service
- Admin assistant uses similar context management
- Vector embeddings stored in MongoDB `product_embeddings` collection
- Likely conversation storage in MongoDB for consistency

### Investigation Areas

1. Search for conversation persistence logic in:
   - `src/Application/Service/CustomerContextManager.php`
   - `src/Application/Service/*Context*.php`
   - `src/Infrastructure/Repository/*Conversation*.php`

2. Locate system prompts:
   - Search for Spanish strings in `src/Infrastructure/AI/`
   - Check for prompt configuration in `config/packages/ai*.yaml`
   - Review tool descriptions in `src/Infrastructure/AI/Tool/`

3. Database inspection:
   - MongoDB: `db.conversations.findOne()` or similar collection
   - MySQL: `SELECT * FROM conversations LIMIT 1` or similar table

### Recommended Approach

1. **Phase 1**: Investigate & Document
   - Locate conversation storage (MongoDB/MySQL)
   - Document schema and queries
   - Identify existing persistence logic and bugs

2. **Phase 2**: Fix Context Persistence
   - Implement or fix save logic
   - Implement or fix load logic
   - Add error handling and logging
   - Test cross-session context retrieval

3. **Phase 3**: English Conversion
   - Identify all Spanish system prompts
   - Translate to professional English
   - Update tool descriptions
   - Validate grammar and tone

4. **Phase 4**: Testing & Validation
   - Test customer chatbot in English
   - Test admin assistant in English
   - Verify context persists across sessions
   - Validate success criteria

## Related Features *(optional)*

- **Feature 007**: Admin Virtual Assistant (foundation for admin English conversion)
- **Feature 008**: Admin Assistant Enhancements (related to admin functionality)
- **Feature 009**: Context Memory (if exists - directly related to context persistence)
- **Feature 010**: Semantic Search (uses MongoDB for embeddings, potential conversation storage location)
