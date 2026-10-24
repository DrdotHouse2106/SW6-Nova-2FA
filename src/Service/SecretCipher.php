<?php declare(strict_types=1);

namespace Nova\TwoFactor\Service;

/**
 * Verschlüsselt die TOTP-Schlüssel in der Datenbank (AES-256-GCM, Schlüssel aus dem App-Secret).
 * Ein Datenbank-Auszug allein reicht so nicht, um Codes zu erzeugen.
 */
class SecretCipher
{
    private readonly string $key;

    public function __construct(string $appSecret)
    {
        $this->key = hash('sha256', 'nova-two-factor|' . $appSecret, true);
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, \OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Verschlüsselung fehlgeschlagen');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || \strlen($raw) < 29) {
            return null;
        }

        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, \OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? null : $plain;
    }
}
