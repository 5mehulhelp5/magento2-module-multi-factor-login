<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Api;

/**
 * Service contract for MFA token lifecycle.
 *
 * @api
 */
interface TokenServiceInterface
{
    /**
     * Generate an encrypted one-time token, persist it, and dispatch it to the customer.
     *
     * Throws LocalizedException when the rate limit is exceeded or delivery fails.
     *
     * @param int    $customerId
     * @param string $deliveryMethod One of TokenInterface::METHOD_* constants
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createAndSend(int $customerId, string $deliveryMethod): void;

    /**
     * Verify the token submitted by the customer.
     *
     * Returns true on a correct match and marks the token consumed.
     * Returns false on a wrong guess and increments the attempt counter.
     * Throws LocalizedException for terminal states: no active token, token
     * expired, or maximum verification attempts exceeded (token is invalidated).
     *
     * @param int    $customerId
     * @param string $inputToken  Raw token as entered by the customer
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function verify(int $customerId, string $inputToken): bool;
}
