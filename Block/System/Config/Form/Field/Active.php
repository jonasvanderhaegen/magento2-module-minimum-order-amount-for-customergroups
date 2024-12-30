<?php

namespace Jvdh\MinOrderAmount\Block\System\Config\Form\Field;

use \Magento\Framework\View\Element\BlockInterface;
use \Jvdh\MinOrderAmount\Block\Adminhtml\Form\Field\CustomerGroup;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class Active extends AbstractFieldArray
{
    protected $_columns = [];
    protected $customerGroupRenderer;
    protected $_addAfter = true;
    protected $_addButtonLabel;

    public function renderCellTemplate($columnName): string
    {
        if ($columnName == "active") {
            $this->_columns[$columnName]['class'] = 'input-text required-entry validate-number';
            $this->_columns[$columnName]['style'] = 'width:100px';
        }
        return parent::renderCellTemplate($columnName);
    }

    /**
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_addButtonLabel = __('Add');
    }

    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'customer_group',
            [
                'label' => __('Customer Group'), 'size' => '300px',
                'renderer' => $this->getCustomerGroupRenderer(),
            ]
        );
        $this->addColumn('active', ['label' => __('Minimum Amount')]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    protected function getCustomerGroupRenderer(): CustomerGroup
    {
        if (!$this->customerGroupRenderer) {
            $this->customerGroupRenderer = $this->getLayout()->createBlock(
                \Jvdh\MinOrderAmount\Block\Adminhtml\Form\Field\CustomerGroup::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->customerGroupRenderer;
    }

    protected function _prepareArrayRow(\Magento\Framework\DataObject $row): void
    {
        $customerGroup = $row->getCustomerGroup();
        $options = [];
        if ($customerGroup) {
            $calcOptionHash = $this->getCustomerGroupRenderer()->calcOptionHash($customerGroup);
            $options['option_' . $calcOptionHash] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }
}
