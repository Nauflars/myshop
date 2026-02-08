# Especificación 012: Arquitectura Unificada de Conversaciones y Memoria

**Fecha de creación**: 2026-02-08  
**Estado**: Documentación (implementado en spec-009)  
**Relacionado con**: spec-003, spec-009

---

## 1. Objetivo

Definir una **arquitectura unificada de conversación y memoria** para dos asistentes (Cliente y Admin) basados en **Symfony AI Agent**, compartiendo una misma infraestructura de almacenamiento en **Redis**, con las siguientes garantías:

* Persistencia del contexto conversacional entre llamadas
* Separación lógica por rol (Cliente / Admin)
* Eliminación explícita del contexto por parte del usuario
* Expiración automática de memoria cuando no hay actividad
* Uso consistente del contexto en cada llamada al agente
* Robustez frente a conversaciones largas y uso de tools

---

## 2. Principios fundamentales

1. El agente **no tiene memoria implícita**: todo el contexto debe reconstruirse en cada llamada.
2. Redis es la **fuente de verdad del contexto conversacional**.
3. Los tools **no forman parte de la memoria**.
4. La conversación se modela como **estado + diálogo humano**.
5. Cliente y Admin comparten el mismo motor, pero **no el mismo contexto**.

---

## 3. Identidad de conversación

Cada conversación se identifica por:

```
conversation_id = {role}:{user_id}:{uuid}
```

Ejemplos:

* `client:42:abc123`
* `admin:1:def456`

Esto permite:

* Varias conversaciones activas por usuario
* Aislamiento total entre Cliente y Admin

---

## 4. Modelo de datos en Redis

### 4.1 Claves Redis

```
conversation:{conversation_id}:history
conversation:{conversation_id}:state
conversation:{conversation_id}:meta
```

Todas las claves comparten el mismo TTL.

---

### 4.2 Historial de conversación (history)

**Tipo:** Lista JSON

**Contenido:** solo mensajes humanos relevantes.

```json
[
  { "role": "user", "content": "Quiero ver zapatillas" },
  { "role": "assistant", "content": "¿Alguna marca en particular?" }
]
```

**Reglas:**

* Máx. 10 mensajes
* FIFO (se eliminan los más antiguos)
* No incluir tools ni mensajes técnicos

---

### 4.3 Estado estructurado (state)

**Tipo:** Hash / JSON

Contiene hechos derivados y datos confirmados.

#### Cliente

```json
{
  "current_category": "zapatillas",
  "cart_items": [12, 45],
  "checkout_step": "address"
}
```

#### Admin

```json
{
  "current_product": 123,
  "draft_product": {
    "name": "Camiseta",
    "price": 19.99
  }
}
```

**Reglas:**

* Guardar solo resultados
* Sobrescribir solo con confirmaciones explícitas

---

### 4.4 Metadata (meta)

```json
{
  "role": "client | admin",
  "created_at": "2026-02-08T10:00:00Z",
  "last_activity": "2026-02-08T10:12:00Z"
}
```

---

## 5. TTL y ciclo de vida

### 5.1 Expiración automática

* TTL recomendado: **30–60 minutos** desde la última interacción
* Cada mensaje renueva el TTL de todas las claves

### 5.2 Eliminación manual

Cuando el usuario ejecuta "borrar conversación":

* Eliminar todas las claves `conversation:{id}:*`
* Generar un nuevo `conversation_id`

---

## 6. Flujo de ejecución del agente

### 6.1 Recuperación de contexto

1. Cargar `history`
2. Cargar `state`
3. (Opcional) recuperar contexto semántico por embeddings

---

### 6.2 Construcción del MessageBag

Orden obligatorio:

1. System: rol del asistente
2. System: reglas del dominio (Cliente / Admin)
3. System: estado estructurado
4. Historial corto
5. Mensaje actual del usuario

```php
$messages = new MessageBag(
    Message::forSystem($rolePrompt),
    Message::forSystem('Estado actual: ' . json_encode($state)),
    ...$history,
    Message::forUser($input)
);
```

---

### 6.3 Ejecución y tools

* El agente decide usar tools
* El resultado se interpreta
* Solo la **conclusión** actualiza el estado

---

### 6.4 Persistencia tras respuesta

1. Añadir user + assistant a `history`
2. Aplicar límite FIFO
3. Actualizar `state` si procede
4. Renovar TTL

---

## 7. Embeddings (opcional pero recomendado)

### Uso

* Resúmenes de conversaciones largas
* Preferencias implícitas
* Decisiones pasadas

### Configuración

```
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
EMBEDDING_CACHE_TTL=3600
```

Embeddings **no sustituyen Redis**, solo complementan.

---

## 8. Qué NO se guarda nunca

❌ Ejecuciones de tools  
❌ Argumentos técnicos  
❌ Prompts internos  
❌ JSON de requests/responses

---

## 9. Ventajas de esta especificación

* Un solo sistema para Cliente y Admin
* Contexto consistente
* Fácil debugging
* Menor consumo de tokens
* Escalable

---

## 10. Regla final

> Redis guarda **lo que el asistente sabe ahora**, no **cómo llegó a saberlo**.

---

## 11. Estado de implementación

Esta especificación documenta la arquitectura implementada en:

- **spec-003**: Sistema de persistencia de conversaciones en MySQL
- **spec-009**: Sistema de contexto conversacional en Redis

### Implementación actual (2026-02-08)

✅ **Contexto en Redis** (`RedisContextStorage`)
- Claves: `chat:customer:{userId}` y `admin:context:{userId}`
- TTL: 30 minutos (configurable)
- Modelos: `CustomerConversationContext`, `AdminConversationContext`

✅ **Conversaciones en MySQL**
- Tablas: `conversations`, `conversation_messages`
- Tablas Admin: `admin_assistant_conversations`, `admin_assistant_messages`

✅ **Gestión de contexto**
- `CustomerContextManager`, `AdminContextManager`
- Auto-renovación de TTL en cada interacción
- Eliminación manual via endpoint

### Diferencias con la especificación ideal

| Aspecto | Especificación | Implementación Actual |
|---------|----------------|----------------------|
| Clave Redis | `conversation:{id}:*` | `chat:customer:{userId}` |
| Historial en Redis | Lista de 10 mensajes | No (solo estado) |
| Conversación completa | En Redis | En MySQL |
| Embeddings | Opcional | Solo para búsqueda semántica |

### Mejoras futuras

Si se requiere implementar el modelo exacto de esta especificación:

1. **Migrar historial a Redis**: Mover últimos 10 mensajes de MySQL a Redis
2. **Unificar naming**: Cambiar claves a patrón `conversation:{id}:*`
3. **Separar history/state**: Crear claves distintas en Redis
4. **Metadata explícita**: Agregar campo `meta` con timestamps

**Nota**: La implementación actual funciona correctamente. Esta especificación define un modelo **idealizado** para referencia arquitectónica.
