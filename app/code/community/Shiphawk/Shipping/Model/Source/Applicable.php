<?php
class Shiphawk_Shipping_Model_Source_Applicable
{
    public function toOptionArray()
    {
        return array(
            array('value'=>1, 'label'=>Mage::helper('adminhtml')->__('For separate parcels in multi-parcel carts')),
            array('value'=>2, 'label'=>Mage::helper('adminhtml')->__('For all carts')),
            array('value'=>0, 'label'=>Mage::helper('adminhtml')->__('Disabled')),
        );
    }
}