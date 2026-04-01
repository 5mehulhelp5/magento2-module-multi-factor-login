<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Block\Verify;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;

/**
 * Block for the MFA verification form template.
 *
 * Exposes session-derived state and masked contact information to the template.
 */
class Form extends Template
{
    /**
     * @param \Magento\Framework\View\Element\Template\Context  $context
     * @param \Muon\MultiFactorLogin\Model\Config               $config
     * @param \Magento\Customer\Model\Session                   $customerSession
     * @param \Muon\MultiFactorLogin\Model\Session              $mfaSession
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Api\AddressRepositoryInterface  $addressRepository
     * @param mixed[]                                           $data  Block configuration data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly MfaSession $mfaSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check whether a token has been sent and the customer should see the input form.
     *
     * @return bool
     */
    public function isTokenSent(): bool
    {
        return (string) $this->mfaSession->getMfaDeliveryMethod() !== '';
    }

    /**
     * Get the delivery method currently in use (sms or email).
     *
     * @return string
     */
    public function getDeliveryMethod(): string
    {
        return (string) $this->mfaSession->getMfaDeliveryMethod();
    }

    /**
     * Check whether the SMS option is available for this customer.
     *
     * @return bool
     */
    public function isSmsAvailable(): bool
    {
        $methods = $this->mfaSession->getMfaAvailableMethods() ?? [];
        return in_array(TokenInterface::METHOD_SMS, $methods, true);
    }

    /**
     * Check whether the Email option is available for this customer.
     *
     * @return bool
     */
    public function isEmailAvailable(): bool
    {
        $methods = $this->mfaSession->getMfaAvailableMethods() ?? [];
        return in_array(TokenInterface::METHOD_EMAIL, $methods, true);
    }

    /**
     * Check whether the customer has a choice of delivery methods.
     *
     * @return bool
     */
    public function hasMethodChoice(): bool
    {
        return $this->isSmsAvailable() && $this->isEmailAvailable();
    }

    /**
     * Get the masked email address for display (e.g. j***@example.com).
     *
     * @return string
     */
    public function getMaskedEmail(): string
    {
        $customerId = $this->mfaSession->getMfaPendingCustomerId();
        if (!$customerId) {
            return '';
        }

        try {
            $customer = $this->customerRepository->getById((int) $customerId);
            return $this->maskEmail($customer->getEmail());
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    /**
     * Get the masked phone number for display (e.g. ***-***-1234).
     *
     * @return string
     */
    public function getMaskedPhone(): string
    {
        $customerId = $this->mfaSession->getMfaPendingCustomerId();
        if (!$customerId) {
            return '';
        }

        try {
            $customer         = $this->customerRepository->getById((int) $customerId);
            $billingAddressId = $customer->getDefaultBilling();
            if (!$billingAddressId) {
                return '';
            }
            $address = $this->addressRepository->getById((int) $billingAddressId);
            return $this->maskPhone((string) $address->getTelephone());
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    /**
     * Get the URL for the token send action.
     *
     * @return string
     */
    public function getSendUrl(): string
    {
        return $this->getUrl('mfa/verify/send');
    }

    /**
     * Get the URL for the token submit action.
     *
     * @return string
     */
    public function getSubmitUrl(): string
    {
        return $this->getUrl('mfa/verify/submit');
    }

    /**
     * Get the URL for the resend action.
     *
     * @return string
     */
    public function getResendUrl(): string
    {
        return $this->getUrl('mfa/verify/resend');
    }

    /**
     * Get the configured token length for input field sizing.
     *
     * @return int
     */
    public function getTokenLength(): int
    {
        return $this->config->getTokenLength();
    }

    /**
     * Mask all characters of the email local part except the first.
     *
     * @param string $email
     * @return string
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;
        $masked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1));
        return $masked . '@' . $domain;
    }

    /**
     * Mask all but the last four digits of a phone number.
     *
     * @param string $phone
     * @return string
     */
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return str_repeat('*', strlen($phone));
        }

        $visible = substr($digits, -4);
        return str_repeat('*', strlen($digits) - 4) . $visible;
    }
}
