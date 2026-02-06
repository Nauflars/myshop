# Feature Specification: Chat Assistant Improvements

## Feature Name
Mejoras de Persistencia y Seguridad del Asistente de Chat

## Feature ID
003-chat-improvements

## Priority
P1 (Alta - mejoras críticas de UX y seguridad)

## Overview

Mejorar el asistente de chat conversacional existente (implementado en spec-002) para:
1. Eliminar la necesidad de IDs de usuario explícitos en operaciones (usar contexto de autenticación)
2. Implementar persistencia de conversaciones entre sesiones
3. Permitir al usuario limpiar el historial de conversación
4. Agregar funcionalidades específicas para usuarios administradores
5. Mejorar la visualización del carrito y precio total

## Business Value

### Problemas Actuales
- **UX deficiente**: Usuario tiene que proporcionar su ID para añadir al carrito (antinatural)
- **Sin memoria**: Cada vez que el usuario recarga la página, pierde el contexto de la conversación
- **Sin control**: Usuario no puede limpiar el historial cuando quiere empezar de nuevo
- **Admin limitado**: Administradores no tienen acceso a estadísticas vía chatbot
- **Inseguro**: Exponer IDs de usuario es un riesgo de seguridad

### Beneficios Esperados
- ✅ **Conversaciones más naturales**: Usuario autenticado no necesita proporcionar datos que el sistema ya conoce
- ✅ **Continuidad**: Usuario puede retomar conversaciones anteriores
- ✅ **Control del usuario**: Puede limpiar el chat cuando desee
- ✅ **Admin eficiente**: Acceso rápido a métricas de negocio
- ✅ **Seguridad mejorada**: No se exponen IDs internos

## Target Users

1. **Clientes autenticados**: Principales usuarios del chatbot
2. **Administradores**: Usuarios con rol ROLE_ADMIN que necesitan estadísticas
3. **Desarrolladores**: Necesitan API clara y segura

## User Stories

### US1: Añadir al Carrito sin ID de Usuario (P1)

**Como** cliente autenticado  
**Quiero** añadir productos al carrito sin tener que proporcionar mi ID  
**Para que** la experiencia sea más natural y segura

**Acceptance Criteria:**
- ✅ AC1.1: Usuario autenticado puede decir "añade iPhone 15 al carrito" sin proporcionar userId
- ✅ AC1.2: El sistema usa `Security::getUser()` para identificar al usuario
- ✅ AC1.3: Si el usuario no está autenticado, el chatbot responde "Debes iniciar sesión para añadir productos"
- ✅ AC1.4: Confirmación incluye nombre del producto y cantidad añadida
- ✅ AC1.5: No se exponen IDs internos (ni de usuario ni de producto) en respuestas

**Technical Notes:**
- Actualizar `AddToCartTool` para inyectar `Security` service
- Quitar parámetro `userId` de la firma del método
- Validar `$security->getUser()` antes de ejecutar

---

### US2: Ver Carrito y Precio Total (P1)

**Como** cliente autenticado  
**Quiero** preguntar "¿qué tengo en mi carrito?" y ver el precio total  
**Para que** pueda revisar mi compra antes de finalizar

**Acceptance Criteria:**
- ✅ AC2.1: Usuario puede preguntar "muéstrame mi carrito" o "¿cuánto cuesta mi carrito?"
- ✅ AC2.2: Respuesta incluye lista de productos con nombres, cantidades y precios individuales
- ✅ AC2.3: Respuesta incluye precio total formateado (ej: "$149.99 USD")
- ✅ AC2.4: Si el carrito está vacío, responde "Tu carrito está vacío"
- ✅ AC2.5: No se exponen IDs de productos o carrito

**Technical Notes:**
- Tool ya existe: `GetCartSummaryTool` (implementado en spec-002 Fase 15)
- Verificar que devuelve todos los campos necesarios
- Asegurar formateo correcto de precios en español

---

### US3: Persistencia de Conversaciones (P1)

**Como** cliente autenticado  
**Quiero** que mis conversaciones con el chatbot persistan entre sesiones  
**Para que** pueda continuar donde lo dejé sin perder contexto

