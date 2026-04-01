<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model;

use Magento\Framework\Model\AbstractModel;
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Model\ResourceModel\Token as TokenResource;

/**
 * MFA token ORM model.
 */
class Token extends AbstractModel implements TokenInterface
{
    /**
     * Initialize the resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TokenResource::class);
    }

    /**
     * Get internal token ID.
     *
     * @return int|null
     */
    public function getTokenId(): ?int
    {
        $value = $this->getData(self::TOKEN_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * Set internal token ID.
     *
     * @param int $tokenId
     * @return $this
     */
    public function setTokenId(int $tokenId): self
    {
        return $this->setData(self::TOKEN_ID, $tokenId);
    }

    /**
     * Get the owning customer ID.
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    /**
     * Set the owning customer ID.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * Get the encrypted token value.
     *
     * @return string
     */
    public function getToken(): string
    {
        return (string) $this->getData(self::TOKEN);
    }

    /**
     * Set the encrypted token value.
     *
     * @param string $token
     * @return $this
     */
    public function setToken(string $token): self
    {
        return $this->setData(self::TOKEN, $token);
    }

    /**
     * Get the delivery method.
     *
     * @return string
     */
    public function getDeliveryMethod(): string
    {
        return (string) $this->getData(self::DELIVERY_METHOD);
    }

    /**
     * Set the delivery method.
     *
     * @param string $deliveryMethod
     * @return $this
     */
    public function setDeliveryMethod(string $deliveryMethod): self
    {
        return $this->setData(self::DELIVERY_METHOD, $deliveryMethod);
    }

    /**
     * Check whether the token has been consumed or invalidated.
     *
     * @return bool
     */
    public function isUsed(): bool
    {
        return (bool) $this->getData(self::IS_USED);
    }

    /**
     * Mark the token as consumed or invalidated.
     *
     * @param bool $isUsed
     * @return $this
     */
    public function setIsUsed(bool $isUsed): self
    {
        return $this->setData(self::IS_USED, (int) $isUsed);
    }

    /**
     * Get the number of failed verification attempts.
     *
     * @return int
     */
    public function getVerifyAttempts(): int
    {
        return (int) $this->getData(self::VERIFY_ATTEMPTS);
    }

    /**
     * Set the number of failed verification attempts.
     *
     * @param int $attempts
     * @return $this
     */
    public function setVerifyAttempts(int $attempts): self
    {
        return $this->setData(self::VERIFY_ATTEMPTS, $attempts);
    }

    /**
     * Get the token expiry datetime string (UTC).
     *
     * @return string
     */
    public function getExpiresAt(): string
    {
        return (string) $this->getData(self::EXPIRES_AT);
    }

    /**
     * Set the token expiry datetime string (UTC).
     *
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt(string $expiresAt): self
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * Get the token creation datetime string (UTC).
     *
     * @return string
     */
    public function getCreatedAt(): string
    {
        return (string) $this->getData(self::CREATED_AT);
    }
}
