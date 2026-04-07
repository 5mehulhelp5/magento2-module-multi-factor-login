<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Service;

use Magento\Framework\Exception\LocalizedException;
use Muon\MultiFactorLogin\Model\Config;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;

/**
 * Dispatches MFA tokens via SMS using the Twilio REST API.
 */
class SmsService
{
    /**
     * @param \Muon\MultiFactorLogin\Model\Config                      $config
     * @param \Muon\MultiFactorLogin\Service\CustomerPhoneResolver     $phoneResolver
     * @param \Muon\MultiFactorLogin\Service\TwilioClientFactory       $twilioClientFactory
     * @param \Psr\Log\LoggerInterface                                 $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerPhoneResolver $phoneResolver,
        private readonly TwilioClientFactory $twilioClientFactory,
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
        $phone = $this->phoneResolver->resolve($customerId);

        if ($phone === null) {
            throw new LocalizedException(
                __('No phone number found on your billing address. Cannot send SMS verification code.')
            );
        }

        $sid       = $this->config->getTwilioAccountSid();
        $authToken = $this->config->getTwilioAuthToken();
        $from      = $this->config->getTwilioFromNumber();

        if ($sid === '' || $authToken === '' || $from === '') {
            throw new LocalizedException(__('SMS delivery is not configured. Please contact support.'));
        }

        $body = (string) __(
            'Your verification code is: %1. It expires in %2 minutes.',
            $token,
            $this->config->getTokenLifetime()
        );

        try {
            $twilio = $this->twilioClientFactory->create($sid, $authToken);
            $twilio->messages->create($phone, ['from' => $from, 'body' => $body]);
        } catch (TwilioException $e) {
            $this->logger->error(
                'Muon_MultiFactorLogin: Twilio SMS delivery failed.',
                ['exception' => $e, 'customer_id' => $customerId],
            );
            throw new LocalizedException(__('Unable to send the verification code by SMS. Please try again.'), $e);
        }
    }
}
