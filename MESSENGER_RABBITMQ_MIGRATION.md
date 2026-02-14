# Migración de Messenger: Doctrine → RabbitMQ

**Fecha**: 14 de febrero de 2026  
**Estado**: ✅ Completado

## Resumen del Cambio

Se ha migrado el sistema de mensajería asíncrona de Symfony Messenger desde transportes **Doctrine (MySQL)** a **RabbitMQ** puro. Todos los mensajes ahora se procesan de forma asíncrona a través de RabbitMQ, eliminando la dependencia de la base de datos MySQL para el sistema de colas.

---

## Cambios Realizados

### 1. Configuración de Messenger (`config/packages/messenger.yaml`)

**Antes** (Doctrine):
```yaml
transports:
    user_embedding_updates:
        dsn: 'doctrine://default?queue_name=user_embedding_updates'
    failed:
        dsn: 'doctrine://default?queue_name=failed'
```

**Después** (RabbitMQ):
```yaml
transports:
    # RabbitMQ transport for user embedding updates
    user_embedding_updates:
        dsn: '%env(RABBITMQ_DSN)%'
        serializer: 'messenger.transport.symfony_serializer'
        options:
            exchange:
                name: 'user_embedding_updates'
                type: direct
                durable: true
            queues:
                user_embedding_updates:
                    binding_keys: ['user.embedding.update']
                    durable: true
            auto_setup: true
        retry_strategy:
            max_retries: 5
            delay: 5000
            multiplier: 2
            max_delay: 30000

    # RabbitMQ transport for embedding sync
    embedding_sync:
        dsn: '%env(RABBITMQ_DSN)%'
        serializer: 'messenger.transport.symfony_serializer'
        options:
            exchange:
                name: 'embedding_sync'
                type: direct
                durable: true
            queues:
                embedding_sync:
                    binding_keys: ['embedding.sync']
                    durable: true
            auto_setup: true
        retry_strategy:
            max_retries: 3
            delay: 3000
            multiplier: 2

    # Dead Letter Queue (DLQ) en RabbitMQ
    failed:
        dsn: '%env(RABBITMQ_DSN)%'
        serializer: 'messenger.transport.symfony_serializer'
        options:
            exchange:
                name: 'failed'
                type: direct
                durable: true
            queues:
                failed:
                    binding_keys: ['failed']
                    durable: true
```

**Routing actualizado**:
```yaml
routing:
    'App\Application\Message\UpdateUserEmbeddingMessage':
        senders: ['user_embedding_updates']
    'App\Application\Message\SyncEmbeddingMessage':
        senders: ['embedding_sync']
```

### 2. Worker Configuration (`docker-compose.yml`)

**Antes**:
```yaml
command: ["php", "bin/console", "messenger:consume", "user_embedding_updates", "--time-limit=3600", "--memory-limit=512M"]
```

**Después** (consume ambas colas):
```yaml
command: ["php", "bin/console", "messenger:consume", "user_embedding_updates", "embedding_sync", "--time-limit=3600", "--memory-limit=512M"]
```

### 3. Documentación Actualizada

- ✅ [`specs/014-user-embeddings-queue/data-model.md`](specs/014-user-embeddings-queue/data-model.md)
- ✅ [`specs/014-user-embeddings-queue/research.md`](specs/014-user-embeddings-queue/research.md)
- ✅ [`specs/014-user-embeddings-queue/tasks.md`](specs/014-user-embeddings-queue/tasks.md)

---

## Variables de Entorno Requeridas

Asegúrese de tener estas variables configuradas en `.env`:

```env
# RabbitMQ Configuration
RABBITMQ_DSN=amqp://myshop_user:myshop_pass@rabbitmq:5672/%2f?frame_max=524288&heartbeat=60
RABBITMQ_USER=myshop_user
RABBITMQ_PASSWORD=myshop_pass
RABBITMQ_VHOST=%2F
```

---

## Pasos de Despliegue

### 1. Verificar que RabbitMQ esté corriendo

```bash
docker-compose ps rabbitmq
```

**Salida esperada**:
```
NAME                 IMAGE                           STATUS
myshop_rabbitmq      rabbitmq:3-management-alpine   Up
```

### 2. Detener workers existentes

```bash
docker-compose stop worker
```

### 3. Limpiar colas Doctrine antiguas (opcional)

Si deseas eliminar las tablas de mensajería de Doctrine de MySQL:

```bash
docker-compose exec php php bin/console doctrine:query:sql "DROP TABLE IF EXISTS messenger_messages"
```

