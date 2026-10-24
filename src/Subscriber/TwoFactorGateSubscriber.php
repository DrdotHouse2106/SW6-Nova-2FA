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

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $scope = (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (!$event->isMainRequest() || !\in_array('storefront', $scope, true) || \in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $customer = $context instanceof SalesChannelContext ? $context->getCustomer() : null;
        if ($customer === null || $customer->getGuest()) {
            return;
        }

        $customerId = $customer->getId();
        if (!$this->guard->isEnabled($customerId) || $this->guard->isVerified($request, $customerId, $context->getSalesChannelId())) {
            return;
        }

        $isXhr = $request->isXmlHttpRequest();
        if ($request->isMethod('GET') && $isXhr) {
            // Kleine Nachlade-Bausteine (Warenkorb-Zähler usw.) nicht blockieren
            return;
        }

        $url = $this->router->generate('frontend.nova_2fa.verify', [
            'redirectTo' => $request->isMethod('GET') ? $request->getRequestUri() : '/account',
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
}
