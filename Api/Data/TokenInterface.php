<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Api\Data;

/**
 * MFA token DTO.
 *
 * Tokens are an internal security infrastructure entity — they are not intended
 * to be extended by third-party modules via extension attributes.
 *
 * @api
 */
interface TokenInterface
{
    public const TOKEN_ID        = 'token_id';
    public const CUSTOMER_ID     = 'customer_id';
    public const TOKEN           = 'token';
    public const DELIVERY_METHOD = 'delivery_method';
    public const IS_USED         = 'is_used';
    public const VERIFY_ATTEMPTS = 'verify_attempts';
    public const EXPIRES_AT      = 'expires_at';
    public const CREATED_AT      = 'created_at';

    public const METHOD_SMS   = 'sms';
    public const METHOD_EMAIL = 'email';

    /**
     * Get internal token ID.
     *
     * @return int|null
     */
    public function getTokenId(): ?int;

    /**
     * Set internal token ID.
     *
     * @param int $tokenId
     * @return $this
     */
    public function setTokenId(int $tokenId): self;

    /**
     * Get the owning customer ID.
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set the owning customer ID.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * Get the encrypted token value.
     *
     * @return string
     */
    public function getToken(): string;

    /**
     * Set the encrypted token value.
     *
     * @param string $token
     * @return $this
     */
    public function setToken(string $token): self;

    /**
     * Get the delivery method (sms or email).
     *
     * @return string
     */
    public function getDeliveryMethod(): string;

    /**
     * Set the delivery method.
     *
     * @param string $deliveryMethod
     * @return $this
     */
    public function setDeliveryMethod(string $deliveryMethod): self;

    /**
     * Check whether the token has been consumed or invalidated.
     *
     * @return bool
     */
    public function isUsed(): bool;

    /**
     * Mark the token as consumed or invalidated.
     *
     * @param bool $isUsed
     * @return $this
     */
    public function setIsUsed(bool $isUsed): self;

    /**
     * Get the number of failed verification attempts against this token.
     *
     * @return int
     */
    public function getVerifyAttempts(): int;

    /**
     * Set the number of failed verification attempts.
     *
     * @param int $attempts
     * @return $this
     */
    public function setVerifyAttempts(int $attempts): self;

    /**
     * Get the token expiry datetime string (UTC).
     *
     * @return string
     */
    public function getExpiresAt(): string;

    /**
     * Set the token expiry datetime string (UTC).
     *
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt(string $expiresAt): self;

    /**
     * Get the token creation datetime string (UTC).
     *
     * @return string
     */
    public function getCreatedAt(): string;
}