**Acceptance Criteria:**
- ✅ AC3.1: Mensajes del usuario y del asistente se guardan automáticamente en la base de datos
- ✅ AC3.2: Al recargar la página, el usuario ve los últimos N mensajes de su conversación activa (ej: últimos 20)
- ✅ AC3.3: El contexto anterior es visible y el asistente lo tiene en cuenta en nuevas respuestas
- ✅ AC3.4: Cada conversación tiene un ID único guardado en localStorage del navegador
- ✅ AC3.5: Si el usuario cierra sesión, no puede ver conversaciones de otros usuarios

**Technical Notes:**
- Crear entidades `Conversation` y `ConversationMessage`
- Migración de base de datos para nuevas tablas
- `ChatbotController` debe guardar cada intercambio después de la respuesta del agente
- Frontend (`chatbot.js`) debe cargar historial al iniciar

---

### US4: Limpiar Historial de Conversación (P1)

**Como** cliente autenticado  
**Quiero** poder limpiar o reiniciar mi conversación con el chatbot  
**Para que** pueda empezar de nuevo cuando el contexto ya no sea relevante

**Acceptance Criteria:**
- ✅ AC4.1: Existe un botón visible "🗑️ Limpiar chat" en el widget del chatbot
- ✅ AC4.2: Al hacer clic, todos los mensajes visibles se eliminan de la interfaz
- ✅ AC4.3: La conversación actual se marca como archivada o se elimina en la base de datos
- ✅ AC4.4: Se crea una nueva conversación (nuevo ID) para mensajes futuros
- ✅ AC4.5: El asistente responde "He limpiado nuestro historial. ¿En qué puedo ayudarte hoy?"

**Alternate Flow:**
- Usuario puede decir "limpia el chat" o "borra la conversación" y el asistente ejecuta la acción

**Technical Notes:**
- Crear `ClearConversationTool` 
- Agregar botón en `templates/chatbot/widget.html.twig`
- JavaScript debe llamar a endpoint o usar tool directamente
- Generar nuevo `conversationId` y guardarlo en localStorage

---

### US5: Estadísticas para Administradores (P2)

**Como** administrador del sistema  
**Quiero** preguntar al chatbot por estadísticas clave del negocio  
**Para que** pueda monitorear el rendimiento sin salir del chat

**Acceptance Criteria:**
- ✅ AC5.1: Usuario con rol ROLE_ADMIN puede preguntar "¿cuáles son las estadísticas de ventas?"
- ✅ AC5.2: Respuesta incluye: total de ventas del mes, productos más vendidos, usuarios activos, órdenes pendientes
- ✅ AC5.3: Si un usuario no-admin pregunta por estadísticas, recibe "No tienes permisos para ver esta información"
- ✅ AC5.4: Datos formateados en español de forma legible
- ✅ AC5.5: Estadísticas se cachean por 5 minutos para optimizar rendimiento

**Technical Notes:**
- Crear `GetAdminStatsUseCase` que consulte repositorios de Order, Product, User
- Crear `GetAdminStatsTool` con validación `$security->isGranted('ROLE_ADMIN')`
- Queries optimizadas con índices apropiados

---

### US6: Información del Usuario Actual (P2)

**Como** cliente autenticado  
**Quiero** preguntar "¿quién soy?" o "¿cuál es mi información?"  
**Para que** confirme con qué cuenta estoy trabajando

**Acceptance Criteria:**
- ✅ AC6.1: Usuario puede preguntar "¿cuál es mi información?" o "¿quién soy?"
- ✅ AC6.2: Respuesta incluye: nombre, email, rol (Cliente/Administrador), número de conversaciones guardadas
- ✅ AC6.3: No se expone el ID interno del usuario
- ✅ AC6.4: Si el usuario es admin, se indica claramente en la respuesta
- ✅ AC6.5: Respuesta en español con formato amigable

**Technical Notes:**
- Crear `GetUserInfoTool` que consulta `$security->getUser()`
- Contar conversaciones con `ConversationRepository->countByUser()`

---

### US7: Acceso Administrador (Documentación) (P3)

