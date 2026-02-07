<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\UseCase\Admin\UpdateProductStock;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminUpdateProductStockTool',
    'Update product stock. Modes: "set" (absolute value), "add" (increment), "subtract" (decrement). Requires administrator confirmation before execution. ONLY for ADMIN users.'
)]
final class AdminUpdateProductStockTool
{
    public function __construct(
        private readonly UpdateProductStock $updateProductStock,
        private readonly Security $security
    ) {
    }

    /**
     * Update product stock with validation
     *
     * @param string $productId UUID del producto a actualizar
     * @param int $quantity Cantidad a establecer/añadir/restar
     * @param string $mode Modo de actualización: "set" (valor absoluto), "add" (incrementar), "subtract" (decrementar)
     * @param string|null $reason Razón opcional para el cambio de stock
     * @param bool $confirmed Confirmación explícita del administrador (requerida)
     */
    public function __invoke(
        string $productId,
        int $quantity,
        string $mode = 'set',
        ?string $reason = null,
        bool $confirmed = false
    ): array {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Acceso denegado. Solo administradores pueden actualizar el inventario.',
            ];
        }

        // Validate mode
        if (!in_array($mode, ['set', 'add', 'subtract'], true)) {
            return [
                'success' => false,
                'error' => "Modo inválido: '$mode'. Debe ser 'set', 'add' o 'subtract'.",
            ];
        }

        // Check if confirmation is required
        if (!$confirmed) {
            $modeText = match ($mode) {
                'set' => "establecer en",
                'add' => "incrementar en",
                'subtract' => "decrementar en",
            };

            return [
                'success' => false,
                'requires_confirmation' => true,
                'message' => "¿Confirmas que deseas {$modeText} {$quantity} unidades el stock del producto?\n" .
                    ($reason ? "Razón: {$reason}\n" : "") .
                    "\nResponde 'sí', 'confirmar' o 'adelante' para ejecutar la acción.",
            ];
        }

        try {
            $result = $this->updateProductStock->execute(
                productId: $productId,
                quantity: $quantity,
                mode: $mode,
                reason: $reason
            );

            return [
                'success' => true,
                'product' => $result['product'],
                'old_stock' => $result['old_stock'],
                'new_stock' => $result['new_stock'],
                'message' => $result['message'],
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar stock: ' . $e->getMessage(),
            ];
        }
    }
}
