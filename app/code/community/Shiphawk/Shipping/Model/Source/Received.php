<?php
class Shiphawk_Shipping_Model_Source_Received
{
    public function toOptionArray()
    {
        return array(
            array('value'=>0, 'label'=>Mage::helper('adminhtml')->__('customer')),
            array('value'=>1, 'label'=>Mage::helper('adminhtml')->__('administrator')),
        );
    }
}
