<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\CollectCheckoutInformation;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    'CollectCheckoutInformationTool',
    'Collect and validate checkout information (shipping address, payment method, contact). Use this tool to prepare data before creating an order.'
)]
final class CollectCheckoutInformationTool
{
    public function __construct(
        private readonly CollectCheckoutInformation $collectCheckoutInformation,
        private readonly LoggerInterface $aiToolsLogger
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
        $this->aiToolsLogger->info('📋 CollectCheckoutInformationTool called', [
            'payment_method' => $paymentMethod,
            'has_phone' => $contactPhone !== null
        ]);
        
        try {
            $result = $this->collectCheckoutInformation->execute(
                $shippingAddress,
                $paymentMethod,
                $contactEmail,
                $contactPhone
            );

            if (!$result['valid']) {
                $this->aiToolsLogger->warning('⚠️ Checkout information validation failed', [
                    'errors' => $result['errors']
                ]);
                return [
                    'success' => false,
                    'valid' => false,
                    'errors' => $result['errors'],
                    'message' => 'There are errors in the provided information: ' . implode(' ', $result['errors']),
                ];
            }

            $this->aiToolsLogger->info('✅ Checkout information validated');
            
            return [
                'success' => true,
                'valid' => true,
                'checkoutInfo' => $result['data'],
                'message' => 'Checkout information validated successfully. Confirm to create the order.',
            ];
        } catch (\Exception $e) {
            $this->aiToolsLogger->error('❌ CollectCheckoutInformationTool failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'valid' => false,
                'message' => 'Could not validate information. Please try again.',
            ];
        }
    }
}
