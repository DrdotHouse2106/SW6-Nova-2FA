<?php declare(strict_types=1);

namespace Nova\TwoFactor\Subscriber;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Service\TwoFactorStore;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Anmeldung in der Administration: Hat der Benutzer 2FA eingerichtet, verlangt der Token-Endpunkt
 * zusätzlich zum Passwort den Code aus der Authenticator-App (Feld „nova_otp“).
 *
 * Erst wenn das Passwort stimmt, wird nach dem Code gefragt bzw. ein falscher Code gezählt –
 * so lässt sich ohne Passwort weder erkennen, ob 2FA aktiv ist, noch das Konto sperren.
 *
 * Shopware lädt Plugin-Erweiterungen der Administration erst nach der Anmeldung – auf der normalen
 * Anmeldeseite gibt es daher kein eigenes Code-Feld. Deshalb gilt zusätzlich: Passwort und Code direkt
 * hintereinander ins Passwortfeld („MeinPasswort123456“, Leerzeichen dazwischen erlaubt; Wiederherstellungscode
 * mit Bindestrich und Leerzeichen: „MeinPasswort ABCDE-12345“).
 *
 * Antwortet das Plugin selbst (Code fehlt/falsch), läuft Shopwares Anmelde-Limit („oauth“) hier mit,
 * sonst ließe sich das Passwort eines 2FA-Kontos am abweichenden Fehler unbegrenzt erraten. Mit richtigem
 * Code wird das Limit zurückgesetzt (wie bei Shopware nach erfolgreicher Anmeldung).
 */
class AdminLoginSubscriber implements EventSubscriberInterface
{
    public const ROUTE = 'api.oauth.token';
    public const FIELD = 'nova_otp';

    private ?RateLimiter $rateLimiter = null;

    public function __construct(
        private readonly TwoFactorStore $userStore,
        private readonly Connection $connection,
        private readonly SystemConfigService $config,
    ) {
    }

    public function setRateLimiter(?RateLimiter $rateLimiter): void
    {
        $this->rateLimiter = $rateLimiter;
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
        if ($user === false) {
            return;
        }

        if (self::isApiUser($this->config, Uuid::fromBytesToHex((string) $user['id']))) {
            // Schnittstellen-Benutzer (z. B. ERP): normale Passwort-Anmeldung ohne Code
            return;
        }

        $hash = (string) $user['password'];
        $code = trim($request->request->getString(self::FIELD));

        if (!password_verify($password, $hash)) {
            // Passwort + Code in einem Feld?
            $split = self::splitPasswordAndCode($password);
            if ($split === null || !password_verify($split[0], $hash)) {
                // Falsches Passwort: Shopware antwortet wie gewohnt
                return;
            }

            $userId = Uuid::fromBytesToHex((string) $user['id']);
            if (!$this->userStore->isEnabled($userId)) {
                // Ohne 2FA bleibt das Passwort, wie es eingegeben wurde → Shopware lehnt es ab
                return;
            }

            $code = $split[1];
            // Shopware prüft danach nur noch das reine Passwort
            self::replacePassword($request, $split[0]);
        } else {
            $userId = Uuid::fromBytesToHex((string) $user['id']);
            if (!$this->userStore->isEnabled($userId)) {
                return;
            }
        }
        if ($code === '') {
            // Ab hier antwortet das Plugin statt Shopware: gleiches Limit wie dort (wirft wie dort 429)
            $this->ensureAccepted($request, $username);
            $this->deny($event, 'NOVA_2FA_REQUIRED', 'Zwei-Faktor-Anmeldung: Passwort und direkt dahinter den 6-stelligen Code aus der App eingeben.');

            return;
        }

        $result = $this->userStore->check(
            $userId,
            $code,
            $this->config->getInt('NovaTwoFactor.config.maxAttempts') ?: 5,
            $this->config->getInt('NovaTwoFactor.config.lockMinutes') ?: 15,
        );

        if ($result === 'ok' || $result === 'recovery') {
            // Vollständig angemeldet: Zähler zurück, Shopware prüft und zählt danach wie gewohnt
            $this->resetLimiter($request, $username);

            return;
        }

        // Falscher Code bei richtigem Passwort: zählt wie ein Fehlversuch bei Shopware
        $this->ensureAccepted($request, $username);

        if ($result === 'locked') {
            $this->deny($event, 'NOVA_2FA_LOCKED', 'Zu viele Fehlversuche. Bitte später erneut versuchen.');
        } else {
            $this->deny($event, 'NOVA_2FA_INVALID', 'Der Code ist ungültig.');
        }
    }

