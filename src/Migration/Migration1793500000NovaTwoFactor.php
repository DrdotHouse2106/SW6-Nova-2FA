<?php declare(strict_types=1);

namespace Nova\TwoFactor\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1793500000NovaTwoFactor extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793500000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_two_factor` (
                `customer_id` BINARY(16) NOT NULL,
                `secret` TEXT NOT NULL,
                `recovery_codes` JSON NOT NULL,
                `last_step` BIGINT NOT NULL DEFAULT 0,
                `failed_attempts` INT NOT NULL DEFAULT 0,
                `locked_until` DATETIME(3) NULL,
                `enabled_at` DATETIME(3) NOT NULL,
                `last_used_at` DATETIME(3) NULL,
                PRIMARY KEY (`customer_id`),
                CONSTRAINT `fk.nova_two_factor.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
