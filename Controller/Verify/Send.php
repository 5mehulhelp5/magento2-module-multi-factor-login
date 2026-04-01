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
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Api\TokenServiceInterface;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;

/**
 * Generates and dispatches a new MFA token to the customer via the selected method.
 */
class Send implements HttpPostActionInterface
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
     * Handle token send request.
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

        $method = (string) $this->request->getParam('delivery_method');

        // Validate that the requested method is available for this customer.
        $availableMethods = $this->mfaSession->getMfaAvailableMethods() ?? [];
        if (!in_array($method, [TokenInterface::METHOD_SMS, TokenInterface::METHOD_EMAIL], true)
            || !in_array($method, $availableMethods, true)
        ) {
            $this->messageManager->addErrorMessage(__('Invalid delivery method selected.'));
            return $redirect->setPath('mfa/verify');
        }

        try {
            $this->tokenService->createAndSend((int) $pendingId, $method);
            $this->mfaSession->setMfaDeliveryMethod($method);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('mfa/verify');
        }

        return $redirect->setPath('mfa/verify');
    }
}
