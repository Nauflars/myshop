<?php

namespace App\Tests\Infrastructure\Controller;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Controller\ApiLoginController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiLoginControllerTest extends TestCase
{
    private ApiLoginController $controller;

    protected function setUp(): void
    {
        $this->controller = new ApiLoginController();
    }

    public function testLoginRouteReturnsErrorWhenReachedDirectly(): void
    {
        $response = $this->controller->login();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testOnAuthenticationSuccessReturnsUserData(): void
    {
        $user = new User('John Doe', new Email('john@example.com'), 'hash123', 'ROLE_CUSTOMER');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = new Request();

        $response = $this->controller->onAuthenticationSuccess($request, $token);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($user->getId(), $data['id']);
        $this->assertEquals('John Doe', $data['name']);
        $this->assertEquals('john@example.com', $data['email']);
        $this->assertContains('ROLE_CUSTOMER', $data['roles']);
    }

    public function testOnAuthenticationSuccessReturnsAdminRoles(): void
    {
        $user = new User('Admin User', new Email('admin@example.com'), 'hash123', 'ROLE_ADMIN');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = new Request();

        $response = $this->controller->onAuthenticationSuccess($request, $token);

        $data = json_decode($response->getContent(), true);
        $this->assertContains('ROLE_ADMIN', $data['roles']);
    }

    public function testOnAuthenticationFailureReturnsUnauthorized(): void
    {
        $exception = new AuthenticationException('Invalid credentials.');
        $request = new Request();

        $response = $this->controller->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid credentials', $data['error']);
    }

    public function testOnAuthenticationSuccessReturnsJsonContentType(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash123');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $this->controller->onAuthenticationSuccess(new Request(), $token);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }
}
