# Verificación Rápida: Migración a RabbitMQ

Esta guía te ayuda a verificar que la migración de Doctrine Messenger a RabbitMQ funciona correctamente.

## 🚀 Inicio Rápido

### 1. Reiniciar servicios

```bash
# Detener todos los servicios
docker-compose down

# Iniciar con rebuild
docker-compose up -d --build
```

### 2. Verificar que RabbitMQ está corriendo

```bash
docker-compose ps rabbitmq
```

**Esperado:**
```
NAME                 IMAGE                           STATUS
myshop_rabbitmq      rabbitmq:3-management-alpine   Up
```

### 3. Verificar el worker

```bash
docker-compose logs worker | head -20
```

**Esperado:**
```
[OK] Consuming messages from transports "user_embedding_updates, embedding_sync".
```

### 4. Verificar estado de las colas

**Opción A: Script de PHP**
```bash
docker-compose exec php php scripts/check-queue-status.php
```

**Opción B: Script de Bash**
```bash
bash scripts/check-rabbitmq-status.sh
```

**Opción C: Management UI**
- Abrir: http://localhost:15672
- Login: `myshop_user` / `myshop_pass`
- Ir a: **Queues** tab

## ✅ Checklist de Validación

- [ ] RabbitMQ container está `Up`
- [ ] Worker consume de `user_embedding_updates` y `embedding_sync`
- [ ] No hay errores en logs del worker: `docker-compose logs worker`
- [ ] Las 3 colas existen en RabbitMQ:
  - `user_embedding_updates`
  - `embedding_sync`
  - `failed`
- [ ] Management UI es accesible en http://localhost:15672

## 🧪 Prueba Funcional

### Publicar un mensaje de prueba

Crear este archivo de prueba: `test-rabbitmq-message.php`

```php
<?php

require 'vendor/autoload.php';

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new \App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

$messageBus = $container->get('messenger.bus.default');

$message = new UpdateUserEmbeddingMessage(
    userId: '550e8400-e29b-41d4-a716-446655440000',
    eventType: EventType::SEARCH,
    searchPhrase: 'test product rabbitmq',
    productId: null,
    occurredAt: new \DateTimeImmutable(),
    metadata: ['test' => true],
    messageId: hash('sha256', 'test-migration-' . time())
);

echo "Dispatching test message...\n";
$messageBus->dispatch($message);
echo "Message dispatched successfully!\n";
echo "Check RabbitMQ UI: http://localhost:15672/#/queues/%2F/user_embedding_updates\n";
```

Ejecutar:
```bash
docker-compose exec php php test-rabbitmq-message.php
```

### Verificar procesamiento

1. **Ver en RabbitMQ UI:**
   - http://localhost:15672/#/queues/%2F/user_embedding_updates
   - El mensaje debería aparecer y desaparecer rápidamente (procesado)

2. **Ver logs del worker:**
   ```bash
   docker-compose logs worker | grep "Processing message"
   ```

3. **Verificar en MongoDB:**
   ```bash
   docker-compose exec mongodb mongosh myshop --eval "db.user_embeddings.findOne({user_id: '550e8400-e29b-41d4-a716-446655440000'})"
   ```

## 🔧 Troubleshooting

### Problema: Worker no consume mensajes

**Síntoma:**
```
[CRITICAL] Error thrown while running command "messenger:consume". Message: "Exception"
```

**Solución:**
```bash
# Verificar configuración
docker-compose exec php php bin/console debug:config framework messenger

# Verificar que RABBITMQ_DSN está configurado
docker-compose exec php printenv RABBITMQ_DSN

# Reiniciar worker
docker-compose restart worker
```

### Problema: No puedo acceder a RabbitMQ UI

**Verificar puerto:**
```bash
docker-compose ps rabbitmq
```

**Verificar logs:**
```bash
docker-compose logs rabbitmq
```

### Problema: Mensajes se quedan en la cola

**Verificar que el worker esté corriendo:**
```bash
docker-compose ps worker
```

**Ver logs en tiempo real:**
```bash
docker-compose logs -f worker
```

**Reiniciar worker:**
```bash
docker-compose restart worker
```

## 📊 Monitoreo

### Comandos útiles

```bash
# Ver estadísticas en tiempo real
watch -n 1 'docker-compose exec php php scripts/check-queue-status.php'

# Ver logs del worker en tiempo real
docker-compose logs -f worker

# Ver mensajes fallidos (DLQ)
curl -u myshop_user:myshop_pass http://localhost:15672/api/queues/%2F/failed | jq .

# Purgar cola (¡solo en desarrollo!)
curl -u myshop_user:myshop_pass -X DELETE http://localhost:15672/api/queues/%2F/user_embedding_updates/contents
```

## 📚 Referencias

- [Guía completa de migración](MESSENGER_RABBITMQ_MIGRATION.md)
- [Symfony Messenger Docs](https://symfony.com/doc/current/messenger.html)
- [RabbitMQ Management Plugin](https://www.rabbitmq.com/management.html)

---

**¿Algún problema?** Revisa los logs:
```bash
docker-compose logs php worker rabbitmq
```
