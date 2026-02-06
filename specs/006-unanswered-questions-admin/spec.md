# Feature Specification: Unanswered Questions Tracking & Admin Panel

**Feature Branch**: `006-unanswered-questions-admin`  
**Created**: February 6, 2026  
**Status**: Draft  
**Input**: System for tracking chatbot knowledge gaps and administrative interface for managing products, users, and unanswered questions

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Automatic Unanswered Question Capture (Priority: P1)

When the AI chatbot cannot answer a user's question (missing tools, unsupported intent, or errors), the system automatically stores the question in the database for future analysis and improvement planning.

**Why this priority**: This is the foundation of the continuous improvement loop. Without capturing unanswered questions, we have no data-driven way to identify gaps in the assistant's capabilities.

**Independent Test**: Can be fully tested by asking the chatbot a question it cannot answer (e.g., "¿Cuál es el estado de mi envío?") and verifying the question appears in the admin panel's unanswered questions list with correct metadata.

**Acceptance Scenarios**:

1. **Given** user asks chatbot a question outside its capabilities, **When** agent determines no tool can answer it, **Then** system stores question with: text, user ID, role, timestamp, reason "missing tool", status "New"
2. **Given** chatbot tool execution fails, **When** no alternative resolution exists, **Then** system stores question with reason "tool error" and responds politely to user
3. **Given** anonymous user asks unanswerable question, **When** stored in database, **Then** user ID is null but question text and timestamp are captured
4. **Given** question is stored, **When** user sees chatbot response, **Then** response is polite Spanish message without technical details

---

### User Story 2 - Admin Unanswered Questions Dashboard (Priority: P1)

Administrators can view, review, and manage all unanswered questions captured by the chatbot, enabling them to plan new features and tools based on real user needs.

**Why this priority**: Without visibility into unanswered questions, capturing them has no value. Admins need immediate access to this data to drive improvement decisions.

**Independent Test**: Can be fully tested by logging in as admin, navigating to "Preguntas sin respuesta" section, and verifying list displays all captured questions with ability to filter by status and add notes.

**Acceptance Scenarios**:

1. **Given** admin accesses unanswered questions page, **When** viewing list, **Then** sees: question text, user (if available), role, date/time, reason category, current status
2. **Given** admin selects a question, **When** marks as "Reviewed", **Then** status updates and date reviewed is recorded
3. **Given** admin reviews question, **When** adds internal notes, **Then** notes are saved and visible only to admins
4. **Given** admin plans to address question, **When** changes status to "Planned", **Then** can optionally link to specification or feature reference
5. **Given** tool is implemented for question type, **When** admin marks related questions as "Resolved", **Then** questions show resolved status with resolution date

---

### User Story 3 - Admin Product Management (Priority: P2)

Administrators can view, create, edit, and delete products through an admin interface, providing full control over the product catalog without needing database access.

**Why this priority**: Important for day-to-day operations but not critical for MVP. Current products can be managed via database migrations initially.

**Independent Test**: Can be fully tested by logging in as admin, navigating to "Productos" section, creating a new product, verifying it appears in customer-facing product list.

**Acceptance Scenarios**:

1. **Given** admin accesses product management, **When** viewing list, **Then** sees all products with: name, price, stock, category, availability status with sorting and filtering
2. **Given** admin clicks "Crear producto", **When** fills form (name, price, stock, description, category), **Then** product is created and appears in customer catalog
3. **Given** admin selects existing product, **When** edits price or stock, **Then** changes save and reflect immediately in chatbot responses
4. **Given** admin selects product, **When** clicks delete with confirmation, **Then** product is removed from catalog (or marked inactive)

---

### User Story 4 - Admin User Management & Insights (Priority: P2)

Administrators can view all registered users with purchase statistics and basic account information, providing visibility into customer behavior and account status.

**Why this priority**: Useful for customer support and business insights but not essential for core chatbot improvement loop.

**Independent Test**: Can be fully tested by logging in as admin, navigating to "Usuarios" section, and verifying list shows all users with accurate order counts and registration dates.

**Acceptance Scenarios**:

1. **Given** admin accesses user management, **When** viewing list, **Then** sees: name, email, role, number of completed orders, registration date
2. **Given** admin views user details, **When** checking purchase history, **Then** sees aggregate order count (read-only, no modification allowed)
3. **Given** admin needs to find user, **When** searches by email or name, **Then** list filters to matching users

