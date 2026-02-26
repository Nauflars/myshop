<?php

namespace App\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class ApiLoginController implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    #[Route('/api/login', name: 'api_login_check', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This method is intercepted by the json_login authenticator.
        // It should never actually be reached.
        return new JsonResponse(['error' => 'Missing credentials'], Response::HTTP_BAD_REQUEST);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
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
        return new JsonResponse([
            'error' => $exception->getMessageKey(),
            'message' => 'Invalid credentials',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
