<?php
class Shiphawk_Shipping_Model_Source_Ratefilter
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'consumer', 'label'=>Mage::helper('adminhtml')->__('consumer')),
            array('value'=>'best', 'label'=>Mage::helper('adminhtml')->__('best')),
        );
    }
}
