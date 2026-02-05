<?php

namespace App\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('user/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // Symfony security handles logout
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/products', name: 'product_list', methods: ['GET'])]
    public function products(): Response
    {
        return $this->render('product/list.html.twig');
    }

    #[Route('/products/{id}', name: 'product_show', methods: ['GET'])]
    public function productShow(string $id): Response
    {
        return $this->render('product/show.html.twig', [
            'productId' => $id,
        ]);
    }

    #[Route('/cart', name: 'cart_view', methods: ['GET'])]
    public function cart(): Response
    {
        return $this->render('cart/view.html.twig');
    }

    #[Route('/checkout', name: 'checkout_view', methods: ['GET'])]
    public function checkout(): Response
    {
        return $this->render('checkout/index.html.twig');
    }

    #[Route('/orders', name: 'order_list', methods: ['GET'])]
    public function orders(): Response
    {
        return $this->render('order/list.html.twig');
    }
}
