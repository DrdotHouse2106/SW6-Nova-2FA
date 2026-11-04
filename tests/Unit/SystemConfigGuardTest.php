<?php declare(strict_types=1);

namespace Nova\TwoFactor\Tests\Unit;

use Nova\TwoFactor\Service\TwoFactorStore;
use Nova\TwoFactor\Subscriber\AdminGuardSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * N-2F-1: Plugin-Einstellungen über /api/_action/system-config(/batch) nur mit Code – geprüft werden die
 * dekodierten Schlüssel, nicht der Rohtext. N-2F-2: Anmeldedaten im eigenen Profil nur mit Code.
 */
class SystemConfigGuardTest extends TestCase
{
    private const SAVE = 'api.action.core.save.system-config';
    private const BATCH = 'api.action.core.save.system-config.batch';

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function bypassAttempts(): iterable
    {
        yield 'klar' => [self::SAVE, 'application/json', '{"NovaTwoFactor.config.apiUserIds":["abc"]}'];
        yield 'JSON-Unicode-Escape' => [self::SAVE, 'application/json', '{"NovaTwoFactor.config.apiUserIds":["abc"]}'];
        yield 'JSON komplett escapt' => [self::SAVE, 'application/json', '{"NovaTwoFactor.config.adminRequired":false}'];
        yield 'Kleinschreibung' => [self::SAVE, 'application/json', '{"novatwofactor.config.adminRequired":false}'];
        yield 'Leerzeichen' => [self::SAVE, 'application/json', '{" NovaTwoFactor.config.adminRequired ":false}'];
        yield 'übergeordneter Schlüssel' => [self::SAVE, 'application/json', '{"NovaTwoFactor.config":{"adminRequired":false}}'];
        yield 'Wurzel' => [self::SAVE, 'application/json', '{"NovaTwoFactor":{"config":{"adminRequired":false}}}'];
        // PHP macht aus Punkten in Formular-Schlüsseln Unterstriche – verschachtelt landet es aber im Baum
        yield 'Formular URL-kodiert' => [self::SAVE, 'application/x-www-form-urlencoded', '%4EovaTwoFactor%5Bconfig%5D%5BadminRequired%5D=0'];
        yield 'Batch' => [self::BATCH, 'application/json', '{"null":{"core.basicInformation.shopName":"x","NovaTwoFactor.config.adminRequired":false}}'];
        yield 'Batch Verkaufskanal' => [self::BATCH, 'application/json', '{"0123456789abcdef0123456789abcdef":{"NovaTwoFactor.config.customerEnabled":true}}'];
    }

    #[DataProvider('bypassAttempts')]
    public function testPluginConfigNeedsConfirmation(string $route, string $contentType, string $body): void
    {
        static::assertTrue($this->guard()->isProtectedWrite($this->request($route, $contentType, $body), $route));
    }

    public function testOtherConfigPasses(): void
    {
        $guard = $this->guard();
        $body = '{"core.basicInformation.shopName":"Demo","NovaUpload.config.maxSize":5,"NovaTwoFactorX":1}';

        static::assertFalse($guard->isProtectedWrite($this->request(self::SAVE, 'application/json', $body), self::SAVE));
        static::assertFalse($guard->isProtectedWrite($this->request(self::BATCH, 'application/json', '{"null":' . $body . '}'), self::BATCH));
    }

    public function testPluginConfigKeyMatching(): void
    {
        static::assertTrue(AdminGuardSubscriber::isPluginConfigKey('NovaTwoFactor.config.apiUserIds'));
        static::assertTrue(AdminGuardSubscriber::isPluginConfigKey('NOVATWOFACTOR.CONFIG.ADMINREQUIRED'));
        static::assertTrue(AdminGuardSubscriber::isPluginConfigKey('NovaTwoFactor'));
        static::assertFalse(AdminGuardSubscriber::isPluginConfigKey('NovaTwoFactorX.config.a'));
        static::assertFalse(AdminGuardSubscriber::isPluginConfigKey('core.NovaTwoFactor.config'));
    }

    public function testChangeMeCredentialsNeedConfirmation(): void
    {
        $guard = $this->guard();
        $route = AdminGuardSubscriber::CHANGE_ME_ROUTE;

        static::assertTrue($guard->isProtectedWrite($this->request($route, 'application/json', '{"password":"neu"}'), $route));
        static::assertTrue($guard->isProtectedWrite($this->request($route, 'application/json', '{"email":"a@example.com"}'), $route));
        static::assertTrue($guard->isProtectedWrite($this->request($route, 'application/json', '{"username":"x"}'), $route));
        static::assertFalse($guard->isProtectedWrite($this->request($route, 'application/json', '{"firstName":"Max","timeZone":"Europe/Berlin"}'), $route));
    }

    public function testCoreAccessDeleteRoutesNeedConfirmation(): void
    {
        $guard = $this->guard();

        static::assertTrue($guard->isProtectedWrite(new Request(), 'api.user_access_keys.delete'));
        static::assertTrue($guard->isProtectedWrite(new Request(), 'api.user_role.delete'));
    }

    /**
     * Wie Shopware die Anfrage zum Zeitpunkt des Controller-Events vorliegen hat: JSON ist bereits per
     * json_decode in $request->request übernommen (JsonRequestTransformerListener), Formulardaten per PHP dekodiert.
     */
    private function request(string $route, string $contentType, string $body): Request
    {
        if ($contentType === 'application/json') {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } else {
            parse_str($body, $data);
        }
        static::assertIsArray($data);

        return new Request([], $data, ['_route' => $route], [], [], ['CONTENT_TYPE' => $contentType, 'REQUEST_METHOD' => 'POST'], $body);
    }

    private function guard(): AdminGuardSubscriber
    {
        return new AdminGuardSubscriber(
            $this->createMock(TwoFactorStore::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(CacheItemPoolInterface::class),
        );
    }
}
