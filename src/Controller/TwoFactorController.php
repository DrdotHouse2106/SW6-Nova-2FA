<?php declare(strict_types=1);

namespace Nova\TwoFactor\Controller;

use Nova\TwoFactor\Service\Totp;
use Nova\TwoFactor\Service\TwoFactorGuard;
use Nova\TwoFactor\Service\TwoFactorStore;
use Nova\TwoFactor\Service\SecretCipher;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Kundenkonto → Zwei-Faktor-Anmeldung: einrichten, Wiederherstellungscodes, abschalten,
 * und die Code-Abfrage nach dem Passwort-Login.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class TwoFactorController extends StorefrontController
{
    public function __construct(
        private readonly GenericPageLoaderInterface $pageLoader,
        private readonly TwoFactorStore $store,
        private readonly TwoFactorGuard $guard,
        private readonly SecretCipher $cipher,
        private readonly SystemConfigService $config,
    ) {
    }

    #[Route(path: '/account/nova-2fa', name: 'frontend.nova_2fa.page', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true], methods: [Request::METHOD_GET])]
    public function page(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $session = $request->getSession();
        $pending = $session->get(TwoFactorGuard::SESSION_PENDING);
        $secret = \is_string($pending) ? $this->cipher->decrypt($pending) : null;
        $recovery = $session->get(TwoFactorGuard::SESSION_RECOVERY);
        $session->remove(TwoFactorGuard::SESSION_RECOVERY);

        return $this->renderStorefront('@NovaTwoFactor/storefront/page/account/nova-2fa/index.html.twig', [
            'page' => $this->pageLoader->load($request, $context),
            'novaTwoFactor' => [
                'enabled' => $this->store->isEnabled($customer->getId()),
                'status' => $this->store->status($customer->getId()),
                'setupSecret' => $secret,
                'setupUri' => $secret !== null ? Totp::uri($secret, $customer->getEmail(), $this->issuer($context)) : null,
                'recoveryCodes' => \is_array($recovery) ? $recovery : null,
                'ttlHours' => $this->guard->ttlHours($context->getSalesChannelId()),
            ],
        ]);
    }

    #[Route(path: '/account/nova-2fa/setup', name: 'frontend.nova_2fa.setup', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function setup(Request $request, CustomerEntity $customer): Response
    {
        if (!$this->store->isEnabled($customer->getId())) {
            $request->getSession()->set(TwoFactorGuard::SESSION_PENDING, $this->cipher->encrypt(Totp::generateSecret()));
        }

        return $this->redirectToRoute('frontend.nova_2fa.page');
    }

    #[Route(path: '/account/nova-2fa/confirm', name: 'frontend.nova_2fa.confirm', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function confirm(Request $request, CustomerEntity $customer): Response
    {
        $session = $request->getSession();
        $pending = $session->get(TwoFactorGuard::SESSION_PENDING);
        $secret = \is_string($pending) ? $this->cipher->decrypt($pending) : null;

        if ($secret === null || Totp::verify($secret, $request->request->getString('code')) === null) {
            $this->addFlash(self::DANGER, $this->trans('novaTwoFactor.error.invalidCode'));

            return $this->redirectToRoute('frontend.nova_2fa.page');
        }

        $codes = $this->store->enable($customer->getId(), $secret);
        $session->remove(TwoFactorGuard::SESSION_PENDING);
        $session->set(TwoFactorGuard::SESSION_RECOVERY, $codes);
        $this->guard->markVerified($request, $customer->getId());
        $this->addFlash(self::SUCCESS, $this->trans('novaTwoFactor.enabled'));

        return $this->redirectToRoute('frontend.nova_2fa.page');
    }

    #[Route(path: '/account/nova-2fa/cancel', name: 'frontend.nova_2fa.cancel', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function cancel(Request $request): Response
    {
        $request->getSession()->remove(TwoFactorGuard::SESSION_PENDING);

        return $this->redirectToRoute('frontend.nova_2fa.page');
    }

    #[Route(path: '/account/nova-2fa/disable', name: 'frontend.nova_2fa.disable', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function disable(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $result = $this->checkCode($request, $context, $customer);
        if ($result === 'ok' || $result === 'recovery') {
            $this->store->disable($customer->getId());
            $this->guard->forget($request);
            $this->addFlash(self::SUCCESS, $this->trans('novaTwoFactor.disabled'));
        }

        return $this->redirectToRoute('frontend.nova_2fa.page');
    }

    #[Route(path: '/account/nova-2fa/recovery', name: 'frontend.nova_2fa.recovery', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function recovery(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $result = $this->checkCode($request, $context, $customer);
        if ($result === 'ok' || $result === 'recovery') {
            $request->getSession()->set(TwoFactorGuard::SESSION_RECOVERY, $this->store->regenerateRecoveryCodes($customer->getId()));
        }

        return $this->redirectToRoute('frontend.nova_2fa.page');
    }

    #[Route(path: '/account/nova-2fa/verify', name: 'frontend.nova_2fa.verify', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true], methods: [Request::METHOD_GET])]
    public function verifyPage(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        if (!$this->store->isEnabled($customer->getId()) || $this->guard->isVerified($request, $customer->getId(), $context->getSalesChannelId())) {
            return $this->redirectTo($this->target($request->query->getString('redirectTo')));
        }

        return $this->renderStorefront('@NovaTwoFactor/storefront/page/account/nova-2fa/verify.html.twig', [
            'page' => $this->pageLoader->load($request, $context),
            'redirectTo' => $this->target($request->query->getString('redirectTo')),
        ]);
    }

    #[Route(path: '/account/nova-2fa/verify', name: 'frontend.nova_2fa.verify.check', defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function verify(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $target = $this->target($request->request->getString('redirectTo'));
        $result = $this->checkCode($request, $context, $customer);

        if ($result === 'ok' || $result === 'recovery') {
            $this->guard->markVerified($request, $customer->getId());
            if ($result === 'recovery') {
                $this->addFlash(self::WARNING, $this->trans('novaTwoFactor.recoveryUsed', ['%count%' => $this->store->remainingRecoveryCodes($customer->getId())]));
            }

            return $this->redirectTo($target);
        }

        return $this->redirectToRoute('frontend.nova_2fa.verify', ['redirectTo' => $target]);
    }

    /**
     * @return 'ok'|'recovery'|'invalid'|'locked'|'missing'
     */
    private function checkCode(Request $request, SalesChannelContext $context, CustomerEntity $customer): string
    {
        $salesChannelId = $context->getSalesChannelId();
        $result = $this->store->check(
            $customer->getId(),
            $request->request->getString('code'),
            $this->config->getInt('NovaTwoFactor.config.maxAttempts', $salesChannelId) ?: 5,
            $this->config->getInt('NovaTwoFactor.config.lockMinutes', $salesChannelId) ?: 15,
        );

        if ($result === 'locked') {
            $this->addFlash(self::DANGER, $this->trans('novaTwoFactor.error.locked', ['%minutes%' => $this->config->getInt('NovaTwoFactor.config.lockMinutes', $salesChannelId) ?: 15]));
        } elseif ($result === 'invalid' || $result === 'missing') {
            $this->addFlash(self::DANGER, $this->trans('novaTwoFactor.error.invalidCode'));
        }

        return $result;
    }

    /** Nur Pfade im Shop, keine fremden Adressen (Open Redirect) */
    private function target(string $target): string
    {
        return str_starts_with($target, '/') && !str_starts_with($target, '//') && !str_contains($target, '\\') ? $target : '/account';
    }

    private function redirectTo(string $path): Response
    {
        return $this->redirect($path);
    }

    private function issuer(SalesChannelContext $context): string
    {
        $issuer = trim((string) $this->config->getString('NovaTwoFactor.config.issuer', $context->getSalesChannelId()));

        return $issuer !== '' ? $issuer : ($context->getSalesChannel()->getTranslation('name') ?? $context->getSalesChannel()->getName() ?? 'Shop');
    }
}
