<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Block\Adminhtml\Form\Field;

use PayPal\Braintree\Helper\CcType;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class CcTypes extends Select
{
    /**
     * @var CcType
     */
    private CcType $ccTypeHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param CcType $ccTypeHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CcType $ccTypeHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->ccTypeHelper = $ccTypeHelper;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->ccTypeHelper->getCcTypes());
        }
        $this->setClass('cc-type-select');
        $this->setExtraParams('multiple="multiple"');
        return parent::_toHtml();
    }

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return self
     */
    public function setInputName(string $value): CcTypes
    {
        return $this->setName($value . '[]');
    }
}
