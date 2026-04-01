<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Muon\MultiFactorLogin\Api\RateLimitServiceInterface;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory;

/**
 * Rate-limit service backed by the muon_mfa_token table.
 *
 * Counts token rows created within the rolling window instead of maintaining
 * a separate counter table — keeping the schema minimal.
 */
class RateLimitService implements RateLimitServiceInterface
{
    /**
     * @param \Muon\MultiFactorLogin\Model\Config                                $config
     * @param \Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory $collectionFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime                        $dateTime
     */
    public function __construct(
        private readonly Config $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Check whether the customer is allowed to request a new token.
     *
     * @param int $customerId
     * @return bool
     */
    public function isRequestAllowed(int $customerId): bool
    {
        $windowMinutes = $this->config->getRateLimitWindowMinutes();
        $windowStart   = $this->dateTime->gmtDate(
            'Y-m-d H:i:s',
            strtotime('-' . $windowMinutes . ' minutes')
        );

        $count = $this->collectionFactory->create()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('created_at', ['gteq' => $windowStart])
            ->getSize();

        return $count < $this->config->getMaxRequests();
    }

    /**
     * No-op: the count is derived from the token table directly.
     *
     * @param int $customerId
     * @return void
     */
    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock -- intentional no-op; rate is derived from COUNT on token rows
    public function recordRequest(int $customerId): void
    {
    }
}
