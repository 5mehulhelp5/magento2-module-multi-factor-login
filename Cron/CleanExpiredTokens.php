<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Muon\MultiFactorLogin\Model\ResourceModel\Token as TokenResource;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Nightly cleanup of expired and consumed MFA tokens.
 */
class CleanExpiredTokens
{
    /**
     * @param \Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory $collectionFactory
     * @param \Muon\MultiFactorLogin\Model\ResourceModel\Token                   $tokenResource
     * @param \Magento\Framework\Stdlib\DateTime\DateTime                        $dateTime
     * @param \Psr\Log\LoggerInterface                                           $logger
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly TokenResource $tokenResource,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Delete tokens that have expired.
     *
     * @return void
     */
    public function execute(): void
    {
        $now        = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('expires_at', ['lt' => $now]);

        $count = 0;
        foreach ($collection as $token) {
            try {
                $this->tokenResource->delete($token);
                $count++;
            } catch (\Exception $e) {
                $this->logger->error(
                    'Muon_MultiFactorLogin: failed to delete expired token.',
                    ['exception' => $e, 'token_id' => $token->getId()],
                );
            }
        }

        if ($count > 0) {
            $this->logger->info(
                sprintf('Muon_MultiFactorLogin: deleted %d expired MFA token(s).', $count)
            );
        }
    }
}
