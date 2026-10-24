<?php declare(strict_types=1);

namespace Nova\TwoFactor\Subscriber;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Service\TwoFactorStore;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Anmeldung in der Administration: Hat der Benutzer 2FA eingerichtet, verlangt der Token-Endpunkt
 * zusätzlich zum Passwort den Code aus der Authenticator-App (Feld „nova_otp“).
 *
 * Erst wenn das Passwort stimmt, wird nach dem Code gefragt bzw. ein falscher Code gezählt –
 * so lässt sich ohne Passwort weder erkennen, ob 2FA aktiv ist, noch das Konto sperren.
 */
class AdminLoginSubscriber implements EventSubscriberInterface
{
    public const ROUTE = 'api.oauth.token';
    public const FIELD = 'nova_otp';

    public function __construct(
        private readonly TwoFactorStore $userStore,
        private readonly Connection $connection,
        private readonly SystemConfigService $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onController', 10]];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->attributes->get('_route') !== self::ROUTE || $request->request->getString('grant_type') !== 'password') {
            return;
        }

        $username = $request->request->getString('username');
        $password = $request->request->getString('password');
        if ($username === '' || $password === '') {
            return;
        }

        $user = $this->connection->fetchAssociative('SELECT `id`, `password` FROM `user` WHERE `username` = :username', ['username' => $username]);
        // Falsches Passwort oder unbekannt: Shopware antwortet wie gewohnt
        if ($user === false || !password_verify($password, (string) $user['password'])) {
            return;
        }

        $userId = Uuid::fromBytesToHex((string) $user['id']);
        if (!$this->userStore->isEnabled($userId)) {
            return;
        }

        $code = trim($request->request->getString(self::FIELD));
        if ($code === '') {
            $this->deny($event, 'NOVA_2FA_REQUIRED', 'Bitte den Code aus der Authenticator-App eingeben.');

            return;
        }

        $result = $this->userStore->check(
            $userId,
            $code,
            $this->config->getInt('NovaTwoFactor.config.maxAttempts') ?: 5,
            $this->config->getInt('NovaTwoFactor.config.lockMinutes') ?: 15,
        );

        if ($result === 'locked') {
            $this->deny($event, 'NOVA_2FA_LOCKED', 'Zu viele Fehlversuche. Bitte später erneut versuchen.');
        } elseif ($result !== 'ok' && $result !== 'recovery') {
            $this->deny($event, 'NOVA_2FA_INVALID', 'Der Code ist ungültig.');
        }
    }

    private function deny(ControllerEvent $event, string $code, string $detail): void
    {
        $event->setController(static fn (): JsonResponse => new JsonResponse([
            'errors' => [['status' => '401', 'code' => $code, 'title' => 'Unauthorized', 'detail' => $detail]],
        ], 401));
    }
}
