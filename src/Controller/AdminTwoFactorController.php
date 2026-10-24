<?php declare(strict_types=1);

namespace Nova\TwoFactor\Controller;

use Nova\TwoFactor\Service\SecretCipher;
use Nova\TwoFactor\Service\Totp;
use Nova\TwoFactor\Service\TwoFactorStore;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API: 2FA des angemeldeten Admin-Benutzers (Profil), Status/Zurücksetzen für andere Benutzer
 * (Einstellungen → Benutzer) und für Kunden (Kunde → Allgemein).
 */
#[Route(defaults: ['_routeScope' => ['api']], requirements: ['id' => '[0-9a-f]{32}'])]
class AdminTwoFactorController
{
    private const PENDING_TTL = 900;

    public function __construct(
        private readonly TwoFactorStore $store,
        private readonly TwoFactorStore $userStore,
        private readonly SecretCipher $cipher,
        private readonly CacheItemPoolInterface $cache,
        private readonly SystemConfigService $config,
    ) {
    }

    // ---- Eigenes Profil ----

    #[Route(path: '/api/_action/nova-2fa/me', name: 'api.action.nova_2fa.me', methods: ['GET'])]
    public function me(Context $context): JsonResponse
    {
        $userId = $this->userId($context);

        return $userId === null ? $this->denied() : new JsonResponse(['enabled' => $this->userStore->isEnabled($userId), 'status' => $this->userStore->status($userId)]);
    }

    #[Route(path: '/api/_action/nova-2fa/me/setup', name: 'api.action.nova_2fa.me.setup', methods: ['POST'])]
    public function setup(Request $request, Context $context): JsonResponse
    {
        $userId = $this->userId($context);
        if ($userId === null) {
            return $this->denied();
        }
        if ($this->userStore->isEnabled($userId)) {
            return new JsonResponse(['message' => 'Zwei-Faktor-Anmeldung ist bereits eingerichtet.'], 400);
        }

        $secret = Totp::generateSecret();
        $item = $this->cache->getItem($this->pendingKey($userId));
        $item->set($this->cipher->encrypt($secret));
        $item->expiresAfter(self::PENDING_TTL);
        $this->cache->save($item);

        // In der App erkennbar als „<Domain> Admin“
        return new JsonResponse(['secret' => $secret, 'uri' => Totp::uri($secret, 'Administration', $request->getHost() . ' Admin')]);
    }

    #[Route(path: '/api/_action/nova-2fa/me/confirm', name: 'api.action.nova_2fa.me.confirm', methods: ['POST'])]
    public function confirm(Request $request, Context $context): JsonResponse
    {
        $userId = $this->userId($context);
        if ($userId === null) {
            return $this->denied();
        }

        $item = $this->cache->getItem($this->pendingKey($userId));
        $secret = $item->isHit() && \is_string($item->get()) ? $this->cipher->decrypt($item->get()) : null;
        if ($secret === null || Totp::verify($secret, $request->request->getString('code')) === null) {
            return new JsonResponse(['message' => 'Der Code ist ungültig.'], 400);
        }

        $this->cache->deleteItem($this->pendingKey($userId));

        return new JsonResponse(['recoveryCodes' => $this->userStore->enable($userId, $secret)]);
    }

    #[Route(path: '/api/_action/nova-2fa/me/disable', name: 'api.action.nova_2fa.me.disable', methods: ['POST'])]
    public function disable(Request $request, Context $context): JsonResponse
    {
        $userId = $this->userId($context);
        if ($userId === null) {
            return $this->denied();
        }

        $result = $this->checkOwn($userId, $request);
        if ($result !== null) {
            return $result;
        }
        $this->userStore->disable($userId);

        return new JsonResponse(['enabled' => false]);
    }

    #[Route(path: '/api/_action/nova-2fa/me/recovery', name: 'api.action.nova_2fa.me.recovery', methods: ['POST'])]
    public function recovery(Request $request, Context $context): JsonResponse
    {
        $userId = $this->userId($context);
        if ($userId === null) {
            return $this->denied();
        }

        $result = $this->checkOwn($userId, $request);

        return $result ?? new JsonResponse(['recoveryCodes' => $this->userStore->regenerateRecoveryCodes($userId)]);
    }

    // ---- Andere Admin-Benutzer ----

    #[Route(path: '/api/_action/nova-2fa/user/{id}', name: 'api.action.nova_2fa.user.status', methods: ['GET'])]
    public function userStatus(string $id, Context $context): JsonResponse
    {
        if (!$this->allowed($context, 'user:read')) {
            return $this->denied();
        }

        return new JsonResponse(['enabled' => $this->userStore->isEnabled($id), 'status' => $this->userStore->status($id)]);
    }

    #[Route(path: '/api/_action/nova-2fa/user/{id}', name: 'api.action.nova_2fa.user.reset', methods: ['DELETE'])]
    public function userReset(string $id, Context $context): JsonResponse
    {
        if (!$this->allowed($context, 'user:update')) {
            return $this->denied();
        }
        $this->userStore->disable($id);

        return new JsonResponse(['enabled' => false]);
    }

    // ---- Kunden ----

    #[Route(path: '/api/_action/nova-2fa/customer/{id}', name: 'api.action.nova_2fa.customer.status', methods: ['GET'])]
    public function customerStatus(string $id, Context $context): JsonResponse
    {
        if (!$this->allowed($context, 'customer:read')) {
            return $this->denied();
        }

        return new JsonResponse(['enabled' => $this->store->isEnabled($id), 'status' => $this->store->status($id)]);
    }

    #[Route(path: '/api/_action/nova-2fa/customer/{id}', name: 'api.action.nova_2fa.customer.reset', methods: ['DELETE'])]
    public function customerReset(string $id, Context $context): JsonResponse
    {
        if (!$this->allowed($context, 'customer:update')) {
            return $this->denied();
        }
        $this->store->disable($id);

        return new JsonResponse(['enabled' => false]);
    }

    private function checkOwn(string $userId, Request $request): ?JsonResponse
    {
        $result = $this->userStore->check(
            $userId,
            $request->request->getString('code'),
            $this->config->getInt('NovaTwoFactor.config.maxAttempts') ?: 5,
            $this->config->getInt('NovaTwoFactor.config.lockMinutes') ?: 15,
        );

        return match ($result) {
            'ok', 'recovery' => null,
            'locked' => new JsonResponse(['message' => 'Zu viele Fehlversuche. Bitte später erneut versuchen.'], 429),
            default => new JsonResponse(['message' => 'Der Code ist ungültig.'], 400),
        };
    }

    private function userId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }

    private function allowed(Context $context, string $privilege): bool
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource && $source->getUserId() !== null && $source->isAllowed($privilege);
    }

    private function pendingKey(string $userId): string
    {
        return 'nova_two_factor_pending_' . $userId;
    }

    private function denied(): JsonResponse
    {
        return new JsonResponse(['message' => 'Keine Berechtigung.'], 403);
    }
}
