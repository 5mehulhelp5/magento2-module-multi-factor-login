<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model\Session;

use Magento\Framework\Session\Storage as FrameworkStorage;

/**
 * Session storage for MFA state, isolated in the 'mfa' namespace.
 *
 * Using a dedicated namespace prevents CustomerSession::logout() (and its
 * session_regenerate_id() call) from affecting MFA pending state.
 * The 'mfa' namespace is injected via di.xml rather than a constructor override.
 */
class Storage extends FrameworkStorage
{
}
