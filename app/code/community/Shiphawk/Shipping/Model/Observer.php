<?php
class Shiphawk_Shipping_Model_Observer extends Mage_Core_Model_Abstract
{
    protected function _setAttributeRequired($attributeCode, $is_active) {
        $attributeModel = Mage::getModel('eav/entity_attribute')->loadByCode( 'catalog_product', $attributeCode);
        $attributeModel->setIsRequired($is_active);
        $attributeModel->save();
    }

    public function salesOrderPlaceAfter($observer) {
        $event = $observer->getEvent();
        $order = $event->getOrder();
        $orderId = $order->getId();

        /* For accessories */
        $accessories    = Mage::app()->getRequest()->getPost('accessories', array());
        $helper         = Mage::helper('shiphawk_shipping');

        $manual_shipping =  Mage::getStoreConfig('carriers/shiphawk_shipping/book_shipment');
        $shipping_code = $order->getShippingMethod();
        $shipping_description = $order->getShippingDescription();
        $check_shiphawk = Mage::helper('shiphawk_shipping')->isShipHawkShipping($shipping_code);
        if($check_shiphawk !== false) {
            /* For location type */
            $shLocationType = Mage::getSingleton('checkout/session')->getData('shiphawk_location_type_shipping');

            if (!empty($shLocationType)) $order->setShiphawkLocationType($shLocationType);

            /* set ShipHawk rate */
            $shiphawk_book_id = Mage::getSingleton('core/session')->getShiphawkBookId();

            $multi_zip_code = Mage::getSingleton('core/session')->getMultiZipCode();

            //shiphawk_shipping_amount
            if($multi_zip_code == false) {

                $shiphawk_book_id  = $helper->getShipHawkCode($shiphawk_book_id, $shipping_code);
                foreach ($shiphawk_book_id as $rate_id=>$method_data) {
                    $order->setShiphawkShippingAmount($method_data['price']);
                    $order->setShiphawkShippingPackageInfo($method_data['packing_info']);
                }

            }else{
                //if multi origin shipping
                $shiphawk_shipping_amount = Mage::getSingleton('core/session')->getSummPrice();
                $shiphawk_shipping_package_info = Mage::getSingleton('core/session')->getPackageInfo();
                $order->setShiphawkShippingAmount($shiphawk_shipping_amount);
                $order->setShiphawkShippingPackageInfo($shiphawk_shipping_package_info);
            }

            $order->setShiphawkBookId(serialize($shiphawk_book_id));

            if (!empty($accessories)) {
                /* For accessories */
                $accessoriesPrice   = 0;
                $accessoriesData    = array();
                foreach($accessories as $typeName => $type) {
                    foreach($type as $name => $values) {
                        foreach($values as $key => $value) {
                            $accessoriesData[$typeName][$key]['name'] = $name;
                            $accessoriesData[$typeName][$key]['value'] = (float)$value;

                            $accessoriesPrice += (float)$value;
                        }
                    }
                }

                $newAccessoriesPrice    = $order->getShippingAmount() + $accessoriesPrice;
                $newGtandTotal          = $order->getGrandTotal() + $accessoriesPrice;

                $order->setShiphawkShippingAccessories(json_encode($accessoriesData));
                $order->setShippingAmount($newAccessoriesPrice);
                $order->setBaseShippingAmount($newAccessoriesPrice);
                $order->setGrandTotal($newGtandTotal);
                $order->setBaseGrandTotal($newGtandTotal);
            }

            $order->save();
            if(!$manual_shipping) {
                if ($order->canShip()) {
                    $api = Mage::getModel('shiphawk_shipping/api');
                    $api->saveshipment($orderId);
                }
            }
        }

        Mage::getSingleton('core/session')->unsShiphawkBookId();
        Mage::getSingleton('core/session')->unsMultiZipCode();
        Mage::getSingleton('core/session')->unsSummPrice();
        Mage::getSingleton('core/session')->unsPackageInfo();
    }

