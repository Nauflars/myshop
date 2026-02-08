# Especificación 012: Arquitectura Unificada de Conversaciones y Memoria

**Estado**: ✅ IMPLEMENTADO  
**Tipo**: Especificación técnica de referencia  
**Fecha**: 2026-02-08  
**Branch**: `012-unified-conversation-memory`  
**Commits**: 6d0953d, f7e76cf

## Resumen

Esta especificación documenta la arquitectura unificada de conversaciones y memoria para los asistentes virtuales (Cliente y Admin) basados en Symfony AI Agent, con Redis como almacenamiento centralizado de contexto.

**IMPORTANTE**: Esta especificación ha sido **implementada completamente** y reemplaza la arquitectura anterior de spec-009.

## Propósito

Define el modelo canónico de:
- ✅ Persistencia de contexto conversacional entre llamadas
- ✅ Separación lógica por rol (Cliente/Admin)
- ✅ Gestión de historial FIFO (últimos 10 mensajes en Redis)
- ✅ Ciclo de vida del contexto (TTL, eliminación)
- ✅ Construcción del MessageBag para Symfony AI
- ✅ Metadata tracking (created_at, last_activity)

## Relación con otras especificaciones

- **spec-003**: Persistencia de conversaciones en MySQL (complementario)
- **spec-009**: Sistema de contexto conversacional en Redis (REEMPLAZADO por spec-012)
- **spec-010**: Búsqueda semántica con embeddings (complementario)

## Estado de implementación

✅ **COMPLETAMENTE IMPLEMENTADO**

### Implementado (Febrero 2026)

#### Phase 1: Storage Layer
- ✅ `UnifiedConversationStorage.php` - Redis storage con claves separadas
- ✅ Patrón de claves: `conversation:{role}:{userId}:{uuid}:{history|state|meta}`
- ✅ FIFO automático (max 10 mensajes)
- ✅ TTL management (30 minutos, auto-refresh)
- ✅ Metadata tracking

#### Phase 2: Context Managers
- ✅ `UnifiedCustomerContextManager.php` - Manager para cliente
- ✅ `UnifiedAdminContextManager.php` - Manager para admin
- ✅ API unificada: `getOrCreateConversation()`, `addMessage()`, `updateState()`
- ✅ MessageBag construction con state + history

#### Phase 3: Controller Integration
- ✅ `ChatbotController` actualizado para usar UnifiedCustomerContextManager
- ✅ `AdminAssistantController` actualizado para usar UnifiedAdminContextManager
- ✅ Historial de Redis integrado en MessageBag
- ✅ State updates automáticos después de cada interacción

#### Phase 4: Tests
- ✅ Unit tests: `UnifiedConversationStorageTest.php` (8 test methods)
- ✅ Integration tests: `UnifiedCustomerContextManagerTest.php` (8 test methods)
- ✅ FIFO validation
- ✅ State management validation
- ✅ MessageBag construction validation

#### Phase 5: Documentation
- ✅ `developer-guide.md` - Guía completa de desarrollo
- ✅ `migration-guide.md` - Guía de deployment
- ✅ Architecture diagrams
- ✅ Code examples
- ✅ Troubleshooting guide

## Archivos clave

**Nuevas implementaciones (spec-012)**:
- `src/Infrastructure/Repository/UnifiedConversationStorage.php` (453 líneas)
- `src/Application/Service/UnifiedCustomerContextManager.php` (369 líneas)
- `src/Application/Service/UnifiedAdminContextManager.php` (319 líneas)
- `tests/Unit/Infrastructure/Repository/UnifiedConversationStorageTest.php`
- `tests/Integration/Application/Service/UnifiedCustomerContextManagerTest.php`

**Legacy (deprecadas, mantener por compatibilidad)**:
- `src/Infrastructure/Repository/RedisContextStorage.php` (a eliminar después de validación)
- `src/Application/Service/CustomerContextManager.php` (legacy)
- `src/Application/Service/AdminContextManager.php` (legacy)
- `src/Domain/ValueObject/AdminConversationContext.php`

**Configuración**:
- `config/packages/redis.yaml`
- `config/services.yaml` (TTL: `CONTEXT_TTL=1800`)

## Uso

Esta especificación sirve como:
1. **Referencia arquitectónica** para decisiones futuras
2. **Documentación** del modelo ideal de conversaciones
3. **Guía** para posibles refactorizaciones

## Nota importante

> Esta es una especificación de **arquitectura ideal** que documenta el modelo recomendado. La implementación actual funciona correctamente con ligeras variaciones de este modelo.

No requiere implementación inmediata, pero puede servir como base para mejoras futuras si se detectan problemas de escalabilidad o consistencia.
