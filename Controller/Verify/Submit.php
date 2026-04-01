<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Controller\Verify;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Muon\MultiFactorLogin\Api\TokenServiceInterface;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;

/**
 * Verifies the token submitted by the customer and completes or cancels the login.
 */
class Submit implements HttpPostActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface               $request
     * @param \Magento\Customer\Model\Session                       $customerSession
     * @param \Muon\MultiFactorLogin\Model\Session                  $mfaSession
     * @param \Magento\Framework\Controller\Result\RedirectFactory  $redirectFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator        $formKeyValidator
     * @param \Magento\Framework\Message\ManagerInterface           $messageManager
     * @param \Muon\MultiFactorLogin\Api\TokenServiceInterface      $tokenService
     * @param \Muon\MultiFactorLogin\Model\Config                   $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly MfaSession $mfaSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly TokenServiceInterface $tokenService,
        private readonly Config $config,
    ) {
    }

    /**
     * Handle token verification submission.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('customer/account/login');
        }

        // Guard: no pending MFA state — URL accessed directly.
        $pendingId = $this->mfaSession->getMfaPendingCustomerId();
        if (!$pendingId) {
            return $redirect->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $redirect->setPath('mfa/verify');
        }

        $inputToken = trim((string) $this->request->getParam('token'));

        if ($inputToken === '') {
            $this->messageManager->addErrorMessage(__('Please enter the verification code.'));
            return $redirect->setPath('mfa/verify');
        }

        try {
            $verified = $this->tokenService->verify((int) $pendingId, $inputToken);
        } catch (LocalizedException $e) {
            // Terminal failure (expired, no token, max attempts exceeded).
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->clearMfaSession();
            return $redirect->setPath('customer/account/login');
        }

        if (!$verified) {
            $this->messageManager->addErrorMessage(__('Incorrect verification code. Please try again.'));
            return $redirect->setPath('mfa/verify');
        }

        // Verification passed — complete the login.
        $this->customerSession->loginById((int) $pendingId);
        $this->customerSession->regenerateId();
        $this->clearMfaSession();

        return $redirect->setPath('customer/account');
    }

    /**
     * Remove all MFA-specific session data.
     *
     * @return void
     */
    private function clearMfaSession(): void
    {
        $this->mfaSession->unsMfaPendingCustomerId();
        $this->mfaSession->unsMfaAvailableMethods();
        $this->mfaSession->unsMfaDeliveryMethod();
    }
}
