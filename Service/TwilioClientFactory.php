<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Service;

use Twilio\Rest\Client as TwilioClient;

/**
 * Factory for Twilio REST Client instances.
 *
 * Wraps the third-party constructor so that production code never uses `new`
 * directly, keeping SmsService testable via mock injection.
 */
class TwilioClientFactory
{
    /**
     * Create a Twilio client authenticated with the given credentials.
     *
     * @param string $sid       Twilio Account SID
     * @param string $authToken Twilio Auth Token (decrypted plaintext)
     * @return \Twilio\Rest\Client
     */
    public function create(string $sid, string $authToken): TwilioClient
    {
        return new TwilioClient($sid, $authToken);
    }
}
