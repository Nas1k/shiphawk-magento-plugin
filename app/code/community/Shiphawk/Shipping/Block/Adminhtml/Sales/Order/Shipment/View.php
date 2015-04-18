<?php
class Shiphawk_Shipping_Block_Adminhtml_Sales_Order_Shipment_View extends Mage_Adminhtml_Block_Sales_Order_Shipment_View {
    public function  __construct() {

        parent::__construct();

      /*  if ($this->getShipment()->getId()) {
            $this->_addButton('subscribe', array(
                    'label'     => Mage::helper('sales')->__('Subscribe to Tracking Information'),
                    'class'     => 'save',
                    'onclick'   => 'setLocation(\''.$this->getShipTrackUrl($this->getShipment()->getId()).'\')'
                )
            );
        }*/
    }

    public function getShipTrackUrl($shipment_id) {
        return Mage::helper("adminhtml")->getUrl("adminshiphawk/adminhtml_shipment/subscribe", array('shipment_id' => $shipment_id));
    }
}