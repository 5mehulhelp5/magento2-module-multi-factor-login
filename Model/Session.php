<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model;

use Magento\Framework\Session\SessionManager;

/**
 * MFA session — stores pending authentication state in an isolated namespace.
 *
 * Magic setters / getters provided by SessionManager:
 *   setMfaPendingCustomerId(int)   / getMfaPendingCustomerId(): ?int
 *   setMfaAvailableMethods(array)  / getMfaAvailableMethods(): ?array
 *   setMfaDeliveryMethod(string)   / getMfaDeliveryMethod(): ?string
 *   unsMfaPendingCustomerId()
 *   unsMfaAvailableMethods()
 *   unsMfaDeliveryMethod()
 */
class Session extends SessionManager
{
}
