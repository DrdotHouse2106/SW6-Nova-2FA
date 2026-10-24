<?php declare(strict_types=1);

namespace Nova\TwoFactor;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Nova 2FA – Zwei-Faktor-Anmeldung per Authenticator-App für Kundenkonten.
 */
class NovaTwoFactor extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container?->get(Connection::class);
        if ($connection instanceof Connection) {
            $connection->executeStatement('DROP TABLE IF EXISTS `nova_two_factor`');
        }
    }
}
