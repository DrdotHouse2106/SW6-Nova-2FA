<?php declare(strict_types=1);

namespace Nova\TwoFactor\Tests\Unit;

use Nova\TwoFactor\Subscriber\TwoFactorGateSubscriber as Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Storefront nach dem Passwort, vor dem Code: Was ist erreichbar?
 */
class TwoFactorGateDecisionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function cases(): iterable
    {
        // Code-Abfrage, Abmelden, Cookies immer erreichbar
        yield 'Code-Seite' => ['frontend.nova_2fa.verify', 'GET', false, Gate::ALLOW];
        yield 'Code prüfen' => ['frontend.nova_2fa.verify.check', 'POST', false, Gate::ALLOW];
        yield 'Abmelden' => ['frontend.account.logout.page', 'GET', false, Gate::ALLOW];
        yield 'Login-Formular' => ['frontend.account.login', 'POST', false, Gate::ALLOW];
        yield 'Cookie-Einstellungen' => ['frontend.cookie.offcanvas', 'GET', true, Gate::ALLOW];

        // Kleine Nachlade-Bausteine ohne Kontodaten
        yield 'Warenkorb-Zähler' => ['frontend.checkout.info', 'GET', true, Gate::ALLOW];
        yield 'Off-Canvas-Warenkorb' => ['frontend.cart.offcanvas', 'GET', true, Gate::ALLOW];
        yield 'Off-Canvas-Menü' => ['frontend.menu.offcanvas', 'GET', true, Gate::ALLOW];
        yield 'Suchvorschläge' => ['frontend.search.suggest', 'GET', true, Gate::ALLOW];
        yield 'Cookie-Gruppen per XHR' => ['frontend.cookie.consent.offcanvas', 'GET', true, Gate::ALLOW];
        yield 'CMS-Baustein' => ['frontend.cms.page', 'GET', true, Gate::ALLOW];
        yield 'Länder-Bundesländer' => ['frontend.country.country.data', 'GET', true, Gate::ALLOW];

        // Z-1: Kontodaten per XHR-Kopfzeile nicht mehr erreichbar
        yield 'Bestellungen per XHR' => ['frontend.account.order.page', 'GET', true, Gate::DENY];
        yield 'Bestelldetail per XHR' => ['widgets.account.order.detail', 'GET', true, Gate::DENY];
        yield 'Adressen per XHR' => ['frontend.account.addressmanager.get', 'GET', true, Gate::DENY];
        yield 'Merkzettel per XHR' => ['frontend.wishlist.product.list', 'GET', true, Gate::DENY];
        yield 'Warenkorb-JSON per XHR' => ['frontend.checkout.cart.json', 'GET', true, Gate::DENY];
        yield 'Bestellabschluss per XHR' => ['frontend.checkout.confirm.page', 'GET', true, Gate::DENY];
        yield 'Unbekannte Plugin-Route per XHR' => ['frontend.some_plugin.data', 'GET', true, Gate::DENY];

        // Seitenaufrufe leiten zur Code-Abfrage, Änderungen werden abgelehnt
        yield 'Kontoseite' => ['frontend.account.home.page', 'GET', false, Gate::REDIRECT];
        yield 'Startseite' => ['frontend.home.page', 'GET', false, Gate::REDIRECT];
        yield 'Warenkorb-Zähler ohne XHR' => ['frontend.checkout.info', 'GET', false, Gate::REDIRECT];
        yield 'In den Warenkorb' => ['frontend.checkout.line-item.add', 'POST', true, Gate::DENY];
        yield 'Profil speichern' => ['frontend.account.profile.save', 'POST', false, Gate::DENY];
        yield 'CMS per POST' => ['frontend.cms.navigation.page', 'POST', true, Gate::DENY];
    }

    #[DataProvider('cases')]
    public function testDecision(string $route, string $method, bool $xhr, string $expected): void
    {
        static::assertSame($expected, Gate::decide($route, $method, $xhr));
    }

    public function testStoreApiBlocksOnlyCredentialChanges(): void
    {
        static::assertTrue(Gate::isStoreApiDenied('store-api.account.change-email'));
        static::assertTrue(Gate::isStoreApiDenied('store-api.account.change-password'));
        static::assertTrue(Gate::isStoreApiDenied('store-api.account.change-profile'));
        static::assertFalse(Gate::isStoreApiDenied('store-api.account.login'));
        static::assertFalse(Gate::isStoreApiDenied('store-api.account.customer'));
        static::assertFalse(Gate::isStoreApiDenied('store-api.checkout.cart.read'));
        static::assertFalse(Gate::isStoreApiDenied('store-api.checkout.order.create'));
    }
}
