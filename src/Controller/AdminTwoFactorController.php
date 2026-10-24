<?php declare(strict_types=1);

namespace Nova\TwoFactor\Controller;

use Nova\TwoFactor\Service\TwoFactorStore;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration → Kunde: Status der 2FA und Zurücksetzen (z. B. Handy verloren, keine Wiederherstellungscodes).
 */
#[Route(defaults: ['_routeScope' => ['api']], requirements: ['customerId' => '[0-9a-f]{32}'])]
class AdminTwoFactorController
{
    public function __construct(private readonly TwoFactorStore $store)
    {
    }

    #[Route(path: '/api/_action/nova-2fa/{customerId}', name: 'api.action.nova_2fa.status', methods: ['GET'])]
    public function status(string $customerId, Context $context): JsonResponse
    {
        if (!$this->allowed($context, 'customer:read')) {
            return new JsonResponse(['message' => 'Keine Berechtigung.'], 403);
        }

        return new JsonResponse(['enabled' => $this->store->isEnabled($customerId), 'status' => $this->store->status($customerId)]);
    }

    #[Route(path: '/api/_action/nova-2fa/{customerId}', name: 'api.action.nova_2fa.reset', methods: ['DELETE'])]
    public function reset(string $customerId, Context $context): JsonResponse
    {
        if (!$this->allowed($context, 'customer:update')) {
            return new JsonResponse(['message' => 'Keine Berechtigung.'], 403);
        }

        $this->store->disable($customerId);

        return new JsonResponse(['enabled' => false]);
    }

    private function allowed(Context $context, string $privilege): bool
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource && $source->getUserId() !== null && $source->isAllowed($privilege);
    }
}
