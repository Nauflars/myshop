<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\Conversation\ListUserConversations;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GetUserInfoTool - AI Tool for getting current user information
 * 
 * Returns information about the authenticated user including name, email, role, and conversation count.
 * Useful for personalized responses and admin detection.
 */
#[AsTool(
    'GetUserInfo',
    'Obtener información del usuario actual (nombre, email, rol, conversaciones). Usa esta herramienta cuando necesites saber quién es el usuario o si es administrador.'
)]
final class GetUserInfoTool
{
    public function __construct(
        private readonly ListUserConversations $listUserConversations,
        private readonly Security $security
    ) {
    }

    /**
     * @return array{success: bool, user: array|null, message: string}
     */
    public function __invoke(): array
    {
        try {
            $user = $this->security->getUser();
            
            if (!$user instanceof User) {
                return [
                    'success' => false,
                    'user' => null,
                    'message' => 'No hay usuario autenticado.',
                ];
            }

            // Get conversation count
            $conversationsResult = $this->listUserConversations->execute($user);
            $conversationCount = $conversationsResult['count'] ?? 0;

            return [
                'success' => true,
                'user' => [
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => $user->isAdmin(),
                    'conversationCount' => $conversationCount,
                ],
                'message' => sprintf(
                    'Usuario actual: %s (%s)',
                    $user->getName(),
                    $user->isAdmin() ? 'Administrador' : 'Cliente'
                ),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'No pude obtener la información del usuario.',
            ];
        }
    }
}
