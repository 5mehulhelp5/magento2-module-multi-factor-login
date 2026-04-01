<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Plugin;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;

/**
 * Intercepts a successful password-based login and redirects the customer
 * to the MFA verification page when multi-factor authentication is enabled.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * Coordination plugin — coupling is inherent to checking session state, customer
 * data, and building a redirect response at the login interception point.
 */
class LoginPostPlugin
{
    /**
     * @param \Muon\MultiFactorLogin\Model\Config                       $config
     * @param \Magento\Customer\Model\Session                           $customerSession
     * @param \Muon\MultiFactorLogin\Model\Session                      $mfaSession
     * @param \Magento\Framework\Controller\Result\RedirectFactory      $redirectFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface         $customerRepository
     * @param \Magento\Customer\Api\AddressRepositoryInterface          $addressRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly MfaSession $mfaSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
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
            if ($this->resolvePhone($customerId) !== '') {
                $methods[] = TokenInterface::METHOD_SMS;
            }
        }

        // Fall back to email if no methods are available (e.g. SMS-only config but no phone).
        if ($methods === []) {
            $methods[] = TokenInterface::METHOD_EMAIL;
        }

        return $methods;
    }

    /**
     * Resolve the customer's phone number from their default billing address.
     *
     * Returns an empty string if no billing address or phone is found.
     *
     * @param int $customerId
     * @return string
     */
    private function resolvePhone(int $customerId): string
    {
        try {
            $customer         = $this->customerRepository->getById($customerId);
            $billingAddressId = $customer->getDefaultBilling();

            if (!$billingAddressId) {
                return '';
            }

            $address = $this->addressRepository->getById((int) $billingAddressId);
            return (string) $address->getTelephone();
        } catch (NoSuchEntityException) {
            return '';
        }
    }
}
