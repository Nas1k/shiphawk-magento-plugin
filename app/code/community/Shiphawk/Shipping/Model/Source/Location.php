<?php
class Shiphawk_Shipping_Model_Source_Location
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'commercial', 'label'=>Mage::helper('adminhtml')->__('commercial')),
            array('value'=>'residential', 'label'=>Mage::helper('adminhtml')->__('residential')),
        );
    }
}