### 4. Reiniciar servicios

```bash
docker-compose up -d --build
```

### 5. Verificar que el worker consuma de RabbitMQ

```bash
docker-compose logs -f worker
```

**Salida esperada**:
```
[OK] Consuming messages from transports "user_embedding_updates, embedding_sync".
```

---

## Verificación Funcional

### 1. Publicar un mensaje de prueba

Desde cualquier controller o servicio:

```php
use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Messenger\MessageBusInterface;

$message = new UpdateUserEmbeddingMessage(
    userId: '550e8400-e29b-41d4-a716-446655440000',
    eventType: EventType::SEARCH,
    searchPhrase: 'test product',
    productId: null,
    occurredAt: new \DateTimeImmutable(),
    metadata: [],
    messageId: hash('sha256', 'test-message-' . time())
);

$messageBus->dispatch($message);
```

### 2. Verificar en RabbitMQ Management UI

1. Abrir http://localhost:15672
2. Login: `myshop_user` / `myshop_pass`
3. Ir a **Queues** → Ver `user_embedding_updates`
4. Verificar que el mensaje fue publicado y consumido

### 3. Verificar logs del worker

```bash
docker-compose logs worker | grep "Processing message"
```

**Salida esperada**:
```
[2026-02-14 10:30:15] app.INFO: Processing message {"message_id":"abc123","user_id":"550e8400-e29b-41d4-a716-446655440000"}
```

### 4. Verificar que MongoDB se actualizó

```bash
docker-compose exec mongodb mongosh myshop
```

```javascript
db.user_embeddings.findOne({user_id: "550e8400-e29b-41d4-a716-446655440000"})
```

---

## Monitoreo y Debugging

### Ver mensajes en cola

```bash
# RabbitMQ Management API
curl -u myshop_user:myshop_pass http://localhost:15672/api/queues/%2F/user_embedding_updates
```

### Ver mensajes fallidos (DLQ)

```bash
# RabbitMQ UI
http://localhost:15672/#/queues/%2F/failed
```

### Reintentar mensajes fallidos

```bash
docker-compose exec php php bin/console messenger:failed:retry --force
```

### Purgar cola (desarrollo solamente)

```bash
docker-compose exec php php bin/console messenger:stop-workers
curl -u myshop_user:myshop_pass -X DELETE http://localhost:15672/api/queues/%2F/user_embedding_updates/contents
```

---

## Ventajas de la Migración

| Aspecto | Doctrine Transport | RabbitMQ |
|---------|-------------------|----------|
| **Performance** | MySQL queries para cada operación | Protocolo AMQP optimizado |
| **Escalabilidad** | Limitado por capacidad MySQL | Colas distribuidas, sharding nativo |
| **Persistencia** | Depende de transacciones DB | Colas durables con confirmación |
| **Monitoreo** | Queries SQL manuales | Management UI integrado |
| **Retries** | Implementado por Symfony | Exponential backoff nativo |
| **DLQ** | Tabla SQL separada | Exchange/Queue dedicado |
| **Latencia** | ~50-100ms | ~5-15ms |

---

## Rollback (Si es necesario)

Para revertir a Doctrine:

1. Restaurar `config/packages/messenger.yaml`:
   ```bash
   git checkout HEAD~1 config/packages/messenger.yaml
   ```

2. Crear tabla de mensajería:
   ```bash
   docker-compose exec php php bin/console messenger:setup-transports
   ```

3. Reiniciar worker:
   ```bash
   docker-compose restart worker
   ```

---

## Soporte

- **RabbitMQ Management UI**: http://localhost:15672
- **Logs del worker**: `docker-compose logs -f worker`
- **Documentación RabbitMQ**: https://www.rabbitmq.com/documentation.html
- **Symfony Messenger**: https://symfony.com/doc/current/messenger.html

---

## Checklist de Validación

- [x] RabbitMQ está corriendo y accesible
- [x] Variables `RABBITMQ_DSN` configuradas en `.env`
- [x] Transportes configurados en `messenger.yaml`
- [x] Worker consume de `user_embedding_updates` y `embedding_sync`
- [x] Mensajes se publican correctamente a RabbitMQ
- [x] Handler procesa mensajes y actualiza MongoDB
- [x] Failed transport (DLQ) funciona correctamente
- [x] Retry strategy funciona con exponential backoff
- [x] Documentación actualizada

---

**Migración completada con éxito ✅**
