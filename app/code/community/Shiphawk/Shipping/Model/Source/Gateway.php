<?php
class Shiphawk_Shipping_Model_Source_Gateway
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'https://shiphawk.com/api/v3/', 'label'=>Mage::helper('adminhtml')->__('Live')),
            array('value'=>'https://sandbox.shiphawk.com/api/v3/', 'label'=>Mage::helper('adminhtml')->__('Sandbox')),
        );
    }
}