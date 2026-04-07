<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Controller\Verify;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\Session as MfaSession;

/**
 * Resets the chosen delivery method so the customer can select a different one.
 */
class ChangeMethod implements HttpPostActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface               $request
     * @param \Muon\MultiFactorLogin\Model\Session                  $mfaSession
     * @param \Magento\Framework\Controller\Result\RedirectFactory  $redirectFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator        $formKeyValidator
     * @param \Magento\Framework\Message\ManagerInterface           $messageManager
     * @param \Muon\MultiFactorLogin\Model\Config                   $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly MfaSession $mfaSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly Config $config,
    ) {
    }

    /**
     * Clear the selected delivery method and redirect back to the method-selection step.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled() || !$this->mfaSession->getMfaPendingCustomerId()) {
            return $redirect->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $redirect->setPath('mfa/verify');
        }

        $this->mfaSession->unsMfaDeliveryMethod();

        return $redirect->setPath('mfa/verify');
    }
}
