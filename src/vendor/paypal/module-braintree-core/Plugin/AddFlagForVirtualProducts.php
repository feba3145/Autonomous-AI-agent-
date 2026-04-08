<?php
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Checkout\CustomerData\AbstractItem;
use Magento\Quote\Model\Quote\Item;

/**
 * A plugin class to add the 'is_virtual' property when quote is virtual
 *
 * Class AddFlagForVirtualProducts
 */
class AddFlagForVirtualProducts
{
    /**
     * Set 'is_virtual' to the quote item when item is virtual.
     *
     * @param AbstractItem $subject
     * @param array $result
     * @param Item $item
     * @return array
     */
    public function afterGetItemData(AbstractItem $subject, array $result, Item $item): array
    {
        if ($item->getIsVirtual()) {
            $result['is_virtual'] = 1;
        }
        return $result;
    }
}
