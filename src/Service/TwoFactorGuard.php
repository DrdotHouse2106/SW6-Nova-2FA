<?php declare(strict_types=1);

namespace Nova\TwoFactor\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Öffentliche Schnittstelle für andere Plugins (z. B. Nova Upload):
 * Hat das Kundenkonto 2FA eingerichtet, und wurde der Code in dieser Sitzung innerhalb der Frist bestätigt?
 */
class TwoFactorGuard
{
    public const SESSION_VERIFIED = 'novaTwoFactor.verified';
    public const SESSION_PENDING = 'novaTwoFactor.pendingSecret';
    public const SESSION_RECOVERY = 'novaTwoFactor.showRecoveryCodes';

    public function __construct(
        private readonly TwoFactorStore $store,
        private readonly SystemConfigService $config,
    ) {
    }

    public function isEnabled(string $customerId): bool
    {
        return $this->store->isEnabled($customerId);
    }

    public function isVerified(Request $request, string $customerId, ?string $salesChannelId = null): bool
    {
        if (!$request->hasSession() || !$this->isEnabled($customerId)) {
            return false;
        }

        $verified = $request->getSession()->get(self::SESSION_VERIFIED);
        if (!\is_array($verified) || ($verified['customerId'] ?? null) !== $customerId || !\is_int($verified['at'] ?? null)) {
            return false;
        }

        return time() - $verified['at'] < $this->ttlHours($salesChannelId) * 3600;
    }

    public function markVerified(Request $request, string $customerId): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_VERIFIED, ['customerId' => $customerId, 'at' => time()]);
        }
    }

    public function forget(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::SESSION_VERIFIED);
            $request->getSession()->remove(self::SESSION_PENDING);
        }
    }

    public function ttlHours(?string $salesChannelId): int
    {
        return max(1, $this->config->getInt('NovaTwoFactor.config.ttlHours', $salesChannelId) ?: 12);
    }
}
