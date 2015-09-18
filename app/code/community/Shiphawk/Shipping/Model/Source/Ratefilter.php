<?php
class Shiphawk_Shipping_Model_Source_Ratefilter
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'consumer', 'label'=>Mage::helper('adminhtml')->__('consumer')),
            array('value'=>'best', 'label'=>Mage::helper('adminhtml')->__('best')),
            /*array('value'=>'min_rate', 'label'=>Mage::helper('adminhtml')->__('min_rate')),
            array('value'=>'fastest', 'label'=>Mage::helper('adminhtml')->__('fastest')),
            array('value'=>'asc', 'label'=>Mage::helper('adminhtml')->__('asc')),
            array('value'=>'desc', 'label'=>Mage::helper('adminhtml')->__('desc')),*/
        );
    }
}
