<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Controller\Verify;

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
 * Resends the MFA token via the same delivery method as the original request.
 */
class Resend implements HttpPostActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface               $request
     * @param \Muon\MultiFactorLogin\Model\Session                  $mfaSession
     * @param \Magento\Framework\Controller\Result\RedirectFactory  $redirectFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator        $formKeyValidator
     * @param \Magento\Framework\Message\ManagerInterface           $messageManager
     * @param \Muon\MultiFactorLogin\Api\TokenServiceInterface      $tokenService
     * @param \Muon\MultiFactorLogin\Model\Config                   $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly MfaSession $mfaSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly TokenServiceInterface $tokenService,
        private readonly Config $config,
    ) {
    }

    /**
     * Handle token resend request.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('customer/account/login');
        }

        // Guard: no pending MFA state.
        $pendingId = $this->mfaSession->getMfaPendingCustomerId();
        if (!$pendingId) {
            return $redirect->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $redirect->setPath('mfa/verify');
        }

        $method = (string) $this->mfaSession->getMfaDeliveryMethod();
        if ($method === '') {
            $this->messageManager->addErrorMessage(__('Please select a delivery method first.'));
            return $redirect->setPath('mfa/verify');
        }

        try {
            $this->tokenService->createAndSend((int) $pendingId, $method);
            $this->messageManager->addSuccessMessage(__('A new verification code has been sent.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('mfa/verify');
    }
}