    /**
     * Schlüssel 1:1 wie in Shopwares AuthController::token() (6.7): Benutzername aus
     * $request->request->getString('username') nur kleingeschrieben (nicht getrimmt), IP aus getClientIp(),
     * kombiniert „<benutzer>-<ip>“. Weicht der Schlüssel ab, zählen Plugin und Shopware getrennt und das
     * Passwort-Orakel wäre wieder offen – daher keine eigene Normalisierung (kein trim, kein mb_strtolower).
     *
     * @return array{0: string, 1: string, 2: string} [Benutzer-IP, Benutzer, IP]
     */
    public static function limiterKeys(string $username, ?string $clientIp): array
    {
        $user = strtolower($username);
        $ip = (string) $clientIp;

        return [$user . '-' . $ip, $user, $ip];
    }

    private function ensureAccepted(Request $request, string $username): void
    {
        if ($this->rateLimiter === null) {
            return;
        }

        try {
            foreach ($this->limits($request, $username) as [$limit, $key, $optional]) {
                $optional
                    ? $this->rateLimiter->ensureAcceptedIfConfigured($limit, $key)
                    : $this->rateLimiter->ensureAccepted($limit, $key);
            }
        } catch (RateLimitExceededException $exception) {
            // Shopware 6.6/6.7: gleiche Antwort wie AuthController::token()
            throw method_exists(ApiException::class, 'notificationThrottled') // @phpstan-ignore function.alreadyNarrowedType
                ? ApiException::notificationThrottled($exception->getWaitTime(), $exception)
                : $exception;
        }
    }

    private function resetLimiter(Request $request, string $username): void
    {
        if ($this->rateLimiter === null) {
            return;
        }

        foreach ($this->limits($request, $username) as [$limit, $key, $optional]) {
            $optional
                ? $this->rateLimiter->resetIfConfigured($limit, $key)
                : $this->rateLimiter->reset($limit, $key);
        }
    }

    /**
     * Shopware 6.7 kennt zusätzlich Limits je Benutzer und je IP (nur wenn konfiguriert)
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function limits(Request $request, string $username): array
    {
        [$combined, $user, $ip] = self::limiterKeys($username, $request->getClientIp());
        $limits = [[RateLimiter::OAUTH, $combined, false]];
        if (method_exists(RateLimiter::class, 'ensureAcceptedIfConfigured')) { // @phpstan-ignore function.alreadyNarrowedType
            foreach (['OAUTH_USER' => $user, 'OAUTH_CLIENT' => $ip] as $name => $key) {
                $constant = RateLimiter::class . '::' . $name;
                if (\defined($constant)) {
                    $limits[] = [(string) \constant($constant), $key, true];
                }
            }
        }

        return $limits;
    }

    /** In den Einstellungen als Schnittstellen-Benutzer (ohne 2FA) ausgewählt? */
    public static function isApiUser(SystemConfigService $config, string $userId): bool
    {
        $ids = $config->get('NovaTwoFactor.config.apiUserIds');

        return \is_array($ids) && \in_array($userId, $ids, true);
    }

    /**
     * Passwort in der Anfrage ersetzen – in den Formulardaten UND im JSON-Text: Shopware reicht die Anfrage
     * über den PSR-Brückenbaustein an OAuth weiter, der bei JSON den ursprünglichen Text neu einliest.
     */
    public static function replacePassword(Request $request, string $password): void
    {
        $request->request->set('password', $password);

        $data = json_decode((string) $request->getContent(), true);
        if (\is_array($data) && \array_key_exists('password', $data)) {
            $data['password'] = $password;
            $json = (string) json_encode($data);
            // Der Inhalt ist in Symfony nicht per Setter änderbar
            (function () use ($json): void {
                $this->content = $json;
            })->call($request);
        }
    }

    /**
     * „Passwort123456“ / „Passwort 123456“ / „Passwort ABCDE-12345“ → [Passwort, Code]
     *
     * @return array{0: string, 1: string}|null
     */
    public static function splitPasswordAndCode(string $input): ?array
    {
        if (preg_match('/^(.+?) ?(\d{6})$/s', $input, $match)) {
            return [$match[1], $match[2]];
        }
        if (preg_match('/^(.+) ([A-Za-z0-9]{5}-[A-Za-z0-9]{5})$/s', $input, $match)) {
            return [$match[1], $match[2]];
        }

        return null;
    }

    private function deny(ControllerEvent $event, string $code, string $detail): void
    {
        $event->setController(static fn (): JsonResponse => new JsonResponse([
            'errors' => [['status' => '401', 'code' => $code, 'title' => 'Unauthorized', 'detail' => $detail]],
        ], 401));
    }
}
