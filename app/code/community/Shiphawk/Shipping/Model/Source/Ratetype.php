<?php
class Shiphawk_Shipping_Model_Source_Ratetype
{
    public function toOptionArray()
    {
        return array(
            array('value'=>1, 'label'=>Mage::helper('adminhtml')->__('Per Parcel')),
            array('value'=>2, 'label'=>Mage::helper('adminhtml')->__('Per Item')),
        );
    }
}