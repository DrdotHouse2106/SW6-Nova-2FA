<?php declare(strict_types=1);

namespace Nova\TwoFactor\Subscriber;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Service\TwoFactorStore;
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
        if ($user === false) {
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
            $this->deny($event, 'NOVA_2FA_REQUIRED', 'Zwei-Faktor-Anmeldung: Passwort und direkt dahinter den 6-stelligen Code aus der App eingeben.');

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
