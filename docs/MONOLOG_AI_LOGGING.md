# Monolog Configuration for AI Agent & Tools

Esta guía explica cómo usar Monolog para ver los mensajes del agente AI y el uso de las tools en el Profiler de Symfony.

## 📋 Tabla de Contenidos

- [Instalación](#instalación)
- [Configuración](#configuración)
- [Canales de Logging](#canales-de-logging)
- [Uso en el Código](#uso-en-el-código)
- [Visualización en el Profiler](#visualización-en-el-profiler)
- [Archivos de Log](#archivos-de-log)
- [Ejemplos](#ejemplos)

## 🚀 Instalación

Monolog ya está instalado en este proyecto a través de `symfony/monolog-bundle`.

```bash
# Si necesitas reinstalar las dependencias
docker-compose exec php composer install
```

## ⚙️ Configuración

### Canales de Logging

Se han configurado 3 canales dedicados para el sistema AI:

1. **`ai_agent`** - Para logs del agente principal (ChatbotController, AdminAssistantController)
2. **`ai_tools`** - Para logs de las herramientas (AddToCartTool, SemanticProductSearchTool, etc.)
3. **`ai_context`** - Para logs del contexto de conversación (ConversationManager, ContextManagers)

### Configuración en `config/packages/monolog.yaml`

```yaml
monolog:
    channels:
        - ai_agent
        - ai_tools
        - ai_context

when@dev:
    monolog:
        handlers:
            ai_agent:
                type: stream
                path: "%kernel.logs_dir%/ai_agent.log"
                level: debug
                channels: ["ai_agent"]
                formatter: monolog.formatter.json

            ai_tools:
                type: stream
                path: "%kernel.logs_dir%/ai_tools.log"
                level: debug
                channels: ["ai_tools"]
                formatter: monolog.formatter.json
```

## 💻 Uso en el Código

### En Controladores (Canal `ai_agent`)

```php
use Psr\Log\LoggerInterface;

class ChatbotController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $aiAgentLogger
    ) {
    }

    public function chat(Request $request): JsonResponse
    {
        $this->aiAgentLogger->info('🤖 AI AGENT CALL START', [
            'user_message' => $userMessage,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'messages_in_context' => count($messages),
            'user_roles' => $user->getRoles()
        ]);

        // ... código del agente

        $this->aiAgentLogger->info('🔧 Tool Calls Made', [
            'tool_calls' => $toolCalls
        ]);
    }
}
```

**Configuración en `config/services.yaml`:**

```yaml
App\Infrastructure\Controller\ChatbotController:
    arguments:
        $aiAgentLogger: '@monolog.logger.ai_agent'
```

### En Tools (Canal `ai_tools`)

```php
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('AddToCart', 'Add a product to cart')]
final class AddToCartTool
{
    public function __construct(
        private readonly LoggerInterface $aiToolsLogger
    ) {
    }

    public function __invoke(string $productName, int $quantity = 1): array
    {
        $this->aiToolsLogger->info('🛒 AddToCartTool called', [
            'product_name' => $productName,
            'quantity' => $quantity
        ]);

        // ... lógica de la tool

        $this->aiToolsLogger->info('AddToCartTool: Successfully added to cart', [
            'product_name' => $productName,
            'total_items' => $result['totalItems'],
            'total_amount' => $result['totalAmount']
        ]);
    }
}
```

**Configuración automática para todas las Tools:**

```yaml
# En config/services.yaml
App\Infrastructure\AI\Tool\:
    resource: '../src/Infrastructure/AI/Tool/'
    autowire: true
    arguments:
        $aiToolsLogger: '@monolog.logger.ai_tools'
    tags: ['ai.tool']
```

### En Servicios de Contexto (Canal `ai_context`)

```php
class ConversationManager
{
    public function __construct(
        private readonly LoggerInterface $aiContextLogger
    ) {
    }

    public function saveUserMessage(...): array
    {
        $this->aiContextLogger->debug('Saving user message', [
            'conversation_id' => $conversationId,
            'message_length' => strlen($message)
        ]);
    }
}
```

## 🔍 Visualización en el Profiler

### Acceso al Web Profiler

1. **Ejecuta una petición** al endpoint del chatbot:
   ```bash
   POST http://localhost/api/chat
   {
       "message": "show me laptops for gaming"
   }
   ```

2. **Abre el Web Profiler** - Haz clic en el icono de Symfony en la barra de debug inferior

3. **Ve a la pestaña "Logs"** - Aquí verás todos los logs organizados por canal

### Filtrar por Canal

En el Profiler, puedes filtrar los logs por canal:
- **ai_agent** - Ver solo logs del agente
- **ai_tools** - Ver solo logs de las herramientas
- **ai_context** - Ver solo logs de contexto

### Información Visible en el Profiler

**Canal `ai_agent`:**
- 🤖 Inicio y fin de llamadas al agente
- 📝 Tipo de respuesta y contenido
- 🔧 Lista de tools llamadas
- ⚠️ Errores del agente
- 📊 Metadata del resultado

**Canal `ai_tools`:**
- 🔍 Llamada a SemanticProductSearchTool con parámetros
- 🛒 AddToCartTool con producto y cantidad
- ✅ Resultado exitoso o error
- ⏱️ Tiempo de ejecución (si se implementa)

## 📁 Archivos de Log

Los logs también se guardan en archivos para análisis posterior:

### Desarrollo (`when@dev`)

```
var/log/dev.log          - Logs generales
var/log/ai_agent.log     - Logs del agente (formato JSON)
var/log/ai_tools.log     - Logs de las tools (formato JSON)
var/log/ai_context.log   - Logs de contexto
```

### Producción (`when@prod`)

Todos los logs se envían a `php://stderr` para ser capturados por el sistema de logging del contenedor Docker.

## 📚 Ejemplos

### Ejemplo 1: Ver llamada completa del agente

```php
$this->aiAgentLogger->info('🤖 AI AGENT CALL START', [
    'user_message' => $userMessage,
    'conversation_id' => $conversationId,
    'user_id' => $userId,
    'messages_in_context' => count($messages)
]);

// ... llamada al agente

if (method_exists($result, 'getToolCalls')) {
    $this->aiAgentLogger->info('🔧 Tool Calls Made', [
        'tool_calls' => $result->getToolCalls()
    ]);
}

$this->aiAgentLogger->info('🤖 AI AGENT CALL END', [
    'response_type' => gettype($content),
    'response_length' => strlen($content)
]);
```

**Output en el Profiler:**
```
[INFO] ai_agent: 🤖 AI AGENT CALL START
Context: {
    "user_message": "show me laptops for gaming",
    "conversation_id": "conv-123",
    "user_id": "user-456",
    "messages_in_context": 5
}

[INFO] ai_agent: 🔧 Tool Calls Made
Context: {
    "tool_calls": [
        {
            "tool": "SemanticProductSearchTool",
            "arguments": {"query": "gaming laptops"}
        }
    ]
}

[INFO] ai_agent: 🤖 AI AGENT CALL END
Context: {
    "response_type": "string",
    "response_length": 245
}
```

### Ejemplo 2: Ver uso de una tool específica

```php
$this->aiToolsLogger->info('🛒 AddToCartTool called', [
    'product_name' => $productName,
    'quantity' => $quantity
]);

// ... lógica

$this->aiToolsLogger->info('AddToCartTool: Successfully added to cart', [
    'product_name' => $productName,
    'total_items' => $result['totalItems'],
    'total_amount' => $result['totalAmount']
]);
```

**Output en el archivo `var/log/ai_tools.log`:**
```json
{
    "message": "🛒 AddToCartTool called",
    "context": {
        "product_name": "Gaming Laptop MSI",
        "quantity": 1
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "ai_tools",
    "datetime": "2026-02-12T10:30:45.123456+00:00"
}
```

### Ejemplo 3: Logging de errores

```php
try {
    // ... código que puede fallar
} catch (\Exception $e) {
    $this->aiAgentLogger->error('Symfony AI Agent Error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
```

## 🎯 Niveles de Log Recomendados

| Nivel | Uso Recomendado |
|-------|-----------------|
| `debug()` | Información detallada para debugging (MessageBag, metadata) |
| `info()` | Eventos importantes (inicio/fin de agente, llamadas a tools) |
| `warning()` | Situaciones inesperadas pero recuperables (usuario no autenticado) |
| `error()` | Errores que requieren atención (fallo de tool, excepción) |
| `critical()` | Errores críticos del sistema (fallo total del agente) |

## 🔧 Comandos Útiles

### Ver logs en tiempo real

```bash
# Ver logs del agente
docker-compose exec php tail -f var/log/ai_agent.log | jq '.'

# Ver logs de las tools
docker-compose exec php tail -f var/log/ai_tools.log | jq '.'

# Ver todos los logs de desarrollo
docker-compose exec php tail -f var/log/dev.log
```

### Limpiar logs

```bash
# Limpiar todos los logs
docker-compose exec php rm -rf var/log/*.log

# Limpiar solo logs de AI
docker-compose exec php rm -rf var/log/ai_*.log
```

## 📊 Análisis de Logs

### Buscar todas las llamadas a una tool específica

```bash
docker-compose exec php grep "SemanticProductSearchTool called" var/log/ai_tools.log | jq '.'
```

### Contar errores del día

```bash
docker-compose exec php grep -c "ERROR" var/log/ai_agent.log
```

### Ver solo mensajes de un usuario específico

```bash
docker-compose exec php grep "user-123" var/log/ai_agent.log | jq '.'
```

## 🚨 Troubleshooting

### Los logs no aparecen en el Profiler

1. Verifica que estés en modo `dev`:
   ```bash
   # En .env
   APP_ENV=dev
   ```

2. Limpia la caché:
   ```bash
   docker-compose exec php php bin/console cache:clear
   ```

3. Verifica los permisos de escritura:
   ```bash
   docker-compose exec php chmod -R 777 var/log
   ```

### Canal de logger no encontrado

Si ves el error "The logger does not exist on channel 'ai_agent'":

1. Asegúrate de que el canal está declarado en `config/packages/monolog.yaml`
2. Recarga Symfony:
   ```bash
   docker-compose restart php
   ```

## 📝 Notas Importantes

- Los logs en formato JSON son más fáciles de parsear con herramientas como `jq`
- En producción, usa niveles `info` o superiores para reducir el volumen
- Los logs del Profiler se guardan en `var/cache/dev/profiler/`
- El buffer_size en `fingers_crossed` handler retiene los últimos 50 mensajes

## 🔗 Referencias

- [Symfony Monolog Documentation](https://symfony.com/doc/current/logging.html)
- [Monolog Handlers](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)