---

### User Story 5 - Role-Based Admin Access Control (Priority: P1)

Only users with ADMIN role can access administrative sections (products, users, unanswered questions), ensuring regular customers cannot view or modify sensitive data.

**Why this priority**: Critical security requirement. Without proper access control, sensitive admin features would be exposed to all users.

**Independent Test**: Can be fully tested by attempting to access admin URLs as regular user (should redirect or show 403), then accessing as admin user (should work).

**Acceptance Scenarios**:

1. **Given** regular user tries to access `/admin/products`, **When** request is made, **Then** system denies access with 403 or redirects to homepage
2. **Given** admin user accesses admin section, **When** navigating between admin pages, **Then** all features are accessible
3. **Given** user interface renders for admin, **When** admin is logged in, **Then** navigation menu shows admin-only links (Productos, Usuarios, Preguntas sin respuesta)
4. **Given** user interface renders for customer, **When** regular user is logged in, **Then** admin links do not appear in navigation

---

### User Story 6 - Future Tool Resolution Linking (Priority: P3)

When new AI tools are implemented, administrators can mark related unanswered questions as "Resolved" and optionally link to the tool that addresses them, closing the feedback loop.

**Why this priority**: Nice-to-have for tracking improvements over time, but manual resolution marking (P2) is sufficient initially.

**Independent Test**: Can be fully tested by implementing a new AI tool, having admin mark old unanswered questions as resolved with reference to new tool, verifying questions show resolved status.

**Acceptance Scenarios**:

1. **Given** new tool is deployed, **When** admin reviews related unanswered questions, **Then** can bulk-select and mark as "Resolved" with resolution note
2. **Given** admin marks question as resolved, **When** adding resolution details, **Then** can reference tool name or specification that addresses it
3. **Given** questions are resolved, **When** viewing analytics, **Then** admin sees metrics on resolution rate and time-to-resolution

---

### Edge Cases

- What happens when same question is asked multiple times by different users? → Store each occurrence separately with different user context; admin can see duplicates and identify high-priority issues
- How to handle questions that are partially answerable? → Store as unanswered if agent explicitly states limitations; threshold is agent's determination
- What if admin accidentally marks question as resolved prematurely? → Allow status changes backward (resolved → planned → reviewed → new) with audit trail
- How to prevent admin panel from performance issues with thousands of questions? → Implement pagination (50 per page), filters by status/date, and database indexes
- What if question contains sensitive user data (passwords, credit cards)? → Sanitize question text before storage; detect and redact patterns matching sensitive data formats

## Requirements *(mandatory)*

### Functional Requirements

#### Unanswered Question Tracking

- **FR-001**: System MUST automatically detect when AI agent cannot answer a question (missing tools, unsupported intent, tool errors)
- **FR-002**: System MUST store unanswered questions with: question text, user ID (nullable), user role, timestamp, reason category, status
- **FR-003**: System MUST preserve original question text without modification (except sanitization of sensitive data)
- **FR-004**: System MUST categorize unanswered questions by reason: "missing_tool", "unsupported_request", "tool_error", "insufficient_data"
- **FR-005**: System MUST set initial status to "New" for all captured questions
- **FR-006**: Chatbot MUST respond politely in Spanish when cannot answer, without exposing technical details
- **FR-007**: System MUST NOT automatically delete any unanswered questions (permanent storage)
- **FR-008**: System MUST capture conversation context reference (conversation ID) with each unanswered question

#### Admin Role & Access Control

