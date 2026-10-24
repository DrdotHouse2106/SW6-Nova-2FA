<?php declare(strict_types=1);

namespace Nova\TwoFactor\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Speichert die eingerichtete 2FA je Konto (Kunde bzw. Admin-Benutzer – eigene Tabelle je Art):
 * verschlüsselter Schlüssel, gehashte Wiederherstellungscodes, Schutz gegen Wiederverwendung und Sperre nach Fehlversuchen.
 */
class TwoFactorStore
{
    public const RECOVERY_CODES = 8;

    public function __construct(
        private readonly Connection $connection,
        private readonly SecretCipher $cipher,
        private readonly string $table = 'nova_two_factor',
        private readonly string $idColumn = 'customer_id',
    ) {
    }

    public function isEnabled(string $customerId): bool
    {
        return Uuid::isValid($customerId)
            && $this->connection->fetchOne('SELECT 1 FROM `' . $this->table . '` WHERE `' . $this->idColumn . '` = :id', ['id' => Uuid::fromHexToBytes($customerId)]) !== false;
    }

    /**
     * @return list<string> Wiederherstellungscodes im Klartext – nur jetzt einmal anzeigen
     */
    public function enable(string $customerId, string $secret): array
    {
        $codes = $this->newRecoveryCodes();
        $this->connection->executeStatement(
            'REPLACE INTO `' . $this->table . '` (`' . $this->idColumn . '`, `secret`, `recovery_codes`, `last_step`, `failed_attempts`, `locked_until`, `enabled_at`)
             VALUES (:id, :secret, :codes, 0, 0, NULL, :now)',
            [
                'id' => Uuid::fromHexToBytes($customerId),
                'secret' => $this->cipher->encrypt($secret),
                'codes' => json_encode($this->hashCodes($codes)),
                'now' => $this->now(),
            ]
        );

        return $codes;
    }

    public function disable(string $customerId): void
    {
        if (Uuid::isValid($customerId)) {
            $this->connection->delete($this->table, [$this->idColumn => Uuid::fromHexToBytes($customerId)]);
        }
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(string $customerId): array
    {
        $codes = $this->newRecoveryCodes();
        $this->connection->update($this->table, ['recovery_codes' => json_encode($this->hashCodes($codes))], [$this->idColumn => Uuid::fromHexToBytes($customerId)]);

        return $codes;
    }

    /**
     * Prüft einen App-Code oder einen Wiederherstellungscode.
     *
     * @return 'ok'|'recovery'|'invalid'|'locked'|'missing'
     */
    public function check(string $customerId, string $input, int $maxAttempts, int $lockMinutes, ?int $now = null): string
    {
        $row = Uuid::isValid($customerId) ? $this->connection->fetchAssociative(
            'SELECT `secret`, `recovery_codes`, `last_step`, `failed_attempts`, `locked_until` FROM `' . $this->table . '` WHERE `' . $this->idColumn . '` = :id',
            ['id' => Uuid::fromHexToBytes($customerId)]
        ) : false;
        if ($row === false) {
            return 'missing';
        }

        $now ??= time();
        if ($row['locked_until'] !== null && strtotime((string) $row['locked_until']) > $now) {
            return 'locked';
        }

        $id = Uuid::fromHexToBytes($customerId);
        $secret = $this->cipher->decrypt((string) $row['secret']);
        $step = $secret !== null ? Totp::verify($secret, $input, $now, (int) $row['last_step']) : null;
        if ($step !== null) {
            $this->connection->update($this->table, ['last_step' => $step, 'failed_attempts' => 0, 'locked_until' => null, 'last_used_at' => $this->now()], [$this->idColumn => $id]);

            return 'ok';
        }

        // Wiederherstellungscode (einmalig verwendbar)
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
        $hashes = json_decode((string) $row['recovery_codes'], true);
        $hashes = \is_array($hashes) ? array_values(array_filter($hashes, 'is_string')) : [];
        if (\strlen($normalized) === 10) {
            foreach ($hashes as $index => $hash) {
                if (password_verify($normalized, $hash)) {
                    unset($hashes[$index]);
                    $this->connection->update($this->table, [
                        'recovery_codes' => json_encode(array_values($hashes)),
                        'failed_attempts' => 0,
                        'locked_until' => null,
                        'last_used_at' => $this->now(),
                    ], [$this->idColumn => $id]);

                    return 'recovery';
                }
            }
        }

        $failed = (int) $row['failed_attempts'] + 1;
        $lock = $failed >= max(1, $maxAttempts);
        $this->connection->update($this->table, [
            'failed_attempts' => $lock ? 0 : $failed,
            'locked_until' => $lock ? date('Y-m-d H:i:s.000', $now + max(1, $lockMinutes) * 60) : null,
        ], [$this->idColumn => $id]);

        return $lock ? 'locked' : 'invalid';
    }

    public function remainingRecoveryCodes(string $customerId): int
    {
        $codes = Uuid::isValid($customerId) ? $this->connection->fetchOne('SELECT `recovery_codes` FROM `' . $this->table . '` WHERE `' . $this->idColumn . '` = :id', ['id' => Uuid::fromHexToBytes($customerId)]) : false;
        $codes = \is_string($codes) ? json_decode($codes, true) : null;

        return \is_array($codes) ? \count($codes) : 0;
    }

    /**
     * @return array{enabledAt: string, lastUsedAt: ?string, recoveryCodes: int}|null
     */
    public function status(string $customerId): ?array
    {
        $row = Uuid::isValid($customerId) ? $this->connection->fetchAssociative(
            'SELECT `enabled_at`, `last_used_at`, `recovery_codes` FROM `' . $this->table . '` WHERE `' . $this->idColumn . '` = :id',
            ['id' => Uuid::fromHexToBytes($customerId)]
        ) : false;
        if ($row === false) {
            return null;
        }
        $codes = json_decode((string) $row['recovery_codes'], true);

        return [
            'enabledAt' => (string) $row['enabled_at'],
            'lastUsedAt' => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            'recoveryCodes' => \is_array($codes) ? \count($codes) : 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; ++$i) {
            $codes[] = substr(Totp::base32Encode(random_bytes(7)), 0, 10);
        }

        return $codes;
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private function hashCodes(array $codes): array
    {
        return array_map(static fn (string $code): string => password_hash($code, \PASSWORD_DEFAULT), $codes);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
