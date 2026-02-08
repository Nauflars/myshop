# Índice de Especificaciones del Proyecto

Este documento lista todas las especificaciones técnicas y funcionales del proyecto MyShop.

## Especificaciones Implementadas

### spec-003: Sistema de Persistencia de Conversaciones
- **Estado**: ✅ Implementado
- **Descripción**: Persistencia de conversaciones de chatbot en MySQL
- **Tablas**: `conversations`, `conversation_messages`
- **Ubicación**: Base de datos MySQL

### spec-004: Enhanced Chatbot UX
- **Estado**: ✅ Implementado
- **Descripción**: Mejoras de experiencia de usuario en el chatbot
- **Ubicación**: `specs/004-enhanced-chatbot-ux/`

### spec-005: Chat Message UI
- **Estado**: ✅ Implementado
- **Descripción**: Interfaz de usuario para mensajes de chat
- **Ubicación**: `specs/005-chat-message-ui/`

### spec-006: Unanswered Questions Admin
- **Estado**: ✅ Implementado
- **Descripción**: Panel de administración para preguntas sin respuesta
- **Ubicación**: `specs/006-unanswered-questions-admin/`
- **Archivos**: `IMPLEMENTATION.md`, `spec.md`

### spec-007: Admin Virtual Assistant
- **Estado**: ✅ Implementado
- **Descripción**: Asistente virtual para administradores
- **Ubicación**: `specs/007-admin-virtual-assistant/`
- **Tablas**: `admin_assistant_conversations`, `admin_assistant_messages`, `admin_assistant_actions`
- **Features**:
  - Gestión de productos conversacional
  - Gestión de inventario
  - Análisis de ventas
  - Auditoría de acciones

### spec-008: Admin Assistant Enhancements
- **Estado**: ✅ Implementado
- **Descripción**: Mejoras al asistente de administración
- **Ubicación**: `specs/008-admin-assistant-enhancements/`
- **Features**:
  - Detección de productos con stock bajo
  - Actualización de stock (set/add/subtract)
  - Contexto conversacional mejorado

### spec-009: Context Memory
- **Estado**: ✅ Implementado
- **Descripción**: Sistema de contexto conversacional y memoria
- **Ubicación**: `specs/009-context-memory/`
- **Tecnología**: Redis (TTL 30 minutos)
- **Features**:
  - Contexto de cliente (`CustomerConversationContext`)
  - Contexto de admin (`AdminConversationContext`)
  - Auto-renovación de TTL
  - Gestión de estados conversacionales

### spec-010: Semantic Search
- **Estado**: ✅ Implementado
- **Descripción**: Búsqueda semántica con embeddings de OpenAI
- **Ubicación**: `specs/010-semantic-search/`
- **Tecnología**: OpenAI text-embedding-3-small + MongoDB vector search
- **Features**:
  - Embeddings automáticos al crear/actualizar productos
  - Búsqueda por similitud semántica
  - Caché de embeddings en Redis (TTL 1 hora)
  - Métricas de performance y costos
- **Documentación**:
  - [Developer Guide](specs/010-semantic-search/docs/DEVELOPER_GUIDE.md)
  - [Admin Guide](specs/010-semantic-search/docs/ADMIN_GUIDE.md)
  - [API Documentation](specs/010-semantic-search/docs/API.md)
  - [Cost Estimation](specs/010-semantic-search/docs/COST_ESTIMATION.md)

### spec-011: English AI Assistants
- **Estado**: ✅ Implementado
- **Descripción**: Migración de asistentes virtuales a inglés
- **Ubicación**: `specs/011-english-ai-assistants/`
- **Cambios**:
  - Todos los prompts de IA en inglés
  - Respuestas del asistente en inglés
  - Documentación actualizada

---

## Especificaciones de Referencia

### spec-012: Arquitectura Unificada de Conversaciones y Memoria ⭐ NUEVO
- **Estado**: 📚 Documentación de referencia
- **Tipo**: Especificación técnica arquitectónica
- **Descripción**: Documenta la arquitectura unificada para gestión de conversaciones y memoria entre asistentes Cliente y Admin
- **Ubicación**: `specs/012-unified-conversation-memory/`
- **Propósito**: Modelo canónico para:
  - Persistencia de contexto conversacional en Redis
  - Separación lógica por rol (Cliente/Admin)
  - Ciclo de vida del contexto (TTL, expiración)
  - Construcción del MessageBag para Symfony AI
  - Uso de embeddings complementarios
- **Relación**: Documenta arquitectura implementada en spec-003 y spec-009
- **Nota**: Esta especificación define el modelo **ideal** de referencia. La implementación actual funciona correctamente con ligeras variaciones arquitectónicas.

---

## Estructura de una Especificación

Cada especificación típicamente contiene:

```
specs/NNN-feature-name/
├── spec.md              # Especificación completa
├── README.md            # Resumen y estado
├── IMPLEMENTATION.md    # Notas de implementación (opcional)
├── tasks.md             # Lista de tareas (opcional)
└── checklists/          # Checklists de validación
    └── *.md
```

---

## Convenciones

- **Numeración**: 3 dígitos (001, 002, 003...)
- **Naming**: `NNN-short-kebab-case-name`
- **Estado**: 
  - ✅ Implementado
  - 🚧 En progreso
  - 📚 Documentación de referencia
  - ⏸️ Pausado
  - ❌ Cancelado

---

## Crear Nueva Especificación

Para crear una nueva especificación, usa el comando speckit:

```bash
/speckit.specify "Descripción breve de la feature"
```

Esto creará:
1. Branch de feature: `NNN-short-name`
2. Directorio: `specs/NNN-short-name/`
3. Archivo: `specs/NNN-short-name/spec.md`
4. Checklist inicial

---

## Referencias Cruzadas

- **Conversaciones**: spec-003 (MySQL) + spec-009 (Redis) + spec-012 (Arquitectura)
- **Asistentes IA**: spec-007 (Admin) + spec-008 (Mejoras) + spec-011 (Inglés)
- **Búsqueda**: spec-010 (Semántica)
- **UI**: spec-004 (Chatbot UX) + spec-005 (Mensajes UI)
- **Admin**: spec-006 (Questions) + spec-007 (Assistant) + spec-008 (Enhancements)
