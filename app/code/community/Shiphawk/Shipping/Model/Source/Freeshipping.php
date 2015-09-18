<?php
class Shiphawk_Shipping_Model_Source_Freeshipping
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'none', 'label'=>Mage::helper('adminhtml')->__('None')),
            array('value'=>'cheapest', 'label'=>Mage::helper('adminhtml')->__('Cheapest')),
            array('value'=>'all', 'label'=>Mage::helper('adminhtml')->__('All'))
        );
    }
}
