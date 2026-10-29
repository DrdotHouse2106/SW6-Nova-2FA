<?php declare(strict_types=1);

namespace Nova\TwoFactor\Tests\Unit;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Exception\TwoFactorException;
use Nova\TwoFactor\Subscriber\AccessWriteGuardSubscriber;
use Nova\TwoFactor\Subscriber\AdminGuardSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Z-6: Zugangs-Entitäten auch über verschachtelte Schreibvorgänge und die Entität system_config schützen.
 */
class AccessWriteGuardTest extends TestCase
{
    public function testNestedUserWriteNeedsConfirmation(): void
    {
        $this->expectException(TwoFactorException::class);
        $this->write('api.media.create', [$this->command('media'), $this->command('user')], deny: true);
    }

    public function testPluginConfigAsEntityNeedsConfirmation(): void
    {
        $this->expectException(TwoFactorException::class);
        $this->write('api.system_config.create', [$this->command('system_config', ['configuration_key' => 'NovaTwoFactor.config.apiUserIds'])], deny: true);
    }

    public function testPluginConfigUpdateByIdIsLookedUp(): void
    {
        $this->expectException(TwoFactorException::class);
        $this->write('api.system_config.update', [$this->command('system_config', [], ['id' => 'x'])], deny: true, storedKey: 'NovaTwoFactor.config.adminRequired');
    }

    public function testOtherWritesPass(): void
    {
        $this->write('api.action.sync', [$this->command('product'), $this->command('system_config', ['configuration_key' => 'core.basicInformation.shopName'])], deny: true);
        $this->addToAssertionCount(1);
    }

    public function testDedicatedActionRoutesPass(): void
    {
        // z. B. App-Installation legt Integration und Rolle selbst an
        $this->write('api.extension.install', [$this->command('integration')], deny: true);
        $this->addToAssertionCount(1);
    }

    public function testConfirmedWritePasses(): void
    {
        $this->write('api.action.sync', [$this->command('acl_role')], deny: false);
        $this->addToAssertionCount(1);
    }

    public function testSystemContextIsIgnored(): void
    {
        $this->write('api.action.sync', [$this->command('user')], deny: true, source: new SystemSource());
        $this->addToAssertionCount(1);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function write(string $route, array $commands, bool $deny, ?object $source = null, string $storedKey = ''): void
    {
        $guard = $this->createMock(AdminGuardSubscriber::class);
        $guard->method('authorizeAccessWrite')->willReturn($deny ? ['NOVA_2FA_CONFIRM_REQUIRED', 'Code'] : null);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($storedKey);

        $stack = new RequestStack();
        $stack->push(new Request([], [], ['_route' => $route]));

        $event = $this->createMock(PreWriteValidationEvent::class);
        $event->method('getContext')->willReturn(new Context($source ?? new AdminApiSource('0123456789abcdef0123456789abcdef')));
        $event->method('getCommands')->willReturn($commands);

        (new AccessWriteGuardSubscriber($guard, $stack, $connection))->onPreWrite($event);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $primaryKey
     */
    private function command(string $entity, array $payload = [], array $primaryKey = []): WriteCommand
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn($entity);
        $command->method('getPayload')->willReturn($payload);
        $command->method('getPrimaryKey')->willReturn($primaryKey);

        return $command;
    }
}
