<?php
class Shiphawk_Shipping_Model_Source_Carriertype
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'', 'label'=>Mage::helper('adminhtml')->__('All')),
            array('value'=>'ltl', 'label'=>Mage::helper('adminhtml')->__('ltl')),
            array('value'=>'blanket wrap', 'label'=>Mage::helper('adminhtml')->__('blanket wrap')),
            array('value'=>'small parcel', 'label'=>Mage::helper('adminhtml')->__('small parcel')),
            array('value'=>'vehicle', 'label'=>Mage::helper('adminhtml')->__('vehicle')),
            array('value'=>'intermodal', 'label'=>Mage::helper('adminhtml')->__('intermodal')),
        );
    }
}