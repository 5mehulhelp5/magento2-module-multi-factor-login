<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Api;

/**
 * Service contract for MFA token-request rate limiting.
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

    /**
     * Record a token request for the customer (called after a token is created).
     *
     * This is a no-op in the current implementation because the count is derived
     * from the muon_mfa_token table directly, but the interface is kept for
     * possible future adapters (e.g. Redis-based rate limiting).
     *
     * @param int $customerId
     * @return void
     */
    public function recordRequest(int $customerId): void;
}
