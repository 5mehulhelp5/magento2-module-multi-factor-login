<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Plugin;

use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;
use Muon\MultiFactorLogin\Service\CustomerPhoneResolver;

/**
 * Intercepts a successful password-based login and redirects the customer
 * to the MFA verification page when multi-factor authentication is enabled.
 */
class LoginPostPlugin
{
    /**
     * @param \Muon\MultiFactorLogin\Model\Config                       $config
     * @param \Magento\Customer\Model\Session                           $customerSession
     * @param \Muon\MultiFactorLogin\Model\Session                      $mfaSession
     * @param \Magento\Framework\Controller\Result\RedirectFactory      $redirectFactory
     * @param \Muon\MultiFactorLogin\Service\CustomerPhoneResolver      $phoneResolver
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly MfaSession $mfaSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerPhoneResolver $phoneResolver,
    ) {
    }

    /**
     * After a successful login, intercept and redirect to MFA verification.
     *
     * If MFA is disabled or the login failed (customer not logged in), the
     * original result is returned unchanged.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * $subject is required by the Magento plugin interface signature.
     *
     * @param \Magento\Customer\Controller\Account\LoginPost      $subject
     * @param \Magento\Framework\Controller\ResultInterface       $result
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function afterExecute(LoginPost $subject, mixed $result): mixed
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        // Determine which delivery methods are available for this customer
        // before destroying the session.
        $availableMethods = $this->resolveAvailableMethods($customerId);

        // Log the customer back out — they must complete MFA first.
        $this->customerSession->logout();

        // Store the pending state in the dedicated MFA session (isolated from CustomerSession).
        $this->mfaSession->setMfaPendingCustomerId($customerId);
        $this->mfaSession->setMfaAvailableMethods($availableMethods);

        return $this->redirectFactory->create()->setPath('mfa/verify');
    }

    /**
     * Determine which delivery methods are available for the given customer.
     *
     * The result is the intersection of admin-configured allowed methods and
     * what the customer actually has (email always exists; SMS requires a phone
     * number on the default billing address).
     *
     * @param int $customerId
     * @return string[]
     */
    private function resolveAvailableMethods(int $customerId): array
    {
        $configured = $this->config->getAllowedDeliveryMethods();
        $methods    = [];

        if ($configured === 'email' || $configured === 'both') {
            $methods[] = TokenInterface::METHOD_EMAIL;
        }

        if ($configured === 'sms' || $configured === 'both') {
            if ($this->phoneResolver->resolve($customerId) !== null) {
                $methods[] = TokenInterface::METHOD_SMS;
            }
        }

        // Fall back to email if no methods are available (e.g. SMS-only config but no phone).
        if ($methods === []) {
            $methods[] = TokenInterface::METHOD_EMAIL;
        }

        return $methods;
    }
}
