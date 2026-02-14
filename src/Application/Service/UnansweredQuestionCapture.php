<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\UnansweredQuestion;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\UnansweredQuestionRepository;
use Psr\Log\LoggerInterface;

/**
 * UnansweredQuestionCapture Service.
 *
 * Responsible for capturing and storing questions that the AI chatbot cannot answer.
 * Spec: 006-unanswered-questions-admin (FR-001 to FR-008)
 */
class UnansweredQuestionCapture
{
    public function __construct(
        private readonly UnansweredQuestionRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Capture an unanswered question.
     *
     * @param string      $questionText   The original user question
     * @param User|null   $user           The authenticated user (null if anonymous)
     * @param string      $userRole       User's role at time of question
     * @param string      $reasonCategory Reason why question couldn't be answered
     * @param string|null $conversationId Associated conversation ID
     */
    public function capture(
        string $questionText,
        ?User $user,
        string $userRole,
        string $reasonCategory,
        ?string $conversationId = null,
    ): UnansweredQuestion {
        // Sanitize question text (remove sensitive data patterns)
        $sanitizedText = $this->sanitizeQuestionText($questionText);

        // Validate reason category
        if (!in_array($reasonCategory, UnansweredQuestion::getValidReasons())) {
            throw new \InvalidArgumentException("Invalid reason category: $reasonCategory");
        }

        // Create and persist entity
        $question = new UnansweredQuestion(
            $sanitizedText,
            $user,
            $userRole,
            $reasonCategory,
            $conversationId
        );

        try {
            $this->repository->save($question);

            $this->logger->info('Captured unanswered question', [
                'question_id' => $question->getId(),
                'user_id' => $user?->getId(),
                'reason' => $reasonCategory,
                'conversation_id' => $conversationId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to capture unanswered question', [
                'error' => $e->getMessage(),
                'question_text' => substr($sanitizedText, 0, 100),
            ]);
            throw $e;
        }

        return $question;
    }

    /**
     * Sanitize question text to remove sensitive data patterns.
     *
     * @param string $text Original question text
     *
     * @return string Sanitized text
     */
    private function sanitizeQuestionText(string $text): string
    {
        // Remove potential credit card numbers (simple pattern)
        $text = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[REDACTED_CARD]', $text);

        // Remove potential email addresses in certain contexts
        // (keep if it's asking about "my email" but remove actual email addresses)
        $text = preg_replace('/(?<!mi |my |el |the )\b[\w\.-]+@[\w\.-]+\.\w{2,}\b/', '[REDACTED_EMAIL]', $text);

        // Remove potential passwords (words following "password", "contraseña", "clave")
        $text = preg_replace('/\b(?:password|contraseña|clave)\s*[:\s]\s*\S+/i', 'password: [REDACTED]', $text);

        // Remove potential phone numbers
        $text = preg_replace('/\b\+?\d{1,3}?[\s.-]?\(?\d{1,4}\)?[\s.-]?\d{1,4}[\s.-]?\d{1,9}\b/', '[REDACTED_PHONE]', $text);

        return trim($text);
    }

    /**
     * Get polite fallback message for user when question cannot be answered.
     *
     * @param string $reasonCategory Reason for inability to answer
     *
     * @return string Spanish message for user
     */
    public function getPoliteFallbackMessage(string $reasonCategory): string
    {
        return match ($reasonCategory) {
            UnansweredQuestion::REASON_MISSING_TOOL => 'Todavía no puedo ayudarte con esa solicitud, pero estoy aprendiendo constantemente. '
                .'¿Quieres que te ayude con productos, tu carrito o pedidos?',

            UnansweredQuestion::REASON_UNSUPPORTED_REQUEST => 'No puedo ayudarte con eso en este momento, pero puedo ayudarte a buscar productos, '
                .'gestionar tu carrito o revisar tus pedidos. ¿En qué puedo ayudarte?',

            UnansweredQuestion::REASON_TOOL_ERROR => 'Disculpa, encontré un problema al procesar tu solicitud. '
                .'¿Podrías intentarlo de nuevo o preguntarme algo diferente?',

            UnansweredQuestion::REASON_INSUFFICIENT_DATA => 'No tengo suficiente información para responder eso. '
                .'¿Podrías darme más detalles o preguntarme sobre productos, tu carrito o pedidos?',

            default => 'Lo siento, no puedo ayudarte con eso ahora. '
                .'¿Quieres que te ayude con productos, tu carrito o pedidos?',
        };
    }
}
