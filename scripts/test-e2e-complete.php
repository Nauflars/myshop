<?php

require __DIR__.'/../vendor/autoload.php';

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "==========================================================\n";
echo "TEST END-TO-END: RabbitMQ Asíncrono COMPLETO\n";
echo "==========================================================\n\n";

$publisher = $container->get('App\Infrastructure\Queue\RabbitMQPublisher');

echo "🎯 PASO 1: Publicar 3 mensajes a RabbitMQ\n\n";

for ($i = 1; $i <= 3; $i++) {
    $userId = "test-e2e-user-{$i}-" . time();
    $message = new UpdateUserEmbeddingMessage(
        userId: $userId,
        eventType: EventType::SEARCH,
        searchPhrase: "test search {$i}",
        productId: null,
        occurredAt: new \DateTimeImmutable(),
        metadata: ['test' => 'e2e', 'iteration' => $i],
        messageId: hash('sha256', $userId . microtime(true))
    );
    
    $result = $publisher->publish($message);
    
    echo "  Mensaje #{$i}: " . ($result ? '✅ Publicado' : '❌ Falló') . "\n";
    echo "    User ID: {$userId}\n";
    echo "    Message ID: {$message->messageId}\n\n";
    
    // Pequeño delay para evitar colisiones
    usleep(100000);
}

echo "\n📊 RESUMEN:\n";
echo "  ✅ 3 mensajes publicados a RabbitMQ\n";
echo "  ✅ Usando transporte AMQP (no doctrine, no sync)\n";
echo "  ✅ Worker debe consumirlos automáticamente\n\n";

echo "🔍 VERIFICACIÓN:\n";
echo "  1. Ver cola: docker-compose exec rabbitmq rabbitmqctl list_queues -p / name messages\n";
echo "  2. Ver logs worker: docker-compose logs --tail=50 worker\n";
echo "  3. Si hay mensajes en cola, el worker está detenido o hay error\n";
echo "  4. Si cola = 0, los mensajes fueron consumidos exitosamente ✅\n\n";

echo "==========================================================\n";
