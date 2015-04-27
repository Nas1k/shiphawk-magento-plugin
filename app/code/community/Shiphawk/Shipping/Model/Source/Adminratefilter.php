<?php
class Shiphawk_Shipping_Model_Source_Adminratefilter
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'consumer', 'label'=>Mage::helper('adminhtml')->__('consumer')),
            array('value'=>'top_10', 'label'=>Mage::helper('adminhtml')->__('top 10')),
        );
    }
}