**Como** desarrollador o usuario nuevo  
**Quiero** saber cómo acceder con credenciales de administrador  
**Para que** pueda probar funcionalidades específicas de admin

**Acceptance Criteria:**
- ✅ AC7.1: Documentación incluye credenciales de admin en README.md
- ✅ AC7.2: Fixtures crean usuario admin si no existe
- ✅ AC7.3: Instrucciones claras sobre cómo iniciar sesión como admin
- ✅ AC7.4: Lista de funcionalidades exclusivas de admin en el chatbot

**Technical Notes:**
- Actualizar `src/DataFixtures/AppFixtures.php` para crear admin
- Documentar en README.md sección "Credenciales de Prueba"
- Credenciales por defecto: `admin@myshop.com` / `admin123`

---

## Non-Functional Requirements

### Performance
- **NFR1**: Guardar mensaje en DB debe tomar < 100ms
- **NFR2**: Cargar historial de conversación < 200ms
- **NFR3**: Estadísticas de admin con cache de 5 minutos

### Security
- **NFR4**: Todos los tools validan autenticación antes de ejecutar
- **NFR5**: GetAdminStatsTool verifica `isGranted('ROLE_ADMIN')`
- **NFR6**: No se exponen IDs internos (UUID) en respuestas del chatbot

### Usability
- **NFR7**: Mensajes de error en español y amigables
- **NFR8**: Botón "Limpiar chat" visible y accesible
- **NFR9**: Historial de conversación se carga automáticamente sin intervención del usuario

### Scalability
- **NFR10**: Limit de 50 mensajes por conversación en memoria (los más recientes)
- **NFR11**: Conversaciones antiguas (>30 días sin actividad) se archivan automáticamente

## Out of Scope (Future Iterations)

- ❌ Búsqueda de conversaciones antiguas por keywords
- ❌ Exportar conversación como PDF
- ❌ Compartir conversación con soporte técnico
- ❌ Conversaciones multi-usuario (soporte compartido)
- ❌ Mensajes con archivos adjuntos (imágenes de productos)

## Dependencies

### Internal
- **spec-002**: AI Shopping Assistant (debe estar implementado)
- Entidades existentes: User, Product, Cart, Order
- Security bundle configurado con roles

### External
- Ninguna librería nueva requerida

## Assumptions

1. Usuario siempre está autenticado cuando usa el chatbot (firewall protege /api/chat)
2. Conversaciones se guardan indefinidamente (archivado manual futuro)
3. Fixtures crean al menos 1 usuario admin para testing
4. Frontend usa localStorage para persistir conversationId

## Success Metrics

- ✅ **Métrica 1**: 0% de requests al chatbot incluyen userId explícito
- ✅ **Métrica 2**: 100% de conversaciones persisten entre sesiones
- ✅ **Métrica 3**: Usuarios pueden limpiar chat en < 2 clicks
- ✅ **Métrica 4**: Admins acceden a estadísticas en < 5 segundos
- ✅ **Métrica 5**: 0 exposiciones de IDs internos en respuestas

## Testing Strategy

### Unit Tests
- Use Cases: SaveConversation, LoadConversation, ClearConversation, GetAdminStats
- Entities: Conversation->generateTitle(), addMessage()

### Integration Tests
- ChatbotController: persistencia automática de mensajes
- Security: GetAdminStatsTool solo accesible por ROLE_ADMIN
- ConversationRepository: CRUD operations

### E2E Tests
- **Test 1**: Usuario añade producto → recarga página → continúa conversación
- **Test 2**: Usuario limpia chat → historial se borra → nueva conversación
- **Test 3**: Admin pregunta estadísticas → recibe datos
- **Test 4**: Usuario no-admin pregunta estadísticas → recibe error de permisos

## Documentation Requirements

1. **README.md**: Sección "Credenciales de Prueba" con admin credentials
2. **CONVERSATION_PERSISTENCE.md**: Explicación técnica de cómo funciona persistencia
3. **ADMIN_FEATURES.md**: Lista de funcionalidades exclusivas de admin
4. **API.md**: Actualizar endpoint `/api/chat` con parámetro `conversationId`

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-06 | System | Initial specification based on user requirements |
