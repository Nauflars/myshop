# Tasks: spec-012 - Arquitectura Unificada de Conversaciones

**Branch**: `012-unified-conversation-memory`  
**Estado**: En implementación  
**Fecha inicio**: 2026-02-08

---

## Phase 0: Preparación

- [x] T001: Crear especificación y documentación
- [x] T002: Crear rama de feature
- [x] T003: Analizar impacto en código existente
- [ ] T004: Crear plan de migración de datos

---

## Phase 1: Nuevo Modelo de Storage en Redis

### T005: Implementar UnifiedConversationStorage
- [x] Crear `UnifiedConversationStorage.php` en `src/Infrastructure/Repository/`
- [x] Implementar claves separadas: `history`, `state`, `meta`
- [x] Patrón de claves: `conversation:{role}:{userId}:{uuid}:*`
- [x] Métodos: `setHistory()`, `getHistory()`, `setState()`, `getState()`, `setMeta()`, `getMeta()`

### T006: Implementar gestión de historial FIFO
- [x] Método `addMessageToHistory(role, content)` con límite de 10 mensajes
- [x] Eliminar mensajes más antiguos automáticamente
- [x] Excluir mensajes técnicos (solo user/assistant)

### T007: Implementar metadata tracking
- [x] Guardar `role`, `created_at`, `last_activity` en clave `meta`
- [x] Auto-actualizar `last_activity` en cada interacción
- [x] Registrar servicio en `services.yaml`

---

## Phase 2: Refactorizar Context Managers

### T008: Refactorizar CustomerContextManager
- [x] Migrar a `UnifiedConversationStorage`
- [x] Usar nuevas claves Redis
- [x] Mantener compatibilidad con código existente
- [x] Separar historial en Redis (últimos 10) vs MySQL (completo)

### T009: Refactorizar AdminContextManager
- [x] Migrar a `UnifiedConversationStorage`
- [x] Usar patrón `conversation:admin:{userId}:{uuid}:*`
- [x] Actualizar métodos de persistencia

### T010: Crear interfaz común UnifiedContextInterface
- [x] Definir contrato común para ambos asistentes (implementado en managers)
- [x] Métodos: `getHistory()`, `getState()`, `updateState()`, `addMessage()`

---

## Phase 3: Actualizar Controllers

### T011: Actualizar ChatbotController
- [x] Cargar historial desde Redis (últimos 10 mensajes)
- [x] Construir MessageBag con orden correcto:
  - System: estado estructurado
  - Historial corto (Redis)
  - Mensaje actual
- [x] Persistir mensaje user + assistant en Redis
- [x] Aplicar límite FIFO

### T012: Actualizar AdminAssistantController
- [x] Implementar mismo patrón que ChatbotController
- [x] Cargar contexto admin desde Redis con nuevo formato
- [x] Actualizar historial en cada interacción

---

## Phase 4: Migración de Datos

### T013: Script de migración de contextos existentes
- [ ] Crear comando Symfony `app:migrate:context-to-unified`
- [ ] Migrar claves `chat:customer:{userId}` → `conversation:client:{userId}:{uuid}:*`
- [ ] Migrar claves `admin:context:{adminId}` → `conversation:admin:{userId}:{uuid}:*`
- [ ] Generar UUIDs para conversaciones existentes

### T014: Migrar historial de MySQL a Redis
- [ ] Cargar últimos 10 mensajes de `conversation_messages` por conversación
- [ ] Guardar en clave `conversation:{id}:history`
- [ ] Solo para conversaciones activas (< 30 días)

---

## Phase 5: Testing

### T015: Tests unitarios UnifiedConversationStorage
- [x] Test FIFO (11 mensajes → solo últimos 10)
- [x] Test separación history/state/meta
- [x] Test TTL refresh
- [x] Test eliminación manual

### T016: Tests integración CustomerContextManager
- [x] Test carga de historial
- [x] Test actualización de estado
- [x] Test construcción de MessageBag

### T017: Tests integración AdminContextManager
- [ ] Test flujos multi-step
- [ ] Test persistencia de contexto admin
- [ ] Test separación de contextos entre usuarios

### T018: Tests E2E conversaciones
- [ ] Test: 3 mensajes → historial correcto en Redis
- [ ] Test: 15 mensajes → solo últimos 10 en Redis
- [ ] Test: TTL se renueva en cada interacción
- [ ] Test: Eliminación manual limpia todas las claves

---

## Phase 6: Documentación

### T019: Actualizar documentación de desarrollo
- [x] Documentar nuevo modelo de claves Redis
- [x] Explicar diferencia history (Redis) vs conversaciones (MySQL)
- [x] Diagramas de flujo actualizados

### T020: Guía de migración para desarrolladores
- [x] Cómo acceder al historial
- [x] Cómo actualizar el estado
- [x] Breaking changes y compatibilidad

---

## Phase 7: Deployment

### T021: Plan de rollout gradual
- [ ] Feature flag para habilitar nuevo modelo
- [ ] Monitoreo de Redis (uso de memoria)
- [ ] Métricas de rendimiento

### T022: Cleanup de código legacy
- [ ] Eliminar `RedisContextStorage` antiguo (después de validación)
- [ ] Eliminar claves Redis viejas después de migración
- [ ] Actualizar configuración en `services.yaml`

---

## Notas

**Cambio principal**: 
- Redis pasa de guardar solo "estado" a guardar "historial corto + estado + meta"
- MySQL sigue siendo la fuente de verdad para historial completo

**Ventajas**:
- Consistencia entre asistentes Cliente y Admin
- Menor carga de MySQL en cada llamada al agente
- Historial en Redis permite respuestas más rápidas
- Modelo escalable y estándar

**Compatibilidad**:
- MySQL no cambia (zero breaking changes en persistencia)
- Controllers necesitan pequeños ajustes
- Tools no se ven afectados
