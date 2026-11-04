<?php declare(strict_types=1);

namespace Nova\TwoFactor\Subscriber;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Exception\TwoFactorException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Zweite Sperre für Zugangs-Entitäten direkt am DAL: Über die allgemeinen Entity-Routen lassen sich Benutzer,
 * Rollen oder Integrationen auch verschachtelt schreiben (z. B. POST /api/media/{id}/user, sync mit
 * eingebetteten Datensätzen) und die Plugin-Einstellungen als Entität „system_config“ ändern.
 * Es gelten dieselben Regeln wie in AdminGuardSubscriber (frischer Code, keine Integrationen/Schnittstellen-Benutzer).
 */
class AccessWriteGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AdminGuardSubscriber $guard,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'onPreWrite'];
    }

    public function onPreWrite(PreWriteValidationEvent $event): void
    {
        $source = $event->getContext()->getSource();
        if (!$source instanceof AdminApiSource || ($source->getUserId() === null && $source->getIntegrationId() === null)) {
            return;
        }

        // Nur Anfragen über die allgemeinen Entity-Routen; eigene Aktionen (z. B. App-Installation) bleiben unberührt
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !AdminGuardSubscriber::isEntityWriteRoute((string) $request->attributes->get('_route'))) {
            return;
        }

        if (!$this->touchesAccess($event->getCommands())) {
            return;
        }

        $denial = $this->guard->authorizeAccessWrite($request, $source, false);
        if ($denial !== null) {
            throw TwoFactorException::accessDenied($denial[0], $denial[1]);
        }
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function touchesAccess(array $commands): bool
    {
        foreach ($commands as $command) {
            $entity = $command->getEntityName();
            if (\in_array($entity, AdminGuardSubscriber::PROTECTED_ENTITIES, true)) {
                return true;
            }
            if ($entity === 'system_config' && AdminGuardSubscriber::isPluginConfigKey($this->configKey($command))) {
                return true;
            }
        }

        return false;
    }

    private function configKey(WriteCommand $command): string
    {
        $key = $command->getPayload()['configuration_key'] ?? null;
        if (\is_string($key)) {
            return $key;
        }

        // Ändern/Löschen per ID: Schlüssel aus der Datenbank
        $id = $command->getPrimaryKey()['id'] ?? null;

        return \is_string($id)
            ? (string) $this->connection->fetchOne('SELECT `configuration_key` FROM `system_config` WHERE `id` = :id', ['id' => $id])
            : '';
    }
}
