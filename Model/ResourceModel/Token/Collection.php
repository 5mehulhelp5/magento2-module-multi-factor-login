<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model\ResourceModel\Token;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Muon\MultiFactorLogin\Model\Token;
use Muon\MultiFactorLogin\Model\ResourceModel\Token as TokenResource;

/**
 * MFA token collection.
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize model and resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Token::class, TokenResource::class);
    }
}
