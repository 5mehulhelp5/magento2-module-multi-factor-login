<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Controller\Verify;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;

/**
 * Renders the MFA verification page (method selector or token input form).
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param \Magento\Customer\Model\Session                      $customerSession
     * @param \Muon\MultiFactorLogin\Model\Session                 $mfaSession
     * @param \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory
     * @param \Magento\Framework\View\Result\PageFactory           $pageFactory
     * @param \Muon\MultiFactorLogin\Model\Config                  $config
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly MfaSession $mfaSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly PageFactory $pageFactory,
        private readonly Config $config,
    ) {
    }

    /**
     * Render the MFA verification page or redirect when not applicable.
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('customer/account/login');
        }

        // Already logged in — nothing to do here.
        if ($this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account');
        }

        // Guard: no pending MFA state means this URL was accessed directly.
        if (!$this->mfaSession->getMfaPendingCustomerId()) {
            return $redirect->setPath('customer/account/login');
        }

        return $this->pageFactory->create();
    }
}
