<?php declare(strict_types=1);

namespace Nova\TwoFactor\Subscriber;

use Nova\TwoFactor\Service\TwoFactorStore;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Schutz der Administration:
 *
 * 1. Zugänge (Benutzer, Rollen, Integrationen/API-Zugänge, Zugangsschlüssel) anlegen, ändern oder löschen
 *    nur mit frisch bestätigtem App-Code (Kopfzeile „x-nova-2fa-code“, danach 5 Minuten gültig).
 * 2. Mit „2FA für die Administration verpflichtend“: Benutzer ohne eingerichtete 2FA dürfen nichts ändern,
 *    bis sie 2FA im Profil eingerichtet haben; Integrationen dürfen keine Zugänge anlegen.
 */
class AdminGuardSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'x-nova-2fa-code';
    public const CONFIRM_TTL = 300;

    /** Entitäten, über die man sich Zugang zur Administration oder API verschafft */
    public const PROTECTED_ENTITIES = ['user', 'acl_role', 'integration', 'user_access_key', 'acl_user_role', 'integration_role'];

    /** Eigene Aktionen, die wie Zugangs-Änderungen behandelt werden */
    public const PROTECTED_ROUTES = ['api.action.nova_2fa.user.reset', 'api.action.nova_2fa.customer.reset'];

    /** Erlaubt ohne eingerichtete 2FA (Pflicht-Modus): Einrichtung, Anmeldung, Profil */
    private const SETUP_ROUTE_PREFIXES = ['api.action.nova_2fa.me', 'api.oauth.', 'api.info.me', 'api.user.logout', 'api.action.user.logout'];

    public function __construct(
        private readonly TwoFactorStore $userStore,
        private readonly SystemConfigService $config,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Nach Kontext-Auflösung (-10) und Scope-Prüfung (-20)
        return [KernelEvents::CONTROLLER => ['onController', -30]];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$event->isMainRequest() || !$context instanceof Context || !$context->getSource() instanceof AdminApiSource) {
            return;
        }

        $source = $context->getSource();
        $route = (string) $request->attributes->get('_route');
        $userId = $source->getUserId();

        if ($this->isProtectedWrite($request, $route)) {
            $denial = $this->authorizeAccessWrite($request, $source);
            if ($denial !== null) {
                $this->deny($event, $denial[0], $denial[1]);
            }

            return;
        }

        // Code für verschachtelte Zugangs-Änderungen (AccessWriteGuardSubscriber) schon hier prüfen:
        // außerhalb der Schreib-Transaktion, damit Fehlversuche nicht mit zurückgerollt werden
        if ($userId !== null
            && self::isEntityWriteRoute($route)
            && trim((string) $request->headers->get(self::HEADER, '')) !== ''
            && !AdminLoginSubscriber::isApiUser($this->config, $userId)
            && $this->userStore->isEnabled($userId)
            && !$this->isConfirmed($userId)) {
            $denial = $this->confirmWithCode($request, $userId);
            if ($denial !== null) {
                $this->deny($event, $denial[0], $denial[1]);

                return;
            }
        }

        // Pflicht-Modus: Benutzer ohne 2FA dürfen bis zur Einrichtung nichts ändern
        if ($userId !== null
            && !AdminLoginSubscriber::isApiUser($this->config, $userId)
            && $this->config->getBool('NovaTwoFactor.config.adminRequired')
            && $this->isWrite($request, $route)
            && !$this->isSetupRoute($route)
            && !$this->userStore->isEnabled($userId)) {
            $this->deny($event, 'NOVA_2FA_SETUP_REQUIRED', 'Bitte zuerst die Zwei-Faktor-Anmeldung im Profil einrichten.');
        }
    }

    /**
     * Darf diese Anmeldung Zugänge (Benutzer, Rollen, Integrationen …) ändern? Prüft bei Bedarf den Code aus der
     * Kopfzeile ($checkCode) und merkt sich die Bestätigung 5 Minuten. Auch für Schreibvorgänge am DAL
     * (AccessWriteGuardSubscriber, dort ohne Codeprüfung – die passiert vorher in onController).
     *
     * @return array{0: string, 1: string}|null Ablehnung (Fehlercode, Text) oder null = erlaubt
     */
    public function authorizeAccessWrite(Request $request, AdminApiSource $source, bool $checkCode = true): ?array
    {
        $required = $this->config->getBool('NovaTwoFactor.config.adminRequired');
        $userId = $source->getUserId();

        // Integration (API-Zugang ohne Benutzer) oder Schnittstellen-Benutzer: wie eine Integration behandeln
        if ($userId === null || AdminLoginSubscriber::isApiUser($this->config, $userId)) {
            if (!$required && $userId === null) {
                return null;
            }

            return ['NOVA_2FA_INTEGRATION_DENIED', $userId !== null
                ? 'Schnittstellen-Benutzer dürfen keine Benutzer, Rollen, API-Zugänge oder die Zwei-Faktor-Einstellungen ändern.'
                : 'Integrationen dürfen keine Benutzer, Rollen oder API-Zugänge anlegen oder ändern, solange die Zwei-Faktor-Anmeldung für die Administration verpflichtend ist.'];
        }

        if (!$this->userStore->isEnabled($userId)) {
            return $required ? ['NOVA_2FA_SETUP_REQUIRED', 'Bitte zuerst die Zwei-Faktor-Anmeldung im Profil einrichten.'] : null;
        }

        if ($this->isConfirmed($userId)) {
            return null;
        }

        return $checkCode
            ? $this->confirmWithCode($request, $userId)
            : ['NOVA_2FA_CONFIRM_REQUIRED', 'Bitte mit dem Code aus der Authenticator-App bestätigen.'];
    }

    /**
     * Code aus der Kopfzeile prüfen und die Bestätigung 5 Minuten merken
     *
     * @return array{0: string, 1: string}|null
     */
    private function confirmWithCode(Request $request, string $userId): ?array
    {
        $code = trim((string) $request->headers->get(self::HEADER, ''));
        if ($code === '') {
            return ['NOVA_2FA_CONFIRM_REQUIRED', 'Bitte mit dem Code aus der Authenticator-App bestätigen.'];
        }

        $result = $this->userStore->check(
            $userId,
            $code,
            $this->config->getInt('NovaTwoFactor.config.maxAttempts') ?: 5,
            $this->config->getInt('NovaTwoFactor.config.lockMinutes') ?: 15,
        );

        if ($result === 'ok' || $result === 'recovery') {
            $item = $this->cache->getItem($this->confirmKey($userId));
            $item->set(time());
            $item->expiresAfter(self::CONFIRM_TTL);
            $this->cache->save($item);

            return null;
        }

        return $result === 'locked'
            ? ['NOVA_2FA_LOCKED', 'Zu viele Fehlversuche. Bitte später erneut versuchen.']
            : ['NOVA_2FA_CONFIRM_INVALID', 'Der Code ist ungültig.'];
    }

    public function isProtectedWrite(Request $request, string $route): bool
    {
        // 2FA anderer Benutzer bzw. von Kunden zurücksetzen
        if (\in_array($route, self::PROTECTED_ROUTES, true)) {
            return true;
        }

        if (preg_match('/^api\.([a-z_]+)\.(create|update|delete)$/', $route, $match)) {
            return \in_array($match[1], self::PROTECTED_ENTITIES, true);
        }

        // Einstellungen dieses Plugins (z. B. Liste der Schnittstellen-Benutzer) nur mit Code ändern
        if (str_starts_with($route, 'api.action.core.save.system-config')
            && str_contains((string) $request->getContent(), 'NovaTwoFactor.config.')) {
            return true;
        }

        if ($route === 'api.action.sync') {
            $payload = json_decode((string) $request->getContent(), true);
            foreach (\is_array($payload) ? $payload : [] as $operation) {
                if (\is_array($operation) && \in_array($operation['entity'] ?? null, self::PROTECTED_ENTITIES, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Allgemeine Entity-Routen (auch verschachtelt), Sync und Klonen */
    public static function isEntityWriteRoute(string $route): bool
    {
        return (bool) preg_match('/^api\.[a-z_]+\.(create|update|delete)$/', $route)
            || \in_array($route, ['api.action.sync', 'api.clone'], true);
    }

    /** Schreibender Zugriff, der ohne 2FA im Pflicht-Modus gesperrt wird (Lesen und Suchen bleiben möglich) */
    public function isWrite(Request $request, string $route): bool
    {
        return self::isEntityWriteRoute($route)
            || str_starts_with($route, 'api.extension.')
            || str_starts_with($route, 'api.action.plugin')
            || str_starts_with($route, 'api.action.dh_github_updater.');
    }

    private function isSetupRoute(string $route): bool
    {
        foreach (self::SETUP_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isConfirmed(string $userId): bool
    {
        $item = $this->cache->getItem($this->confirmKey($userId));

        return $item->isHit() && \is_int($item->get()) && time() - $item->get() < self::CONFIRM_TTL;
    }

    private function confirmKey(string $userId): string
    {
        return 'nova_two_factor_confirmed_' . $userId;
    }

    private function deny(ControllerEvent $event, string $code, string $detail): void
    {
        $event->setController(static fn (): JsonResponse => new JsonResponse([
            'errors' => [['status' => '403', 'code' => $code, 'title' => 'Forbidden', 'detail' => $detail]],
        ], 403));
    }
}