- **FR-009**: System MUST support ADMIN role distinct from regular USER role
- **FR-010**: System MUST restrict access to admin routes (/admin/*) to users with ADMIN role only
- **FR-011**: System MUST display admin navigation menu only to authenticated admin users
- **FR-012**: System MUST return 403 Forbidden or redirect when non-admin attempts to access admin pages

#### Admin Unanswered Questions Management

- **FR-013**: Admin panel MUST display list of all unanswered questions with columns: question text, user, role, date, reason, status
- **FR-014**: Admin MUST be able to change question status to: Reviewed, Planned, Resolved
- **FR-015**: Admin MUST be able to add internal notes to any question (visible only to admins)
- **FR-016**: Admin MUST be able to filter questions by status and date range
- **FR-017**: System MUST support pagination for unanswered questions list (50 per page default)
- **FR-018**: (P3 - Optional) Admin MAY link questions to future specifications or implemented tools

#### Admin Product Management

- **FR-019**: Admin panel MUST display list of all products with: name, price, stock, category, availability
- **FR-020**: Admin MUST be able to create new product with required fields: name, price (with currency), description, stock
- **FR-021**: Admin MUST be able to edit existing product details (name, price, stock, description, category)
- **FR-022**: Admin MUST be able to delete product with confirmation dialog
- **FR-023**: Product list MUST support sorting by name, price, stock, category
- **FR-024**: Product list MUST support filtering by category and availability status
- **FR-025**: System MUST validate all product data (positive price, non-negative stock, required fields)

#### Admin User Management

- **FR-026**: Admin panel MUST display list of all registered users with: name, email, role, order count, registration date
- **FR-027**: System MUST calculate total number of completed orders per user (read-only aggregate)
- **FR-028**: Admin MUST be able to view user details including order history summary
- **FR-029**: Admin MUST NOT be able to directly modify user orders from user management view
- **FR-030**: User list MUST support search by email and name

### Key Entities

- **UnansweredQuestion**: Represents a chatbot question that could not be answered with attributes: id, questionText, user (nullable), userRole, askedAt (timestamp), conversationId, reasonCategory (enum: missing_tool, unsupported_request, tool_error, insufficient_data), status (enum: new, reviewed, planned, resolved), adminNotes (text), reviewedAt, resolvedAt
- **AdminUser**: User with ADMIN role, inherits from User entity with elevated privileges
- **Product** (existing): Extended with admin management capabilities - adminCanEdit, adminCanDelete flags
- **User** (existing): Extended with aggregate orderCount for admin insights

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of unanswerable chatbot questions are captured and stored in database within 500ms of detection
- **SC-002**: Administrators can identify top 10 most common unanswered question patterns within 5 minutes of accessing admin panel
- **SC-003**: Time from identifying unanswered question to marking as "Planned" reduces to under 2 days for priority issues
- **SC-004**: Admin can create, edit, or delete a product in under 30 seconds (measured from navigation to save confirmation)
- **SC-005**: System supports at least 1000 unanswered questions without performance degradation in admin list view (load time < 2 seconds)
- **SC-006**: Zero unauthorized access attempts succeed to admin panel (100% blocked non-admin users)
- **SC-007**: Within first month of deployment, at least 20% of captured unanswered questions are marked as "Reviewed" or "Planned"

## Assumptions

- Admin users are trusted internal staff, not end customers
- Number of admin users is small (typically 1-5)
- Unanswered questions are primarily used for planning, not real-time customer support
- Administrators will manually review questions periodically (not automated analysis)
- Product catalog size is manageable (< 1000 products initially)
- Admin interface is web-based using existing Twig template system
- Spanish is primary language for chatbot responses and admin interface labels
- Security relies on Symfony's built-in role-based access control
- Database can efficiently handle thousands of unanswered questions over time
- Initial implementation focuses on visibility; bulk operations and analytics are future enhancements

## Dependencies

- Existing Symfony security system with role-based access control
- Existing chatbot and AI agent infrastructure from spec-003/004
- Conversation persistence system with conversation IDs from spec-003
- User authentication system with role support
- Product entity and repository from spec-001
- Doctrine ORM for new UnansweredQuestion entity persistence
- Twig templating for admin UI pages

## Out of Scope

- Real-time notifications when new unanswered questions arrive
- Automated question analysis or categorization using ML/AI
- Public-facing FAQ generation from unanswered questions
- Email alerts to admins about critical unanswered questions
- Integration with external ticketing or support systems
- Natural language processing to detect question sentiment or urgency
- Bulk import/export of unanswered questions
- Advanced analytics dashboard with charts and trends
- Multi-language support for admin interface (Spanish only)
- Customer self-service portal to track status of their questions
- Automated tool suggestion based on question analysis
- Version control or history tracking for product changes
- User account suspension or deletion from admin panel
- Role management interface (admin roles assigned via database/config only)
- Audit logging for all admin actions (future security enhancement)
