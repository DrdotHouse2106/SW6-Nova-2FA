<?php declare(strict_types=1);

namespace Nova\TwoFactor\Service;

/**
 * Zeitbasierte Einmal-Codes nach RFC 6238 (SHA-1, 6 Stellen, 30 Sekunden) –
 * kompatibel mit Google/Microsoft Authenticator, 1Password, Authy usw.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Zufälliger Schlüssel (160 Bit) in Base32 */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = \ord($hash[19]) & 0x0F;
        $value = ((\ord($hash[$offset]) & 0x7F) << 24)
            | ((\ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((\ord($hash[$offset + 2]) & 0xFF) << 8)
            | (\ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', \STR_PAD_LEFT);
    }

    /**
     * Prüft einen Code mit ±1 Zeitfenster Toleranz. Gibt das verwendete Zeitfenster zurück
     * (zum Speichern gegen Wiederverwendung) oder null. Fenster ≤ $lastStep werden abgelehnt.
     */
    public static function verify(string $secret, string $code, ?int $now = null, int $lastStep = 0): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $current = intdiv($now ?? time(), self::PERIOD);
        foreach ([0, -1, 1] as $offset) {
            $step = $current + $offset;
            if ($step > $lastStep && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(\ord($char)), 8, '0', \STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $data));
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', \STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (\strlen($byte) === 8) {
                $out .= \chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
