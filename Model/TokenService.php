<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Api\RateLimitServiceInterface;
use Muon\MultiFactorLogin\Api\TokenServiceInterface;
use Muon\MultiFactorLogin\Model\ResourceModel\Token as TokenResource;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory;
use Muon\MultiFactorLogin\Service\EmailService;
use Muon\MultiFactorLogin\Service\SmsService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * Coordination service — coupling is inherent to orchestrating token generation,
 * encryption, persistence, rate limiting, and multi-channel dispatch.
 */
class TokenService implements TokenServiceInterface
{
    /**
     * @param \Muon\MultiFactorLogin\Model\Config                                $config
     * @param \Muon\MultiFactorLogin\Model\TokenFactory                          $tokenFactory
     * @param \Muon\MultiFactorLogin\Model\ResourceModel\Token                   $tokenResource
     * @param \Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory $collectionFactory
     * @param \Muon\MultiFactorLogin\Api\RateLimitServiceInterface               $rateLimitService
     * @param \Muon\MultiFactorLogin\Service\EmailService                        $emailService
     * @param \Muon\MultiFactorLogin\Service\SmsService                          $smsService
     * @param \Magento\Framework\Encryption\EncryptorInterface                   $encryptor
     * @param \Magento\Framework\Stdlib\DateTime\DateTime                        $dateTime
     */
    public function __construct(
        private readonly Config $config,
        private readonly TokenFactory $tokenFactory,
        private readonly TokenResource $tokenResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly RateLimitServiceInterface $rateLimitService,
        private readonly EmailService $emailService,
        private readonly SmsService $smsService,
        private readonly EncryptorInterface $encryptor,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Generate an encrypted one-time token, persist it, and dispatch it to the customer.
     *
     * @param int    $customerId
     * @param string $deliveryMethod
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createAndSend(int $customerId, string $deliveryMethod): void
    {
        if (!$this->rateLimitService->isRequestAllowed($customerId)) {
            throw new LocalizedException(
                __('Too many verification code requests. Please wait before requesting a new one.')
            );
        }

        $this->invalidateActiveTokens($customerId);

        $rawToken = $this->generateToken();

        $lifetime  = $this->config->getTokenLifetime();
        $expiresAt = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('+' . $lifetime . ' minutes'));

        $token = $this->tokenFactory->create();
        $token->setCustomerId($customerId);
        $token->setToken($this->encryptor->encrypt($rawToken));
        $token->setDeliveryMethod($deliveryMethod);
        $token->setIsUsed(false);
        $token->setVerifyAttempts(0);
        $token->setExpiresAt($expiresAt);
        $this->tokenResource->save($token);

        if ($deliveryMethod === TokenInterface::METHOD_SMS) {
            $this->smsService->send($customerId, $rawToken);
            return;
        }

        $this->emailService->send($customerId, $rawToken);
    }

    /**
     * Verify the token submitted by the customer.
     *
     * @param int    $customerId
     * @param string $inputToken
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function verify(int $customerId, string $inputToken): bool
    {
        $token = $this->loadActiveToken($customerId);

        if ($token === null) {
            throw new LocalizedException(
                __('No active verification code found. Please request a new one.')
            );
        }

        $now = $this->dateTime->gmtDate('Y-m-d H:i:s');
        if ($token->getExpiresAt() < $now) {
            throw new LocalizedException(
                __('Your verification code has expired. Please request a new one.')
            );
        }

        $maxAttempts = $this->config->getMaxVerifyAttempts();
        if ($token->getVerifyAttempts() >= $maxAttempts) {
            $token->setIsUsed(true);
            $this->tokenResource->save($token);
            throw new LocalizedException(
                __('Too many incorrect attempts. Please log in again and request a new code.')
            );
        }

        $storedToken = $this->encryptor->decrypt($token->getToken());

        if (!hash_equals($storedToken, $inputToken)) {
            $token->setVerifyAttempts($token->getVerifyAttempts() + 1);

            if ($token->getVerifyAttempts() >= $maxAttempts) {
                $token->setIsUsed(true);
            }

            $this->tokenResource->save($token);
            return false;
        }

        $token->setIsUsed(true);
        $this->tokenResource->save($token);

        return true;
    }

    /**
     * Generate a cryptographically random token from the configured character set.
     *
     * @return string
     */
    private function generateToken(): string
    {
        $length     = $this->config->getTokenLength();
        $characters = $this->config->getTokenCharacters();
        $charCount  = strlen($characters);
        $token      = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, $charCount - 1)];
        }

        return $token;
    }

    /**
     * Load the most recent active (not used, not expired) token for the customer.
     *
     * @param int $customerId
     * @return \Muon\MultiFactorLogin\Model\Token|null
     */
    private function loadActiveToken(int $customerId): ?Token
    {
        $now        = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('is_used', 0)
            ->addFieldToFilter('expires_at', ['gteq' => $now])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);

        /** @var \Muon\MultiFactorLogin\Model\Token $token */
        $token = $collection->getFirstItem();
        return $token->getTokenId() !== null ? $token : null;
    }

    /**
     * Invalidate any previously active tokens for the customer before issuing a new one.
     *
     * This prevents an attacker from using an old token that was never consumed.
     *
     * @param int $customerId
     * @return void
     */
    private function invalidateActiveTokens(int $customerId): void
    {
        $now        = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('is_used', 0)
            ->addFieldToFilter('expires_at', ['gteq' => $now]);

        foreach ($collection as $token) {
            /** @var \Muon\MultiFactorLogin\Model\Token $token */
            $token->setIsUsed(true);
            $this->tokenResource->save($token);
        }
    }
}
