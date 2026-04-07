<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Muon\MultiFactorLogin\Model\ResourceModel\Token as TokenResource;
use Psr\Log\LoggerInterface;

/**
 * Nightly cleanup of expired and consumed MFA tokens.
 */
class CleanExpiredTokens
{
    /**
     * @param \Muon\MultiFactorLogin\Model\ResourceModel\Token $tokenResource
     * @param \Magento\Framework\Stdlib\DateTime\DateTime      $dateTime
     * @param \Psr\Log\LoggerInterface                         $logger
     */
    public function __construct(
        private readonly TokenResource $tokenResource,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Delete all expired tokens in a single bulk query.
     *
     * @return void
     */
    public function execute(): void
    {
        $now        = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $connection = $this->tokenResource->getConnection();
        $count      = $connection->delete(
            $this->tokenResource->getMainTable(),
            ['expires_at < ?' => $now]
        );

        if ($count > 0) {
            $this->logger->info(
                sprintf('Muon_MultiFactorLogin: deleted %d expired MFA token(s).', $count)
            );
        }
    }
}
