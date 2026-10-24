<?php declare(strict_types=1);

namespace Nova\TwoFactor\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Zwei-Faktor-Anmeldung für Benutzer der Administration.
 */
class Migration1793600000NovaTwoFactorUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_two_factor_user` (
                `user_id` BINARY(16) NOT NULL,
                `secret` TEXT NOT NULL,
                `recovery_codes` JSON NOT NULL,
                `last_step` BIGINT NOT NULL DEFAULT 0,
                `failed_attempts` INT NOT NULL DEFAULT 0,
                `locked_until` DATETIME(3) NULL,
                `enabled_at` DATETIME(3) NOT NULL,
                `last_used_at` DATETIME(3) NULL,
                PRIMARY KEY (`user_id`),
                CONSTRAINT `fk.nova_two_factor_user.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
