<?php
class Shiphawk_Shipping_Model_Session extends Mage_Core_Model_Session_Abstract {
     public function __construct()
{
    $namespace = 'shiphawk_shipping';

    $this->init($namespace);
    Mage::dispatchEvent('shiphawk_shipping_session_init', array('shiphawk_shipping_session'=>$this));
}
   public function renewSession()
{
    parent::renewSession();
    Mage::getSingleton('core/session')->unsSessionHosts();

    return $this;
}
}