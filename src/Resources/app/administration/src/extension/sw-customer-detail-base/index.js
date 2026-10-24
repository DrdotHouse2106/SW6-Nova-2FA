import template from './sw-customer-detail-base.html.twig';

/**
 * Kunde → Allgemein: Karte „Zwei-Faktor-Anmeldung“ (Status, Zurücksetzen).
 */
Shopware.Component.override('sw-customer-detail-base', {
    template,
});
