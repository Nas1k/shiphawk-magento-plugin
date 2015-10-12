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

            // set ShipHawk rate todo ship to multiply shiping address, only one shipping order save to session
            $shiphawk_book_id = Mage::getSingleton('core/session')->getShiphawkBookId();

            $multi_zip_code = Mage::getSingleton('core/session')->getMultiZipCode();

            // set ShipHawk rate filter
            $shiphawkRateFilter = Mage::getSingleton('core/session')->getShiphawkRateFilter();
            $order->setShiphawkRateFilter($shiphawkRateFilter);

            //shiphawk_shipping_amount
            if($multi_zip_code == false) {

                $shiphawk_book_id  = $helper->getShipHawkCode($shiphawk_book_id, $shipping_code);
                foreach ($shiphawk_book_id as $rate_id=>$method_data) {
                    //$order->setShiphawkShippingAmount($method_data['price']);
                    $shiphawk_shipping_amount = $method_data['price'];
                    $order->setShiphawkShippingPackageInfo($method_data['packing_info']);
                }

            }else{
                //if multi origin shipping
                $shiphawk_shipping_amount = Mage::getSingleton('core/session')->getSummPrice();
                $shiphawk_shipping_package_info = Mage::getSingleton('core/session')->getPackageInfo();
                //$order->setShiphawkShippingAmount($shiphawk_shipping_amount);
                $order->setShiphawkShippingPackageInfo($shiphawk_shipping_package_info);
            }

            $order->setShiphawkBookId(serialize($shiphawk_book_id));

            // it's for admin order
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

                $order->setShiphawkShippingAmount($shiphawk_shipping_amount + $accessoriesPrice);
            }else{

                // it is for frontend order - accessories saved in checkout_type_onepage_save_order event
                $accessoriesPriceData = json_decode($order->getData('shiphawk_shipping_accessories'));
                $accessoriesPrice = $helper->getAccessoriesPrice($accessoriesPriceData);

                $order->setShiphawkShippingAmount($shiphawk_shipping_amount + $accessoriesPrice);
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

        Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
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

        // we have no accessories on cart page
        $is_it_cart_page = Mage::helper('shiphawk_shipping')->checkIsItCartPage();

        if (empty($accessories['accessories_price']) || !$method || $is_it_cart_page) {
            return;
        }

        $totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();
        $discount = 0;
        if(isset($totals['discount'])&&$totals['discount']->getValue()) {
            $discount = round($totals['discount']->getValue(), 2); //Discount value if applied
        }

        $accessoriesPrice   = (float)$accessories['accessories_price'];
        $grandTotal         = (float)$accessories['grand_total'];
        $baseGrandTotal     = (float)$accessories['base_grand_total'];
        $shippingAmount     = (float)$accessories['shipping_amount'];
        $baseShippingAmount = (float)$accessories['base_shipping_amount'];

        //$shippingAmount     = empty($shippingAmount) ? $address->getShippingAmount() : $shippingAmount;
        $shippingAmount     = $address->getShippingAmount();
        //$baseShippingAmount = empty($baseShippingAmount) ? $address->getBaseShippingAmount() : $baseShippingAmount;
        $baseShippingAmount = $address->getBaseShippingAmount();

        $newShippingPrice       = $shippingAmount + $accessoriesPrice;
        $newShippingBasePrice   = $baseShippingAmount + $accessoriesPrice;

        $address->setShippingAmount($newShippingPrice);
        $address->setBaseShippingAmount($baseShippingAmount + $accessoriesPrice);
        $address->setGrandTotal($grandTotal + $newShippingPrice + ($discount));
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
        $session->setAccessoriesprice($accessoriesPrice);
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

        //clear session data
        $session->unsetData('shipment_accessories');

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

        if ((floatval($overrideCost) < 0)||($overrideCost === null)||( $overrideCost === "")) {
            return;
        }

        $overrideCost   = floatval($overrideCost);

        $grandTotal = $subTotal + $overrideCost;

        $order->setShippingAmount($overrideCost);
        $order->setBaseShippingAmount($overrideCost);
        $order->setGrandTotal($grandTotal);
        $order->setBaseGrandTotal($grandTotal);

        $order->save();
    }

    /**
     * @param $observer
     */
    public function  showShiphawkRateError($observer) {

        $err_text = Mage::getSingleton('core/session')->getShiphawkErrorRate();
        if($err_text) {
            Mage::getSingleton('core/session')->getMessages(true); // The true is for clearing them after loading them
            Mage::getSingleton('core/session')->addError($err_text);
        }

        Mage::getSingleton('core/session')->unsShiphawkErrorRate();

    }

    /**
     * Update accessories & shipping price in admin order view
     * @param $observer
     */
    public function  addAccessoriesToTotals($observer) {

        if(!Mage::helper('shiphawk_shipping')->checkIsAdmin()) {
            return;
        }

        $event          = $observer->getEvent();
        $address        = $event->getQuoteAddress();

        $accessories_price_admin = Mage::getSingleton('core/session')->getData('admin_accessories_price');

        $shiphawk_override_cost = Mage::getSingleton('core/session')->getData('shiphawk_override_cost');

        $shippingAmount     = $address->getShippingAmount();

        if(empty($shippingAmount)) {
            return;
        }

        $baseShippingAmount = $address->getBaseShippingAmount();

        $grandTotal         = $address->getSubtotal();
        $baseGrandTotal     = $address->getBaseSubtotal();

        $newShippingPrice       = $shippingAmount + $accessories_price_admin;
        $newShippingBasePrice   = $baseShippingAmount + $accessories_price_admin;

        $totals = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getTotals();
        $discount = 0;
        if(isset($totals['discount'])&&$totals['discount']->getValue()) {
            $discount = round($totals['discount']->getValue(), 2); //Discount value if applied
        }

        $address->setShippingAmount($newShippingPrice);
        $address->setBaseShippingAmount($baseShippingAmount + $accessories_price_admin);
        $address->setGrandTotal($grandTotal + $newShippingPrice + ($discount));
        $address->setBaseGrandTotal($baseGrandTotal + $newShippingBasePrice);

        Mage::getSingleton('core/session')->unsetData('admin_accessories_price');

        if ((floatval($shiphawk_override_cost) < 0)||($shiphawk_override_cost === null)||( $shiphawk_override_cost === "")) {
            return;
        }

        $overrideCost   = floatval($shiphawk_override_cost);

        $subTotal       = $address->getSubtotal();
        $grandTotal = $subTotal + $overrideCost;


        $address->setShippingAmount($overrideCost);
        $address->setBaseShippingAmount($overrideCost);
        $address->setGrandTotal($grandTotal);
        $address->setBaseGrandTotal($grandTotal);

        Mage::getSingleton('core/session')->unsetData('shiphawk_override_cost');

    }

}