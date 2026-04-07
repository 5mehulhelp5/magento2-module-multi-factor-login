<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Service;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Resolves a customer's phone number from their default billing address.
 *
 * Centralises billing-address phone lookup that was previously duplicated
 * across LoginPostPlugin, SmsService, and Block\Verify\Form.
 */
class CustomerPhoneResolver
{
    /**
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Api\AddressRepositoryInterface  $addressRepository
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
    ) {
    }

    /**
     * Return the phone number from the customer's default billing address.
     *
     * Returns null when the customer has no billing address or no telephone on it.
     *
     * @param int $customerId
     * @return string|null
     */
    public function resolve(int $customerId): ?string
    {
        try {
            $customer         = $this->customerRepository->getById($customerId);
            $billingAddressId = $customer->getDefaultBilling();

            if (!$billingAddressId) {
                return null;
            }

            $address = $this->addressRepository->getById((int) $billingAddressId);
            $phone   = (string) $address->getTelephone();

            return $phone !== '' ? $phone : null;
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
