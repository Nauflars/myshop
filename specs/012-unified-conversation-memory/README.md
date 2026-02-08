# Especificación 012: Arquitectura Unificada de Conversaciones y Memoria

**Estado**: Documentación arquitectónica  
**Tipo**: Especificación técnica de referencia  
**Fecha**: 2026-02-08

## Resumen

Esta especificación documenta la arquitectura unificada de conversaciones y memoria para los asistentes virtuales (Cliente y Admin) basados en Symfony AI Agent, con Redis como almacenamiento centralizado de contexto.

## Propósito

Definir el modelo canónico de:
- Persistencia de contexto conversacional entre llamadas
- Separación lógica por rol (Cliente/Admin)
- Ciclo de vida del contexto (TTL, eliminación)
- Construcción del MessageBag para Symfony AI
- Uso de embeddings complementarios

## Relación con otras especificaciones

- **spec-003**: Persistencia de conversaciones en MySQL (implementado)
- **spec-009**: Sistema de contexto conversacional en Redis (implementado)
- **spec-010**: Búsqueda semántica con embeddings (implementado)

## Estado de implementación

✅ **Parcialmente implementado**

La arquitectura descrita está **funcionando** en el proyecto actual, pero con algunas diferencias de naming y estructura:

### Implementado
- Contexto en Redis con TTL (30 min)
- Separación Cliente/Admin
- Auto-renovación de TTL
- Conversaciones completas en MySQL
- Embeddings para búsqueda

### Diferencias
- Claves Redis: `chat:customer:{userId}` vs `conversation:{id}:*` (especificación)
- Historial completo en MySQL, no en Redis
- No se usa patrón `history/state/meta` separado

## Archivos clave

**Implementaciones actuales**:
- `src/Infrastructure/Repository/RedisContextStorage.php`
- `src/Application/Service/CustomerContextManager.php`
- `src/Application/Service/AdminContextManager.php`
- `src/Domain/ValueObject/CustomerConversationContext.php`
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
