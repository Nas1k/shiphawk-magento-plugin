<?php
class Shiphawk_Shipping_Block_Adminhtml_Origins extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    protected function _construct()
    {
        $this->_addButtonLabel = Mage::helper('shiphawk_shipping')->__('Add New Origins');

        $this->_blockGroup = 'shiphawk_shipping';
        $this->_controller = 'adminhtml_origins';
        $this->_headerText = Mage::helper('shiphawk_shipping')->__('Origins');
    }
}
