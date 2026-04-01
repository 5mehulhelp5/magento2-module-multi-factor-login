<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Admin configuration reader for Muon_MultiFactorLogin.
 */
class Config
{
    private const XML_PATH_ENABLED             = 'muon_multifactorlogin/general/enabled';
    private const XML_PATH_TOKEN_LENGTH        = 'muon_multifactorlogin/token/length';
    private const XML_PATH_TOKEN_CHARACTERS    = 'muon_multifactorlogin/token/characters';
    private const XML_PATH_TOKEN_LIFETIME      = 'muon_multifactorlogin/token/lifetime';
    private const XML_PATH_DELIVERY_METHODS    = 'muon_multifactorlogin/delivery/methods';
    private const XML_PATH_MAX_REQUESTS        = 'muon_multifactorlogin/rate_limit/max_requests';
    private const XML_PATH_WINDOW_MINUTES      = 'muon_multifactorlogin/rate_limit/window_minutes';
    private const XML_PATH_MAX_VERIFY_ATTEMPTS = 'muon_multifactorlogin/rate_limit/max_verify_attempts';
    private const XML_PATH_TWILIO_ACCOUNT_SID  = 'muon_multifactorlogin/twilio/account_sid';
    private const XML_PATH_TWILIO_AUTH_TOKEN   = 'muon_multifactorlogin/twilio/auth_token';
    private const XML_PATH_TWILIO_FROM_NUMBER  = 'muon_multifactorlogin/twilio/from_number';
    private const XML_PATH_EMAIL_SENDER_EMAIL  = 'muon_multifactorlogin/email/sender_email';
    private const XML_PATH_EMAIL_SENDER_NAME   = 'muon_multifactorlogin/email/sender_name';
    private const XML_PATH_EMAIL_TEMPLATE      = 'muon_multifactorlogin/email/template';

    private const DEFAULT_TOKEN_LENGTH        = 6;
    private const DEFAULT_TOKEN_CHARACTERS    = '0123456789';
    private const DEFAULT_TOKEN_LIFETIME      = 10;
    private const DEFAULT_MAX_REQUESTS        = 3;
    private const DEFAULT_WINDOW_MINUTES      = 60;
    private const DEFAULT_MAX_VERIFY_ATTEMPTS = 5;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Encryption\EncryptorInterface   $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly \Magento\Framework\Encryption\EncryptorInterface $encryptor,
    ) {
    }

    /**
     * Check whether MFA is enabled for the given scope.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return bool
     */
    public function isEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, $scopeType, $scopeCode);
    }

    /**
     * Get the token length.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getTokenLength(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_TOKEN_LENGTH, $scopeType, $scopeCode);
        return ($value >= 4 && $value <= 12) ? $value : self::DEFAULT_TOKEN_LENGTH;
    }

    /**
     * Get the character set used for token generation.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return string
     */
    public function getTokenCharacters(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): string {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_TOKEN_CHARACTERS, $scopeType, $scopeCode);
        return $value !== '' ? $value : self::DEFAULT_TOKEN_CHARACTERS;
    }

    /**
     * Get the token lifetime in minutes.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getTokenLifetime(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_TOKEN_LIFETIME, $scopeType, $scopeCode);
        return $value > 0 ? $value : self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Get the allowed delivery methods setting: 'sms', 'email', or 'both'.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return string
     */
    public function getAllowedDeliveryMethods(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): string {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_DELIVERY_METHODS, $scopeType, $scopeCode);
    }

    /**
     * Get the maximum number of token requests allowed per rate-limit window.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getMaxRequests(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_REQUESTS, $scopeType, $scopeCode);
        return $value > 0 ? $value : self::DEFAULT_MAX_REQUESTS;
    }

    /**
     * Get the rate-limit rolling window duration in minutes.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getRateLimitWindowMinutes(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_WINDOW_MINUTES, $scopeType, $scopeCode);
        return $value > 0 ? $value : self::DEFAULT_WINDOW_MINUTES;
    }

    /**
     * Get the maximum number of failed verification attempts before a token is invalidated.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getMaxVerifyAttempts(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_VERIFY_ATTEMPTS, $scopeType, $scopeCode);
        return $value > 0 ? $value : self::DEFAULT_MAX_VERIFY_ATTEMPTS;
    }

    /**
     * Get the Twilio Account SID.
     *
     * @return string
     */
    public function getTwilioAccountSid(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TWILIO_ACCOUNT_SID,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the decrypted Twilio Auth Token.
     *
     * @return string
     */
    public function getTwilioAuthToken(): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(
            self::XML_PATH_TWILIO_AUTH_TOKEN,
            ScopeInterface::SCOPE_STORE
        );
        return $encrypted !== '' ? $this->encryptor->decrypt($encrypted) : '';
    }

    /**
     * Get the Twilio From number (E.164 format).
     *
     * @return string
     */
    public function getTwilioFromNumber(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TWILIO_FROM_NUMBER,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the sender email address (falls back to store general contact if empty).
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return string
     */
    public function getSenderEmail(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): string {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_EMAIL_SENDER_EMAIL, $scopeType, $scopeCode);
        if ($value !== '') {
            return $value;
        }
        return (string) $this->scopeConfig->getValue('trans_email/ident_general/email', $scopeType, $scopeCode);
    }

    /**
     * Get the sender name.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return string
     */
    public function getSenderName(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): string {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMAIL_SENDER_NAME, $scopeType, $scopeCode);
    }

    /**
     * Get the configured email template identifier.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return string
     */
    public function getEmailTemplate(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): string {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMAIL_TEMPLATE, $scopeType, $scopeCode);
    }
}
