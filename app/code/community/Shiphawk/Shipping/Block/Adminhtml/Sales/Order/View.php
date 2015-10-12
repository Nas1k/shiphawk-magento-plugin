<?php
class Shiphawk_Shipping_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View {
    public function  __construct() {

        parent::__construct();

        $order_id = $this->getOrderId();
        $order = $this->getOrder();

        $manual_shipping =  Mage::getStoreConfig('carriers/shiphawk_shipping/book_shipment');
        $shipping_code = $order->getShippingMethod();

        $confirm_messsage = $this->__('Are you sure to process?');
        $check_shiphawk = Mage::helper('shiphawk_shipping')->isShipHawkShipping($shipping_code);
        if($check_shiphawk !== false) {
            if ($order->canShip()) {
                if($manual_shipping) {
                    $this->_addButton('shiphawk_shipping', array(
                        'label'     => Mage::helper('shiphawk_shipping')->__('ShipHawk Shipment'),
                        //'onclick' => "confirmSetLocation('{$confirm_messsage}', '{$this->getShipHawkUrl($order_id)}')",
                        'onclick' => "reBook('{$this->getShipHawkUrl($order_id)}', '{$order_id}', '{$this->getNewShipHawkUrl($order_id)}')",
                        'class'     => 'go'
                    ), 0, 100, 'header', 'header');
                }
            }
        }else{
            if ($order->canShip()) {
            $this->_addButton('shiphawk_shipping_new', array(
                'label'     => Mage::helper('shiphawk_shipping')->__('New ShipHawk Shipment'),
                'class'     => 'newshipment',
                'onclick'      => "showPopup('{$this->getNewShipHawkUrl($order_id)}')"
            ), 0, 150, 'header', 'header');
            }
        }

    }

    public function getShipHawkUrl($order_id) {
        return Mage::helper("adminhtml")->getUrl("adminshiphawk/adminhtml_shipment/saveshipment", array('order_id' => $order_id));
    }

    public function getNewShipHawkUrl($order_id) {
        return Mage::helper("adminhtml")->getUrl("adminshiphawk/adminhtml_shipment/newshipment", array('order_id' => $order_id));
    }
}