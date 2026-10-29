<?php declare(strict_types=1);

namespace Nova\TwoFactor\Subscriber;

use Nova\TwoFactor\Service\TwoFactorGuard;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Nach dem Passwort-Login ist das Konto erst nach dem App-Code voll nutzbar:
 * Seiten leiten zur Code-Abfrage, Änderungen (POST) werden abgelehnt. Gilt nur für Konten mit eingerichteter 2FA.
 */
class TwoFactorGateSubscriber implements EventSubscriberInterface
{
    public const ALLOW = 'allow';
    public const REDIRECT = 'redirect';
    public const DENY = 'deny';

    /** Erreichbar ohne bestätigten Code */
    private const ALLOWED_ROUTES = [
        'frontend.nova_2fa.verify',
        'frontend.nova_2fa.verify.check',
        'frontend.account.logout.page',
        'frontend.account.login.page',
        'frontend.account.login',
        'frontend.cookie.offcanvas',
        'frontend.cookie.permission',
        'frontend.cookie.groups',
    ];

    /**
     * Nachlade-Bausteine (GET per XHR), die auch vor der Code-Eingabe laufen dürfen – ohne Kontodaten.
     * Alles andere (Konto, Bestellungen, Adressen, Merkzettel, Checkout) bleibt bis zum Code gesperrt.
     */
    private const XHR_ALLOWED_ROUTES = [
        'frontend.checkout.info',
        'frontend.cart.offcanvas',
        'frontend.menu.offcanvas',
        'frontend.search.suggest',
        'frontend.country.country.data',
        'frontend.detail.switch',
        'frontend.product.reviews',
        'frontend.product.purchase-limit',
        'frontend.captcha.basic-captcha.load',
        'widgets.quickview.minimal',
        'widgets.search.pagelet.v2',
        'widgets.search.filter',
    ];

    private const XHR_ALLOWED_PREFIXES = ['frontend.cookie.', 'frontend.cms.'];

    /** Store-API: Zugangsdaten und Profil eines Kontos mit 2FA lassen sich dort nicht ohne Code ändern */
    private const STORE_API_DENIED_ROUTES = [
        'store-api.account.change-email',
        'store-api.account.change-password',
        'store-api.account.change-profile',
    ];

    public function __construct(
        private readonly TwoFactorGuard $guard,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', -20],
            CustomerLoginEvent::class => 'onLogin',
            CustomerLogoutEvent::class => 'onLogout',
        ];
    }

    /**
     * Was passiert mit einer Storefront-Anfrage eines angemeldeten, aber noch nicht per Code bestätigten Kontos?
     *
     * @return self::ALLOW|self::REDIRECT|self::DENY
     */
    public static function decide(string $route, string $method, bool $isXhr): string
    {
        if (\in_array($route, self::ALLOWED_ROUTES, true)) {
            return self::ALLOW;
        }

        $safe = \in_array(strtoupper($method), ['GET', 'HEAD'], true);
        if ($safe && $isXhr && self::isXhrAllowed($route)) {
            return self::ALLOW;
        }

        return $safe && !$isXhr ? self::REDIRECT : self::DENY;
    }

    public static function isStoreApiDenied(string $route): bool
    {
        return \in_array($route, self::STORE_API_DENIED_ROUTES, true);
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $scope = (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (!$event->isMainRequest()) {
            return;
        }

        $storefront = \in_array('storefront', $scope, true);
        $storeApi = !$storefront && \in_array('store-api', $scope, true) && self::isStoreApiDenied($route);
        if (!$storefront && !$storeApi) {
            return;
        }
        if ($storefront && self::decide($route, $request->getMethod(), $request->isXmlHttpRequest()) === self::ALLOW) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $customer = $context instanceof SalesChannelContext ? $context->getCustomer() : null;
        if ($customer === null || $customer->getGuest()) {
            return;
        }

        $customerId = $customer->getId();
        if ($storeApi) {
            // Die Store-API kennt keine Code-Abfrage – für Konten mit 2FA bleibt nur die Storefront
            if ($this->guard->isEnabled($customerId)) {
                $event->setController(static fn (): JsonResponse => new JsonResponse(['errors' => [[
                    'status' => '403',
                    'code' => 'NOVA_2FA_STORE_API_DENIED',
                    'title' => 'Forbidden',
                    'detail' => 'Dieses Konto ist mit Zwei-Faktor-Anmeldung geschützt. Änderungen bitte im Kundenkonto im Shop vornehmen.',
                ]]], 403));
            }

            return;
        }

        if (!$this->guard->isEnabled($customerId) || $this->guard->isVerified($request, $customerId, $context->getSalesChannelId())) {
            return;
        }

        $isXhr = $request->isXmlHttpRequest();
        $url = $this->router->generate('frontend.nova_2fa.verify', [
            'redirectTo' => $request->isMethod('GET') && !$isXhr ? $request->getRequestUri() : '/account',
        ]);

        $event->setController(static fn () => $isXhr
            ? new JsonResponse(['redirectTo' => $url], 403)
            : new RedirectResponse($url));
    }

    public function onLogin(CustomerLoginEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            $this->guard->forget($request);
        }
    }

    public function onLogout(CustomerLogoutEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            $this->guard->forget($request);
        }
    }

    private static function isXhrAllowed(string $route): bool
    {
        if (\in_array($route, self::XHR_ALLOWED_ROUTES, true)) {
            return true;
        }
        foreach (self::XHR_ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
