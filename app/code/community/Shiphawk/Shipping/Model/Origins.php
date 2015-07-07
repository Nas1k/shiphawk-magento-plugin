<?php
class Shiphawk_Shipping_Model_Resource_Origins extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('shiphawk_shipping/origins', 'id');
    }
}
