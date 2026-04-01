<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Muon\MultiFactorLogin\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Dispatches MFA tokens via transactional email.
 */
class EmailService
{
    private const DEFAULT_TEMPLATE_ID = 'muon_multifactorlogin_email_template';

    /**
     * @param \Muon\MultiFactorLogin\Model\Config                        $config
     * @param \Magento\Framework\Mail\Template\TransportBuilder          $transportBuilder
     * @param \Magento\Store\Model\StoreManagerInterface                 $storeManager
     * @param \Magento\Customer\Api\CustomerRepositoryInterface          $customerRepository
     * @param \Psr\Log\LoggerInterface                                   $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send the MFA token to the customer's registered email address.
     *
     * @param int    $customerId
     * @param string $token      Plain-text token to include in the message
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function send(int $customerId, string $token): void
    {
        $customer = $this->customerRepository->getById($customerId);
        $store    = $this->storeManager->getStore();

        $templateId = $this->config->getEmailTemplate();
        if ($templateId === '') {
            $templateId = self::DEFAULT_TEMPLATE_ID;
        }

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area'  => Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'customer_name'  => $customer->getFirstname(),
                    'token'          => $token,
                    'token_lifetime' => $this->config->getTokenLifetime(),
                    'store'          => $store,
                ])
                ->setFrom([
                    'email' => $this->config->getSenderEmail(),
                    'name'  => $this->config->getSenderName(),
                ])
                ->addTo($customer->getEmail(), $customer->getFirstname())
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error(
                'Muon_MultiFactorLogin: failed to send MFA token email.',
                ['exception' => $e, 'customer_id' => $customerId],
            );
            throw new LocalizedException(__('Unable to send the verification code by email. Please try again.'), $e);
        }
    }
}
