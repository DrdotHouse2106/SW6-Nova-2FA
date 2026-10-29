<?php declare(strict_types=1);

namespace Nova\TwoFactor\Tests\Unit;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Service\TwoFactorStore;
use Nova\TwoFactor\Subscriber\AdminLoginSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Z-3: Antwortet das Plugin selbst, gilt Shopwares Anmelde-Limit – sonst verrät die abweichende Antwort das Passwort.
 */
class AdminLoginRateLimitTest extends TestCase
{
    private const USER_ID = '0123456789abcdef0123456789abcdef';

    public function testWrongPasswordIsLeftToShopware(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects(static::never())->method('ensureAccepted');

        [$event, $original] = $this->event('falsch', '');
        $this->subscriber($limiter, 'ok')->onController($event);

        static::assertSame($original, $event->getController());
    }

    public function testCorrectPasswordWithoutCodeCountsAgainstLimit(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects(static::once())->method('ensureAccepted')->with(RateLimiter::OAUTH, 'admin-10.0.0.1');

        [$event] = $this->event('geheim', '');
        $this->subscriber($limiter, 'ok')->onController($event);

        static::assertSame('NOVA_2FA_REQUIRED', $this->errorCode($event));
    }

    public function testThrottledCorrectPasswordAnswersLikeShopware(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->method('ensureAccepted')->willThrowException(new RateLimitExceededException(time() + 10));

        [$event] = $this->event('geheim', '');

        try {
            $this->subscriber($limiter, 'ok')->onController($event);
            static::fail('Erwartet: 429 wie bei Shopware');
        } catch (\Throwable $exception) {
            static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : null);
        }
    }

    public function testWrongCodeCountsAgainstLimit(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects(static::once())->method('ensureAccepted');
        $limiter->expects(static::never())->method('reset');

        [$event] = $this->event('geheim', '000000');
        $this->subscriber($limiter, 'invalid')->onController($event);

        static::assertSame('NOVA_2FA_INVALID', $this->errorCode($event));
    }

    public function testCorrectCodeResetsLimitAndContinuesToShopware(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects(static::never())->method('ensureAccepted');
        $limiter->expects(static::once())->method('reset')->with(RateLimiter::OAUTH, 'admin-10.0.0.1');

        [$event, $original] = $this->event('geheim', '123456');
        $this->subscriber($limiter, 'ok')->onController($event);

        static::assertSame($original, $event->getController());
    }

    public function testPasswordAndCodeInOneFieldIsSplit(): void
    {
        $limiter = $this->createMock(RateLimiter::class);

        [$event, $original, $request] = $this->event('geheim123456', '');
        $this->subscriber($limiter, 'ok')->onController($event);

        static::assertSame($original, $event->getController());
        static::assertSame('geheim', $request->request->getString('password'));
    }

    private function subscriber(RateLimiter $limiter, string $checkResult): AdminLoginSubscriber
    {
        $store = $this->createMock(TwoFactorStore::class);
        $store->method('isEnabled')->willReturn(true);
        $store->method('check')->willReturn($checkResult);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => (string) hex2bin(self::USER_ID),
            'password' => password_hash('geheim', \PASSWORD_BCRYPT, ['cost' => 4]),
        ]);

        $subscriber = new AdminLoginSubscriber($store, $connection, $this->createMock(SystemConfigService::class));
        $subscriber->setRateLimiter($limiter);

        return $subscriber;
    }

    /**
     * @return array{0: ControllerEvent, 1: callable, 2: Request}
     */
    private function event(string $password, string $code): array
    {
        $request = new Request([], array_filter([
            'grant_type' => 'password',
            'username' => 'Admin',
            'password' => $password,
            'nova_otp' => $code,
        ]), ['_route' => AdminLoginSubscriber::ROUTE], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $original = static fn (): Response => new Response('shopware');
        $event = new ControllerEvent($this->createMock(HttpKernelInterface::class), $original, $request, HttpKernelInterface::MAIN_REQUEST);

        return [$event, $original, $request];
    }

    private function errorCode(ControllerEvent $event): ?string
    {
        $response = ($event->getController())();
        $data = json_decode((string) $response->getContent(), true);

        return $data['errors'][0]['code'] ?? null;
    }
}
