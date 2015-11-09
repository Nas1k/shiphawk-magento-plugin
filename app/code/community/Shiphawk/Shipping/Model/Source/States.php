<?php
class Shiphawk_Shipping_Model_Source_States
{
    public function toOptionArray()
    {
        $regions = Mage::helper('shiphawk_shipping')->getRegions();

        return $regions;
    }
}