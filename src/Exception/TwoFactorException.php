<?php declare(strict_types=1);

namespace Nova\TwoFactor\Exception;

use Shopware\Core\Framework\HttpException;

/**
 * Antwort wie die übrigen Ablehnungen des Plugins ({"errors": [{"code": "NOVA_2FA_…"}]}),
 * damit die Administration das Bestätigungsfenster zeigt und die Anfrage mit Code wiederholt.
 */
class TwoFactorException extends HttpException
{
    public static function accessDenied(string $errorCode, string $message): self
    {
        return new self(403, $errorCode, $message);
    }
}
