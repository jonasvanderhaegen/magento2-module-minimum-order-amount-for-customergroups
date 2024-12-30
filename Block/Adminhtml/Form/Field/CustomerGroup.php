<?php

namespace Jvdh\MinOrderAmount\Block\Adminhtml\Form\Field;

use Magento\Customer\Model\GroupFactory;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class CustomerGroup extends Select
{
    public function __construct(
        Context $context,
        private GroupFactory $groupfactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): mixed
    {
        if (!$this->getOptions()) {
            $customerGroupCollection = $this->groupfactory->create()->getCollection();
            foreach ($customerGroupCollection as $customerGroup) {
                $this->addOption($customerGroup->getCustomerGroupId(), $customerGroup->getCustomerGroupCode());
            }
        }
        return parent::_toHtml();
    }

    public function setInputName($value): CustomerGroup
    {
        return $this->setName($value);
    }
}
