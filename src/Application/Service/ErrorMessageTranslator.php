<?php

namespace App\Application\Service;

use Psr\Log\LoggerInterface;

/**
 * T094: User-Friendly Error Messages.
 *
 * Translates technical exceptions into Spanish user-friendly error messages.
 * Technical details are logged but not exposed to end users.
 */
class ErrorMessageTranslator
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get user-friendly error message for an exception.
     *
     * @param string $context Additional context (e.g., 'search', 'product_sync')
     *
     * @return string Spanish user-friendly error message
     */
    public function translate(\Throwable $exception, string $context = ''): string
    {
        // Log technical details (not exposed to user)
        $this->logger->error('Error translator caught exception', [
            'context' => $context,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Map technical exceptions to user-friendly Spanish messages
        $exceptionClass = get_class($exception);
        $message = $exception->getMessage();

        // Circuit breaker open
        if (str_contains($message, 'circuit breaker open')) {
            if (str_contains($message, 'MongoDB')) {
                return 'El servicio de búsqueda está temporalmente no disponible. Estamos trabajando en resolverlo. Por favor, intenta nuevamente en unos minutos.';
            }
            if (str_contains($message, 'OpenAI')) {
                return 'El servicio de búsqueda inteligente está temporalmente no disponible. Hemos activado el modo de búsqueda alternativo automáticamente.';
            }
        }

        // MongoDB connection errors
        if ('MongoDB\Driver\Exception\ConnectionTimeoutException' === $exceptionClass
            || str_contains($message, 'MongoDB')
            || str_contains($message, 'connection timeout')) {
            return 'No pudimos conectarnos a la base de datos. Por favor, intenta nuevamente en unos momentos.';
        }

        // OpenAI API errors
        if (str_contains($message, 'OpenAI')
            || str_contains($message, 'API request failed')
            || str_contains($message, 'embedding generation failed')) {
            return 'El servicio de búsqueda inteligente no está disponible temporalmente. Hemos activado el modo de búsqueda alternativo.';
        }

        // Rate limit errors
        if (str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')) {
            return 'Estamos procesando demasiadas solicitudes. Por favor, espera unos segundos e intenta nuevamente.';
        }

        // Timeout errors
        if (str_contains($message, 'timeout')
            || str_contains($message, 'timed out')) {
            return 'La búsqueda está tardando más de lo esperado. Por favor, intenta con términos más específicos o inténtalo nuevamente.';
        }

        // Validation errors (embedding dimensions, description length)
        if ($exception instanceof \InvalidArgumentException) {
            if (str_contains($message, 'embedding dimensions')) {
                return 'Ha ocurrido un error procesando el producto. Por favor, contacta al soporte técnico.';
            }
            if (str_contains($message, 'description') && str_contains($message, 'too long')) {
                return 'La descripción del producto es demasiado larga. Por favor, reduce el texto a menos de 8000 caracteres.';
            }

            // Generic validation error - expose message as it's likely user-facing
            return $message;
        }

        // Network errors
        if (str_contains($message, 'network')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'DNS')) {
            return 'No pudimos conectarnos al servicio externo. Por favor, verifica tu conexión a internet e intenta nuevamente.';
        }

        // Runtime errors (circuit breaker, service unavailable)
        if ($exception instanceof \RuntimeException) {
            if (str_contains($message, 'service unavailable')) {
                return 'El servicio está temporalmente no disponible. Por favor, intenta nuevamente en unos minutos.';
            }

            // Generic runtime error
            return 'Ha ocurrido un error procesando tu solicitud. Por favor, intenta nuevamente.';
        }

        // Default fallback - generic error (context-specific)
        return match ($context) {
            'search' => 'No pudimos completar la búsqueda. Por favor, intenta con otros términos o inténtalo nuevamente más tarde.',
            'product_sync' => 'No pudimos sincronizar el producto. El sistema intentará nuevamente automáticamente.',
            'embedding_generation' => 'No pudimos procesar el producto para búsqueda inteligente. El producto se guardó correctamente y se procesará más tarde.',
            default => 'Ha ocurrido un error inesperado. Por favor, intenta nuevamente o contacta al soporte si el problema persiste.',
        };
    }

    /**
     * Get user-friendly error message with HTTP status code.
     *
     * @return array ['message' => string, 'status_code' => int]
     */
    public function translateWithStatus(\Throwable $exception, string $context = ''): array
    {
        $message = $this->translate($exception, $context);

        // Determine appropriate HTTP status code
        $statusCode = match (true) {
            $exception instanceof \InvalidArgumentException => 400, // Bad Request
            str_contains($exception->getMessage(), 'rate limit') => 429, // Too Many Requests
            str_contains($exception->getMessage(), 'timeout') => 504, // Gateway Timeout
            str_contains($exception->getMessage(), 'circuit breaker open') => 503, // Service Unavailable
            str_contains($exception->getMessage(), 'MongoDB') => 503, // Service Unavailable
            str_contains($exception->getMessage(), 'OpenAI') => 503, // Service Unavailable
            default => 500, // Internal Server Error
        };

        return [
            'message' => $message,
            'status_code' => $statusCode,
        ];
    }
}
