<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Service;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Muon\MultiFactorLogin\Model\Config;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Dispatches MFA tokens via SMS using the Twilio REST API.
 */
class SmsService
{
    /**
     * @param \Muon\MultiFactorLogin\Model\Config                       $config
     * @param \Magento\Customer\Api\CustomerRepositoryInterface         $customerRepository
     * @param \Magento\Customer\Api\AddressRepositoryInterface          $addressRepository
     * @param \Psr\Log\LoggerInterface                                  $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send the MFA token to the customer's billing address phone number via SMS.
     *
     * @param int    $customerId
     * @param string $token      Plain-text token to include in the message
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function send(int $customerId, string $token): void
    {
        $phone = $this->resolvePhone($customerId);

        $sid      = $this->config->getTwilioAccountSid();
        $authToken = $this->config->getTwilioAuthToken();
        $from     = $this->config->getTwilioFromNumber();

        if ($sid === '' || $authToken === '' || $from === '') {
            throw new LocalizedException(__('SMS delivery is not configured. Please contact support.'));
        }

        $body = (string) __(
            'Your verification code is: %1. It expires in %2 minutes.',
            $token,
            $this->config->getTokenLifetime()
        );

        try {
            $twilio = new TwilioClient($sid, $authToken);
            $twilio->messages->create($phone, ['from' => $from, 'body' => $body]);
        } catch (TwilioException $e) {
            $this->logger->error(
                'Muon_MultiFactorLogin: Twilio SMS delivery failed.',
                ['exception' => $e, 'customer_id' => $customerId],
            );
            throw new LocalizedException(__('Unable to send the verification code by SMS. Please try again.'), $e);
        }
    }

    /**
     * Resolve the customer's phone number from their default billing address.
     *
     * @param int $customerId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function resolvePhone(int $customerId): string
    {
        try {
            $customer         = $this->customerRepository->getById($customerId);
            $billingAddressId = $customer->getDefaultBilling();

            if (!$billingAddressId) {
                throw new LocalizedException(
                    __('No billing address found. Cannot send SMS verification code.')
                );
            }

            $address = $this->addressRepository->getById((int) $billingAddressId);
            $phone   = (string) $address->getTelephone();

            if ($phone === '') {
                throw new LocalizedException(
                    __('No phone number found on your billing address. Cannot send SMS verification code.')
                );
            }

            return $phone;
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(
                __('Unable to retrieve customer data for SMS delivery.'),
                $e
            );
        }
    }
}
