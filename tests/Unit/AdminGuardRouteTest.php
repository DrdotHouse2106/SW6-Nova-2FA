<?php declare(strict_types=1);

namespace Nova\TwoFactor\Tests\Unit;

use Nova\TwoFactor\Service\TwoFactorStore;
use Nova\TwoFactor\Subscriber\AdminGuardSubscriber;
use Nova\TwoFactor\Subscriber\AdminLoginSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

class AdminGuardRouteTest extends TestCase
{
    public function testResetRoutesNeedConfirmation(): void
    {
        $guard = $this->guard();
        $request = new Request();

        static::assertTrue($guard->isProtectedWrite($request, 'api.action.nova_2fa.user.reset'));
        static::assertTrue($guard->isProtectedWrite($request, 'api.action.nova_2fa.customer.reset'));
        static::assertTrue($guard->isProtectedWrite($request, 'api.user.create'));
        static::assertTrue($guard->isProtectedWrite($request, 'api.integration.update'));
        static::assertFalse($guard->isProtectedWrite($request, 'api.action.nova_2fa.user.status'));
        static::assertFalse($guard->isProtectedWrite($request, 'api.action.nova_2fa.me.setup'));
        static::assertFalse($guard->isProtectedWrite($request, 'api.product.update'));
    }

    public function testEntityWriteRoutes(): void
    {
        static::assertTrue(AdminGuardSubscriber::isEntityWriteRoute('api.media.create'));
        static::assertTrue(AdminGuardSubscriber::isEntityWriteRoute('api.system_config.update'));
        static::assertTrue(AdminGuardSubscriber::isEntityWriteRoute('api.action.sync'));
        static::assertTrue(AdminGuardSubscriber::isEntityWriteRoute('api.clone'));
        static::assertFalse(AdminGuardSubscriber::isEntityWriteRoute('api.product.search'));
        static::assertFalse(AdminGuardSubscriber::isEntityWriteRoute('api.extension.install'));
        static::assertFalse(AdminGuardSubscriber::isEntityWriteRoute('api.oauth.token'));
    }

    public function testLimiterKeysMatchShopware(): void
    {
        static::assertSame(['admin-1.2.3.4', 'admin', '1.2.3.4'], AdminLoginSubscriber::limiterKeys('Admin', '1.2.3.4'));
        static::assertSame(['admin-', 'admin', ''], AdminLoginSubscriber::limiterKeys('admin', null));
        // Wie AuthController::token() in 6.7: strtolower (nur ASCII) ohne trim
        static::assertSame(['max.muster Ü-::1', 'max.muster Ü', '::1'], AdminLoginSubscriber::limiterKeys('Max.Muster Ü', '::1'));
        static::assertSame([' admin-1.2.3.4', ' admin', '1.2.3.4'], AdminLoginSubscriber::limiterKeys(' ADMIN', '1.2.3.4'));
    }

    public function testSplitPasswordAndCode(): void
    {
        static::assertSame(['Passwort', '123456'], AdminLoginSubscriber::splitPasswordAndCode('Passwort123456'));
        static::assertSame(['Passwort', '123456'], AdminLoginSubscriber::splitPasswordAndCode('Passwort 123456'));
        static::assertSame(['Passwort', 'ABCDE-12345'], AdminLoginSubscriber::splitPasswordAndCode('Passwort ABCDE-12345'));
        static::assertNull(AdminLoginSubscriber::splitPasswordAndCode('Passwort'));
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
