<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

final class CollectCheckoutInformation
{
    /**
     * Validate and structure checkout information collected conversationally
     *
     * @param string $shippingAddress Full shipping address
     * @param string $paymentMethod Payment method (e.g., 'credit_card', 'paypal')
     * @param string $contactEmail Contact email
     * @param string|null $contactPhone Optional contact phone
     * @return array Validation result with structured data
     */
    public function execute(
        string $shippingAddress,
        string $paymentMethod,
        string $contactEmail,
        ?string $contactPhone = null
    ): array {
        $errors = [];

        // Validate shipping address
        if (strlen($shippingAddress) < 10) {
            $errors[] = 'La dirección de envío debe ser más detallada.';
        }

        // Validate payment method
        $validPaymentMethods = ['credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery'];
        if (!in_array($paymentMethod, $validPaymentMethods, true)) {
            $errors[] = 'Método de pago no válido.';
        }

        // Validate email
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no es válido.';
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return [
            'valid' => true,
            'data' => [
                'shippingAddress' => $shippingAddress,
                'paymentMethod' => $paymentMethod,
                'contactEmail' => $contactEmail,
                'contactPhone' => $contactPhone,
            ],
        ];
    }
}
