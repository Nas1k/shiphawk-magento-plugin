<?php
class Shiphawk_Shipping_Model_Resource_Origins_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('shiphawk_shipping/origins');
    }
}
