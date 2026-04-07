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
use Muon\MultiFactorLogin\Service\CustomerPhoneResolver;

/**
 * Block for the MFA verification form template.
 *
 * Exposes session-derived state and masked contact information to the template.
 */
class Form extends Template
{
    /**
     * Cached masked email — null means not yet resolved.
     *
     * @var string|null
     */
    private ?string $maskedEmail = null;

    /**
     * Cached masked phone — null means not yet resolved.
     *
     * @var string|null
     */
    private ?string $maskedPhone = null;

    /**
     * @param \Magento\Framework\View\Element\Template\Context     $context
     * @param \Muon\MultiFactorLogin\Model\Config                  $config
     * @param \Magento\Customer\Model\Session                      $customerSession
     * @param \Muon\MultiFactorLogin\Model\Session                 $mfaSession
     * @param \Magento\Customer\Api\CustomerRepositoryInterface    $customerRepository
     * @param \Muon\MultiFactorLogin\Service\CustomerPhoneResolver $phoneResolver
     * @param mixed[]                                              $data  Block configuration data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly MfaSession $mfaSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerPhoneResolver $phoneResolver,
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
     * Check whether the configured token character set is entirely numeric.
     *
     * Used by the template to set the appropriate inputmode on the token field.
     *
     * @return bool
     */
    public function isNumericTokenSet(): bool
    {
        return ctype_digit($this->config->getTokenCharacters());
    }

    /**
     * Get the masked email address for display (e.g. j***@example.com).
     *
     * Result is cached so repeated template calls do not re-query the database.
     *
     * @return string
     */
    public function getMaskedEmail(): string
    {
        if ($this->maskedEmail === null) {
            $this->maskedEmail = '';
            $customerId        = $this->mfaSession->getMfaPendingCustomerId();
            if ($customerId) {
                try {
                    $customer          = $this->customerRepository->getById((int) $customerId);
                    $this->maskedEmail = $this->maskEmail($customer->getEmail());
                } catch (NoSuchEntityException $e) {
                    // Customer not found — maskedEmail stays ''
                    $this->_logger->debug($e->getMessage());
                }
            }
        }

        return $this->maskedEmail;
    }

    /**
     * Get the masked phone number for display (e.g. ***-***-1234).
     *
     * Result is cached so repeated template calls do not re-query the database.
     *
     * @return string
     */
    public function getMaskedPhone(): string
    {
        if ($this->maskedPhone === null) {
            $this->maskedPhone = '';
            $customerId        = $this->mfaSession->getMfaPendingCustomerId();
            if ($customerId) {
                $phone = $this->phoneResolver->resolve((int) $customerId);
                if ($phone !== null) {
                    $this->maskedPhone = $this->maskPhone($phone);
                }
            }
        }

        return $this->maskedPhone;
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
     * Get the URL for the change-method action.
     *
     * @return string
     */
    public function getChangeMethodUrl(): string
    {
        return $this->getUrl('mfa/verify/changemethod');
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
