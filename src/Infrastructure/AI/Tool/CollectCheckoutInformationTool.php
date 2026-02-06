<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\CollectCheckoutInformation;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    'CollectCheckoutInformationTool',
    'Recopilar y validar información de checkout (dirección de envío, método de pago, contacto). Usa esta herramienta para preparar los datos antes de crear un pedido.'
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
                    'message' => 'Hay errores en la información proporcionada: ' . implode(' ', $result['errors']),
                ];
            }

            return [
                'success' => true,
                'valid' => true,
                'checkoutInfo' => $result['data'],
                'message' => 'Información de checkout validada correctamente. Confirma para crear el pedido.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'message' => 'No se pudo validar la información. Por favor intenta de nuevo.',
            ];
        }
    }
}
