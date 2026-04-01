<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * MFA token resource model.
 */
class Token extends AbstractDb
{
    public const TABLE_NAME = 'muon_mfa_token';
    public const ID_FIELD   = 'token_id';

    /**
     * Initialize resource model table and primary key.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }
}
