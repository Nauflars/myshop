<?php

namespace App\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * JSON login success/failure handler for mobile API authentication.
 *
 * The json_login authenticator handles the actual authentication.
 * This controller provides the route and handles success/failure responses.
 *
 * NOTE: Does NOT extend AbstractController to avoid container injection issues
 * when used as a security success/failure handler service.
 */
class ApiLoginController implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    #[Route('/api/login', name: 'api_login_check', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This method is never reached: json_login authenticator intercepts the request.
        // If somehow reached, return an error.
        return new JsonResponse(
            ['error' => 'JSON login not properly configured'],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        /** @var \App\Domain\Entity\User $user */
        $user = $token->getUser();

        return new JsonResponse([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Invalid credentials'],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
