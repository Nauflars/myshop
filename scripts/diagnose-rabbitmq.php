<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

echo "==========================================================\n";
echo "DIAGNÓSTICO COMPLETO DE RABBITMQ\n";
echo "==========================================================\n\n";

// Test 1: Extensión PHP AMQP
echo "1️⃣ TEST: Extensión PHP AMQP\n";
echo "   Cargada: " . (extension_loaded('amqp') ? '✅ SÍ' : '❌ NO') . "\n";
if (extension_loaded('amqp')) {
    echo "   Versión: " . phpversion('amqp') . "\n";
}
echo "\n";

// Test 2: Paquete Symfony AMQP Messenger
echo "2️⃣ TEST: Paquete Symfony AMQP Messenger\n";
$amqpClasses = [
    'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport',
    'Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection',
    'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender',
];
foreach ($amqpClasses as $class) {
    echo "   " . basename(str_replace('\\', '/', $class)) . ": " . (class_exists($class) ? '✅' : '❌') . "\n";
}
echo "\n";

// Test 3: Conexión directa con extensión AMQP
echo "3️⃣ TEST: Conexión directa con extensión PHP AMQP\n";
try {
    $conn = new AMQPConnection([
        'host' => 'rabbitmq',
        'port' => 5672,
        'vhost' => '/',
        'login' => 'myshop_user',
        'password' => 'myshop_pass'
    ]);
    
    $connected = $conn->connect();
    echo "   Conexión: " . ($conn->isConnected() ? '✅ CONECTADO' : '❌ FALLÓ') . "\n";
    
    if ($conn->isConnected()) {
        $channel = new AMQPChannel($conn);
        echo "   Canal: ✅ CREADO\n";
        
        // Declarar exchange
        $exchange = new AMQPExchange($channel);
        $exchange->setName('test_direct_exchange');
        $exchange->setType(AMQP_EX_TYPE_DIRECT);
        $exchange->setFlags(AMQP_DURABLE);
        $exchange->declareExchange();
        echo "   Exchange: ✅ DECLARADO\n";
        
        // Declarar cola
        $queue = new AMQPQueue($channel);
        $queue->setName('test_direct_queue');
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $queue->bind('test_direct_exchange', 'test.key');
        echo "   Queue: ✅ DECLARADO Y BINDEADO\n";
        
        // Publicar mensaje
        $message = json_encode(['test' => 'mensaje_directo', 'timestamp' => time()]);
        $result = $exchange->publish($message, 'test.key', AMQP_NOPARAM, ['delivery_mode' => 2]);
        echo "   Publicación: " . ($result ? '✅ EXITOSA' : '❌ FALLÓ') . "\n";
        
        // Verificar mensaje en cola
        $count = $queue->declareQueue();
        echo "   Mensajes en cola: {$count}\n";
        
        if ($count > 0) {
            echo "   🎉 ¡MENSAJE RECIBIDO EN RABBITMQ!\n";
            // Limpiar
            $queue->purge();
        }
        
        $conn->disconnect();
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: {$e->getMessage()}\n";
}
echo "\n";

// Test 4: Configuración de Symfony Messenger
echo "4️⃣ TEST: Configuración de Symfony Messenger\n";

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "   Kernel: ✅ CARGADO\n";
echo "   Entorno: {$_ENV['APP_ENV']}\n";
echo "   RABBITMQ_DSN: {$_ENV['RABBITMQ_DSN']}\n\n";

// Test 5: Message Bus
echo "5️⃣ TEST: Symfony Message Bus\n";
try {
    $messageBus = $container->get('Symfony\Component\Messenger\MessageBusInterface');
    echo "   MessageBus: ✅ DISPONIBLE\n";
    echo "   Clase: " . get_class($messageBus) . "\n";
} catch (Exception $e) {
    echo "   ❌ ERROR: {$e->getMessage()}\n";
}
echo "\n";

// Test 6: Publisher
echo "6️⃣ TEST: RabbitMQ Publisher\n";
try {
    $publisher = $container->get('App\Infrastructure\Queue\RabbitMQPublisher');
    echo "   Publisher: ✅ DISPONIBLE\n";
    echo "   Clase: " . get_class($publisher) . "\n";
} catch (Exception $e) {
    echo "   ❌ ERROR: {$e->getMessage()}\n";
}
echo "\n";

// Test 7: Dispatch real con inspección de stamps
echo "7️⃣ TEST: Dispatch de mensaje real\n";
use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

try {
    $message = new UpdateUserEmbeddingMessage(
        userId: 'test-diagnostic-' . time(),
        eventType: EventType::SEARCH,
        searchPhrase: 'diagnostic test',
        productId: null,
        occurredAt: new DateTimeImmutable(),
        metadata: ['diagnostic' => true],
        messageId: hash('sha256', 'diagnostic-' . microtime(true))
    );
    
    echo "   Mensaje creado: ✅\n";
    echo "   Message ID: {$message->messageId}\n";
    
    $stamps = [new TransportNamesStamp(['user_embedding_updates'])];
    $envelope = $messageBus->dispatch($message, $stamps);
    
    echo "   Dispatch ejecutado: ✅\n";
    
    echo "\n   📋 Stamps en el envelope:\n";
    foreach ($envelope->all() as $stampType => $instances) {
        $shortName = substr($stampType, strrpos($stampType, '\\') + 1);
        echo "      - {$shortName}: " . count($instances) . " instancia(s)\n";
    }
    
    // El problema clave: si tiene SentStamp pero no está en RabbitMQ
    $hasSentStamp = !empty($envelope->all('Symfony\Component\Messenger\Stamp\SentStamp'));
    echo "\n   SentStamp presente: " . ($hasSentStamp ? '✅ SÍ' : '❌ NO') . "\n";
    
    if ($hasSentStamp) {
        echo "   ⚠️  PROBLEMA DETECTADO:\n";
        echo "      - Symfony dice que envió el mensaje (SentStamp)\n";
        echo "      - Pero necesitas verificar si está en RabbitMQ\n";
        echo "      - Ejecuta: docker-compose exec rabbitmq rabbitmqctl list_queues -p / name messages\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR: {$e->getMessage()}\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}

echo "\n";
echo "==========================================================\n";
echo "RESUMEN DEL DIAGNÓSTICO\n";
echo "==========================================================\n\n";

echo "Si el Test 3 funcionó (mensaje directo llegó a RabbitMQ):\n";
echo "  → RabbitMQ y la extensión PHP AMQP funcionan correctamente\n";
echo "  → El problema está en la configuración de Symfony Messenger\n\n";

echo "Si el Test 7 tiene SentStamp pero no hay mensajes en la cola:\n";
echo "  → El transporte AMQP no está enviando realmente\n";
echo "  → Posible problema con el DSN o configuración del transport\n\n";

echo "Comandos útiles:\n";
echo "  docker-compose exec rabbitmq rabbitmqctl list_queues -p / name messages\n";
echo "  docker-compose exec rabbitmq rabbitmqctl list_connections\n";
echo "  docker-compose exec rabbitmq rabbitmqctl list_exchanges\n";
echo "  docker-compose exec rabbitmq rabbitmqctl list_bindings\n\n";

echo "==========================================================\n";