    /**
     * For rewrite address collectTotals
     *
     * @param $observer
     *
     * @version 20150617
     */
    public function recalculationTotals($observer) {
        $event          = $observer->getEvent();
        $address        = $event->getQuoteAddress();

        $session        = Mage::getSingleton('checkout/session');
        $accessories    = $session->getData('shipment_accessories');
        $method         = $address->getShippingMethod();

        if (empty($accessories['accessories_price']) || !$method) {
            return;
        }

        $accessoriesPrice   = (float)$accessories['accessories_price'];
        $grandTotal         = (float)$accessories['grand_total'];
        $baseGrandTotal     = (float)$accessories['base_grand_total'];
        $shippingAmount     = (float)$accessories['shipping_amount'];
        $baseShippingAmount = (float)$accessories['base_shipping_amount'];

        $shippingAmount     = empty($shippingAmount) ? $address->getShippingAmount() : $shippingAmount;
        $baseShippingAmount = empty($baseShippingAmount) ? $address->getBaseShippingAmount() : $baseShippingAmount;

        $newShippingPrice       = $shippingAmount + $accessoriesPrice;
        $newShippingBasePrice   = $baseShippingAmount + $accessoriesPrice;

        $address->setShippingAmount($newShippingPrice);
        $address->setBaseShippingAmount($baseShippingAmount + $accessoriesPrice);
        $address->setGrandTotal($grandTotal + $newShippingPrice);
        $address->setBaseGrandTotal($baseGrandTotal + $newShippingBasePrice);
    }

    /**
     * For save accessories in checkout session
     *
     * @param $observer
     *
     * @version 20150617
     */
    public function setAccessories($observer) {
        $event              = $observer->getEvent();
        $accessories        = $event->getRequest()->getPost('accessories', array());
        $address            = $event->getQuote()->getShippingAddress();
        $grandTotal         = $address->getSubtotal();
        $baseGrandTotal     = $address->getBaseSubtotal();
        $shippingAmount     = $address->getShippingInclTax();
        $baseShippingAmount = $address->getBaseShippingInclTax();
        $session            = Mage::getSingleton('checkout/session');

        if (empty($accessories)) {
            $session->setData("shipment_accessories", array());
            return;
        }

        $accessoriesPrice   = 0;
        $accessoriesData    = array();
        foreach($accessories as $typeName => $type) {
            foreach($type as $name => $values) {
                foreach($values as $key => $value) {
                    $accessoriesData[$typeName][$key]['name'] = $name;
                    $accessoriesData[$typeName][$key]['value'] = (float)$value;

                    $accessoriesPrice += (float)$value;
                }
            }
        }

        $params['data']                 = $accessoriesData;
        $params['grand_total']          = $grandTotal;
        $params['base_grand_total']     = $baseGrandTotal;
        $params['accessories_price']    = $accessoriesPrice;
        $params['shipping_amount']      = $shippingAmount;
        $params['base_shipping_amount'] = $baseShippingAmount;

        $session->setData("shipment_accessories", $params);
    }

    /**
     * For save accessories in order
     *
     * @param $observer
     *
     * @version 20150618
     */
    public function saveAccessoriesInOrder($observer) {
        $event = $observer->getEvent();
        $order = $event->getOrder();

        $session        = Mage::getSingleton('checkout/session');
        $accessories    = $session->getData("shipment_accessories");

        if (empty($accessories['accessories_price'])) {
            return;
        }

        $order->setShiphawkShippingAccessories(json_encode($accessories['data']));
        $order->save();
    }

    /**
     * For rewrite shipping/method/form.phtml template
     *
     * @param $observer
     *
     * @version 20150622
     */
    public function changeSippingMethodTemplate($observer) {
        if ($observer->getBlock() instanceof Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form) {
            $observer->getBlock()->setTemplate('shiphawk/shipping/method/form.phtml')->renderView();
        }
    }

    /**
     * For override shipping cost by admin, when he create order
     *
     * @param $observer
     *
     * @version 20150626
     */
    public function overrideShippingCost($observer) {
        $event          = $observer->getEvent();
        $order          = $event->getOrder();
        $subTotal       = $order->getSubtotal();

        $overrideCost   = Mage::app()->getRequest()->getPost('sh_override_shipping_cost', 0);
        $overrideCost   = floatval($overrideCost);

        if (empty($overrideCost)) {
            return;
        }

        $grandTotal = $subTotal + $overrideCost;

        $order->setShippingAmount($overrideCost);
        $order->setBaseShippingAmount($overrideCost);
        $order->setGrandTotal($grandTotal);
        $order->setBaseGrandTotal($grandTotal);

        $order->save();
    }
}