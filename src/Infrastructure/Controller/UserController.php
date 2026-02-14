<?php

namespace App\Infrastructure\Controller;

use App\Application\UseCase\CreateUser;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    #[Route('/users', name: 'api_user_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $this->createUser->execute(
                name: $data['name'] ?? '',
                email: $data['email'] ?? '',
                plainPassword: $data['password'] ?? '',
                role: $data['role'] ?? 'ROLE_CUSTOMER'
            );

            return $this->json([
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/users/me', name: 'api_user_me', methods: ['GET'])]
    public function me(#[CurrentUser] $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
