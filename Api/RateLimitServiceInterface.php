<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Api;

/**
 * Service contract for MFA token-request rate limiting.
 *
 * The rate is derived from the count of token rows in the muon_mfa_token table
 * within a rolling time window, so no explicit recording call is needed.
 *
 * @api
 */
interface RateLimitServiceInterface
{
    /**
     * Check whether the customer is allowed to request a new token.
     *
     * @param int $customerId
     * @return bool
     */
    public function isRequestAllowed(int $customerId): bool;
}
