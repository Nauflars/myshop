<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\CollectCheckoutInformation;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    'CollectCheckoutInformationTool',
    'Collect and validate checkout information (shipping address, payment method, contact). Use this tool to prepare data before creating an order.'
)]
final class CollectCheckoutInformationTool
{
    public function __construct(
        private readonly CollectCheckoutInformation $collectCheckoutInformation
    ) {
    }

    /**
     * @param string $shippingAddress Dirección completa de envío
     * @param string $paymentMethod Método de pago (credit_card, paypal, bank_transfer, cash_on_delivery)
     * @param string $contactEmail Correo electrónico de contacto
     * @param string|null $contactPhone Teléfono de contacto (opcional)
     */
    public function __invoke(
        string $shippingAddress,
        string $paymentMethod,
        string $contactEmail,
        ?string $contactPhone = null
    ): array {
        try {
            $result = $this->collectCheckoutInformation->execute(
                $shippingAddress,
                $paymentMethod,
                $contactEmail,
                $contactPhone
            );

            if (!$result['valid']) {
                return [
                    'success' => false,
                    'valid' => false,
                    'errors' => $result['errors'],
                    'message' => 'There are errors in the provided information: ' . implode(' ', $result['errors']),
                ];
            }

            return [
                'success' => true,
                'valid' => true,
                'checkoutInfo' => $result['data'],
                'message' => 'Checkout information validated successfully. Confirm to create the order.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'message' => 'Could not validate information. Please try again.',
            ];
        }
    }
}
