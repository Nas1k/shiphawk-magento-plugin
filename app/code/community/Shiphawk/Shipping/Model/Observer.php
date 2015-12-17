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
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

        $check_shiphawk = Mage::helper('shiphawk_shipping')->isShipHawkShipping($shipping_code);
        if($check_shiphawk !== false) {

            /* For location type */
            $shLocationType = Mage::getSingleton('checkout/session')->getData('shiphawk_location_type_shipping');

            if (!empty($shLocationType)) $order->setShiphawkLocationType($shLocationType);

            // set ShipHawk Rates data
            $shiphawk_book_id = Mage::getSingleton('core/session')->getShiphawkBookId();

            if ($quote->getIsMultiShipping()) {
                $shiphawk_multi_shipping_book_id = unserialize($quote->getShiphawkBookId());
                $customer_shipping_address_id = $order->getShippingAddress()->getData('customer_address_id');
                $shiphawk_book_id = $shiphawk_multi_shipping_book_id[$customer_shipping_address_id];
            }

            $shiphawk_multi_shipping = Mage::getModel('shiphawk_shipping/carrier')->sortMultipleShiping($shiphawk_book_id);
            $order->setShiphawkMultiShipping(serialize($shiphawk_multi_shipping));

            $chosen_shipping_methods = Mage::getSingleton('checkout/session')->getData('chosen_multi_shipping_methods');


            $multi_zip_code = Mage::getSingleton('core/session')->getMultiZipCode();

            // set ShipHawk rate filter
            $shiphawkRateFilter = Mage::getSingleton('core/session')->getShiphawkRateFilter();
            $order->setShiphawkRateFilter($shiphawkRateFilter);

            //shiphawk_shipping_amount
            if($multi_zip_code == false) {//if single parcel shipping

                $shiphawk_book_id  = $helper->getShipHawkCode($shiphawk_book_id, $shipping_code);
                foreach ($shiphawk_book_id as $rate_id=>$method_data) {
                    $shiphawk_shipping_amount = $method_data['price'];
                    $order->setShiphawkShippingPackageInfo($method_data['packing_info']);
                }

            }else{
                //if multi origin shipping
                $shiphawk_shipping_package_info = _('See Package Info in Shipments');
                $shiphawk_shipping_amount = Mage::getSingleton('checkout/session')->getData('multiple_price');
                $order->setShiphawkShippingPackageInfo($shiphawk_shipping_package_info);
            }

            $order->setShiphawkBookId(serialize($shiphawk_book_id));

            // it's for admin order
            if (!empty($accessories)) {
                /* For accessories */
                $accessoriesPrice   = 0;
                $accessoriesData    = array();
                //$chosen_shipping_methods - get shipping method from post
                $order_data = Mage::app()->getRequest()->getPost('order', array());
                $chosen_shipping_methods[] = "'" . $order_data['shipping_method'] . "'";

                foreach($accessories as $typeName => $type) { //$typeName - destination, origin
                    foreach($type as $name => $values) { // name - shipping rate code

                            foreach($values as $key => $value) {
                                foreach($value as $id=>$price) {
                                    $accessoriesData[$name][$typeName][$key]['name'] = $key;
                                    $accessoriesData[$name][$typeName][$key]['value'] = (float)$price;
                                    $accessoriesData[$name][$typeName][$key]['id'] = $id;

                                    $accessoriesPrice += (float)$price;
                                }
                            }
                    }
                }

                $order->setShiphawkShippingAccessories(json_encode($accessoriesData));
                $order->setShiphawkShippingAmount($shiphawk_shipping_amount); //todo

            }else{

                $order->setShiphawkShippingAmount($shiphawk_shipping_amount); //todo
            }

            // save pre *destination* accessorials for future re rate in booking
            $accessories_for_rates = $helper->getPreAccessoriesInSession();
            $order->setShiphawkCustomerAccessorials(serialize($accessories_for_rates));

            $order->setChosenMultiShippingMethods(serialize($chosen_shipping_methods));

            $helper->clearCustomAccessoriesInSession();

            $order->save();
            if(!$manual_shipping) {
                if ($order->canShip()) {
                    $api = Mage::getModel('shiphawk_shipping/api');
                    $api->saveshipment($order);
                }
            }
        }

        if (!$quote->getIsMultiShipping()) {
            Mage::getSingleton('core/session')->unsShiphawkBookId();
            Mage::getSingleton('core/session')->unsMultiZipCode();
            Mage::getSingleton('core/session')->unsSummPrice();
            Mage::getSingleton('core/session')->unsPackageInfo();

            Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
            Mage::getSingleton('checkout/session')->unsetData('multiple_price');
            Mage::getSingleton('checkout/session')->unsetData('shiphawk_multi_shipping');
            Mage::getSingleton('checkout/session')->unsetData('chosen_multi_shipping_methods');
            Mage::getSingleton('checkout/session')->unsetData('chosen_accessories_per_carrier');
            Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
            Mage::getSingleton('checkout/session')->unsetData('accessoriesprice');
        }

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
        $tax = 0;
        if(isset($totals['tax']) && $totals['tax']->getValue()) {
            $tax = round($totals['tax']->getValue(), 2); //Discount value if applied
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

        //$newShippingPrice       = $shippingAmount + $accessoriesPrice;
        $newShippingPrice       = $shippingAmount;
        //$newShippingBasePrice   = $baseShippingAmount + $accessoriesPrice;
        $newShippingBasePrice   = $baseShippingAmount;

        $address->setShippingAmount($newShippingPrice);
        //$address->setBaseShippingAmount($baseShippingAmount + $accessoriesPrice);
        $address->setBaseShippingAmount($baseShippingAmount);
        $address->setGrandTotal($grandTotal + $newShippingPrice + ($discount) + ($tax));
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
        $quote              = $event->getQuote();
        $address            = $quote->getShippingAddress();
        $grandTotal         = $address->getSubtotal();
        $baseGrandTotal     = $address->getBaseSubtotal();
        $shippingAmount     = $address->getShippingInclTax();
        $baseShippingAmount = $address->getBaseShippingInclTax();
        $session            = Mage::getSingleton('checkout/session');
        $params = $event->getRequest()->getPost();

        $chosen_shipping_methods = array();

        Mage::getSingleton('checkout/session')->unsetData('chosen_accessories_per_carrier');
        if (!empty($params['hdaccess'])) {
            // chosen accessories per carrier
            Mage::getSingleton('checkout/session')->setData('chosen_accessories_per_carrier', $params['hdaccess']);
        }

        if($event->getRequest()->getPost('shipping_method') == 'shiphawk_shipping_Shipping_from_multiple_location') {
            $shippingAmount = $event->getRequest()->getPost('shiphawk_shipping_multi_parcel_price');

            $rates = $address->collectShippingRates()->getGroupedAllShippingRates();

            foreach ($rates as $carrier) {
                foreach ($carrier as $rate) {
                    if($rate->getCode() == 'shiphawk_shipping_Shipping_from_multiple_location'){
                        // multi price for checkout
                        Mage::getSingleton('checkout/session')->setData('multiple_price_checkout', $shippingAmount);
                        // multi price for order
                        Mage::getSingleton('checkout/session')->setData('multiple_price', $shippingAmount);
                        $rate->setPrice((float) $shippingAmount);
                        $rate->save();
                        $address->collectTotals();
                        break;
                    }
                }
            }

            // chosen multi shipping methods
            foreach ($params as $key => $value) {
                if(($key != 'shiphawk_shipping_multi_parcel_price') && ($key != 'shipping_method') && ($key != 'accessories')&& ($key != 'hdaccess') ) {
                    $chosen_shipping_methods[] = "'".$value."'";
                }
            }
        }else{
            // chosen one parcel method
            $chosen_shipping_methods[] = "'".$event->getRequest()->getPost('shipping_method')."'";
        }

        Mage::getSingleton('checkout/session')->setData('chosen_multi_shipping_methods', $chosen_shipping_methods);

        if (empty($accessories)) {
            $session->setData("shipment_accessories", array());
            return;
        }

        $accessoriesPrice   = 0;
        $accessoriesData    = array();

        foreach($accessories as $typeName => $type) { //$typeName - destination, origin
            foreach($type as $name => $values) { // name - shipping rate code
                if(in_array($name, $chosen_shipping_methods)){
                    foreach($values as $key => $value) {
                        foreach($value as $id=>$price) {
                            $accessoriesData[$name][$typeName][$key]['name'] = $key;
                            $accessoriesData[$name][$typeName][$key]['value'] = (float)$price;
                            $accessoriesData[$name][$typeName][$key]['id'] = $id;

                            $accessoriesPrice += (float)$price;
                        }
                    }
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
        $session->unsetData('accessoriesprice');
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



        /*if ((floatval($overrideCost) < 0)||($overrideCost === null)||( $overrideCost === "")) {
            return;
        }
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        $totals = $quote->getTotals();
        $discount = 0;
        if(isset($totals['discount']) && $totals['discount']->getValue()) {
            $discount = round($totals['discount']->getValue(), 2); //Discount value if applied
        }
        $tax = 0;
        if(isset($totals['tax']) && $totals['tax']->getValue()) {
            $tax = round($totals['tax']->getValue(), 2); //Tax value if applied
        }

        $overrideCost   = floatval($overrideCost);

        $grandTotal = $subTotal + $overrideCost + $discount + $tax;

        $order->setShippingAmount($overrideCost);
        $order->setBaseShippingAmount($overrideCost);
        $order->setGrandTotal($grandTotal);
        $order->setBaseGrandTotal($grandTotal);
        $order->save();*/
    }

    /**
     * @param $observer
     */
    /*public function  showShiphawkRateError($observer) {

        $err_text = Mage::getSingleton('core/session')->getShiphawkErrorRate();
        if($err_text) {
            Mage::getSingleton('core/session')->getMessages(true); // The true is for clearing them after loading them
            Mage::getSingleton('core/session')->addError($err_text);
        }

        Mage::getSingleton('core/session')->unsShiphawkErrorRate();

    }*/

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

        //Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
        //Mage::getSingleton('core/session')->unsetData('shiphawk_override_cost');

        /*$shippingAmount     = $address->getShippingAmount();

        if(empty($shippingAmount)) {
            return;
        }

        $baseShippingAmount = $address->getBaseShippingAmount();

        $grandTotal         = $address->getSubtotal();
        $baseGrandTotal     = $address->getBaseSubtotal();

        $newShippingPrice       = $shippingAmount + $accessories_price_admin;
        $newShippingBasePrice   = $baseShippingAmount + $accessories_price_admin;
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $totals = $quote->getTotals();
        $discount = 0;
        if(isset($totals['discount']) && $totals['discount']->getValue()) {
            $discount = round($totals['discount']->getValue(), 2); //Discount value if applied
        }
        $tax = 0;
        if(isset($totals['tax']) && $totals['tax']->getValue()) {
            $tax = round($totals['tax']->getValue(), 2); //Tax value if applied
        }

        $address->setShippingAmount($newShippingPrice);
        $address->setBaseShippingAmount($baseShippingAmount + $accessories_price_admin);
        $address->setGrandTotal($grandTotal + $newShippingPrice + ($discount) +  ($tax));
        $address->setBaseGrandTotal($baseGrandTotal + $newShippingBasePrice);

        Mage::getSingleton('core/session')->unsetData('admin_accessories_price');

        if ((floatval($shiphawk_override_cost) < 0)||($shiphawk_override_cost === null)||( $shiphawk_override_cost === "")) {
            return;
        }

        $overrideCost   = floatval($shiphawk_override_cost);

        $subTotal       = $address->getSubtotal();
        $grandTotal = $subTotal + $overrideCost + $discount + $tax;

        $address->setShippingAmount($overrideCost);
        $address->setBaseShippingAmount($overrideCost);
        $address->setGrandTotal($grandTotal);
        $address->setBaseGrandTotal($grandTotal);

        Mage::getSingleton('core/session')->unsetData('shiphawk_override_cost');*/

    }

}