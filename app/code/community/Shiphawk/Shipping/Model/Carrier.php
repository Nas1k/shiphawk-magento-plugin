<?php

class Shiphawk_Shipping_Model_Carrier
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    const DEFAULT_WEIGHT = "126.0000";// todo ?
    /**
     * Carrier's code, as defined in parent class
     *
     * @var string
     */
    protected $_code = 'shiphawk_shipping';

    /**
     * Returns available shipping rates for Shiphawk Shipping carrier
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        /** @var Mage_Shipping_Model_Rate_Result $result */
        $result = Mage::getModel('shipping/rate_result');

        $helper = Mage::helper('shiphawk_shipping');
        $default_origin_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        $hide_on_frontend = Mage::getStoreConfig('carriers/shiphawk_shipping/hide_on_frontend');
        $is_admin = $helper->checkIsAdmin();
        // hide ShipHawk method on frontend , allow only in admin area
        if (($hide_on_frontend == 1) && (!$is_admin)) {
            return $result;
        }

        /* For location type */
        $shLocationType     = 'residential';
        if ($is_admin) {
            // if is admin this means that we have already set the value in setlocationtypeAction
            $shLocationType = Mage::getSingleton('checkout/session')->getData('shiphawk_location_type_shipping');
        } else {
            $shippingRequest    = Mage::app()->getRequest()->getPost();

            if (!empty($shippingRequest['billing'])) {
                $shLocationType = $shippingRequest['billing']['shiphawk_location_type'];
                $shLocationType = $shLocationType != 'commercial' && $shLocationType != 'residential' ? 'residential' : $shLocationType;
                Mage::getSingleton('checkout/session')->setData('shiphawk_location_type_billing', $shLocationType);

                if ($shippingRequest['billing']['use_for_shipping']) {
                    Mage::getSingleton('checkout/session')->setData('shiphawk_location_type_shipping', $shLocationType);
                }
            } else if (!empty($shippingRequest['shipping'])) {
                $shLocationType = $shippingRequest['shipping']['shiphawk_location_type'];
                $shLocationType = $shLocationType != 'commercial' && $shLocationType != 'residential' ? 'residential' : $shLocationType;
                Mage::getSingleton('checkout/session')->setData('shiphawk_location_type_shipping', $shLocationType);
            } else {
                $shLocationType = Mage::getSingleton('checkout/session')->getData('shiphawk_location_type_shipping');
                $shLocationType = $shLocationType != 'commercial' && $shLocationType != 'residential' ? 'residential' : $shLocationType;
            }
        }

        $to_zip = $this->getShippingZip();
        $api = Mage::getModel('shiphawk_shipping/api');
        $items = $this->getShiphawkItems($request);

        $grouped_items_by_zip = $this->getGroupedItemsByZip($items);

        $grouped_items_by_carrier_type = $this->getGroupedItemsByCarrierType($items);

        $grouped_items_by_discount_or_markup2 = $this->getGroupedItemsByDiscountOrMarkup($items); //delete

        $ship_responces = array();
        $toOrder= array();
        $api_error = false;
        $is_multi_zip = false;
        $is_multi_carrier = false;

        if(count($grouped_items_by_zip) > 1)  {
            $is_multi_zip = true;
        }

        if(count($grouped_items_by_carrier_type) > 1)  {
            $is_multi_carrier = true;
            $is_multi_zip = true;
        }

        $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin);

        //default origin zip code from config settings
        $from_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        try {

            /* items has various carrier type */
            if($is_multi_carrier) {
                foreach($grouped_items_by_carrier_type as $carrier_type=>$items_) {

                    $grouped_items_by_origin = $this->getGroupedItemsByZip($items_);

                    foreach($grouped_items_by_origin as $origin_id=>$items__) {

                        if ($origin_id != 'origin_per_product') { // product has origin id or primary origin

                            $rate_filter = 'best'; // multi carrier

                            if($origin_id) {
                                $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                                $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                            }

                            $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items__, $rate_filter);

                            if(empty($checkattributes)) {

                                $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items__);

                                foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {
                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $items__[0]['zip'];
                                    $location_type = $items__[0]['location_type'];
                                    // 1. multi carrier, multi origin, not origin per product
                                    $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);

                                    $helper->shlog('from zip: ' . $from_zip, 'shiphawk-get-rate.log');
                                    $helper->shlog('to zip: ' . $to_zip, 'shiphawk-get-rate.log');
                                    $helper->shlog('carrier type: ' . $carrier_type, 'shiphawk-get-rate.log');


                                    $ship_responces[] = $responceObject;

                                    if(is_object($responceObject)) {
                                        $api_error = true;
                                        $shiphawk_error = (string) $responceObject->error;
                                        //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                        $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                    }else{
                                        // if $rate_filter = 'best' then it is only one rate
                                        if(($is_multi_zip)||($rate_filter == 'best')) {
                                            //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                            $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                            $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                            Mage::getSingleton('core/session')->setMultiZipCode(true);
                                            $toOrder[$responceObject[0]->id]['product_ids'] = $this->getProductIds($discount_items);
                                            $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                            $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                            $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                            $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                            $toOrder[$responceObject[0]->id]['carrier'] = $this->getCarrierName($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['packing_info'] = $this->getPackeges($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        }
                                    }
                                }


                            }else{
                                $api_error = true;
                                foreach($checkattributes as $rate_error) {
                                    //Mage::log('ShipHawk error: '.$rate_error, null, 'ShipHawk.log');
                                    $helper->shlog('ShipHawk error: '.$rate_error);
                                }
                            }
                        }else{ // product items has all required shipping origin fields

                            $grouped_items_per_product_by_zip = $this->getGroupedItemsByZipPerProduct($items__);

                            if(count($grouped_items_per_product_by_zip) > 1 ) {
                                $is_multi_zip = true;
                            }

                            if($is_multi_zip) {
                                $rate_filter = 'best';
                            }

                            foreach ($grouped_items_per_product_by_zip as $from_zip=>$items_per_product) {

                                $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                                if(empty($checkattributes)) {
                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $items_[0]['zip'];
                                    $location_type = $items_[0]['location_type'];

                                    $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_per_product);

                                    foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                        $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                        $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                        // 2. multi carrier, multi origin, origin per product
                                        $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                        $helper->shlog('from zip: ' . $from_zip, 'shiphawk-get-rate.log');
                                        $helper->shlog('to zip: ' . $to_zip, 'shiphawk-get-rate.log');
                                        $helper->shlog('carrier type: ' . $carrier_type, 'shiphawk-get-rate.log');

                                        $ship_responces[] = $responceObject;

                                        if(is_object($responceObject)) {
                                            $api_error = true;
                                            $shiphawk_error = (string) $responceObject->error;
                                            //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                            $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                        }else{
                                            // if $rate_filter = 'best' then it is only one rate
                                            if(($is_multi_zip)||($rate_filter == 'best')) {
                                                Mage::getSingleton('core/session')->setMultiZipCode(true);
                                                $toOrder[$responceObject[0]->id]['product_ids'] = $this->getProductIds($discount_items);
                                                $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                                $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                                $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                                $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                                $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                                $toOrder[$responceObject[0]->id]['carrier'] = $this->getCarrierName($responceObject[0]);
                                                $toOrder[$responceObject[0]->id]['packing_info'] = $this->getPackeges($responceObject[0]);
                                                $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                                $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                                $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;

                                            }
                                        }

                                    }


                                }else{
                                    $api_error = true;
                                    foreach($checkattributes as $rate_error) {
                                        //Mage::log('ShipHawk error: '.$rate_error, null, 'ShipHawk.log');
                                        $helper->shlog('ShipHawk error: '.$rate_error);
                                    }
                                }

                            }

                        }

                    }
                }
            }else{

                /* all product items has one carrier type or carrier type is null in all items */
                foreach($grouped_items_by_zip as $origin_id=>$items_) {

                    /* get carrier type from first item because items grouped by carrier type and not multi carrier */
                    /* if carrier type is null, get default carrier type from settings */
                    $carrier_type = ($items_[0]['shiphawk_carrier_type']) ? ($items_[0]['shiphawk_carrier_type']) : Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');

                    if ($origin_id != 'origin_per_product') {

                        if($is_multi_zip) {
                            $rate_filter = 'best';
                        }

                        if($origin_id) {
                            $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                            $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                        }

                        $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_, $rate_filter);

                        if(empty($checkattributes)) {

                            $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_);

                            foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                $from_zip = $discount_items[0]['zip'];
                                $location_type = $discount_items[0]['location_type'];

                                // 3. one carrier, multi origin, not origin per product
                                $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                $helper->shlog('from zip: ' . $from_zip, 'shiphawk-get-rate.log');
                                $helper->shlog('to zip: ' . $to_zip, 'shiphawk-get-rate.log');
                                $helper->shlog('carrier type: ' . $carrier_type, 'shiphawk-get-rate.log');

                                $ship_responces[] = $responceObject;

                                //todo null?
                                if(is_object($responceObject)) {
                                    $api_error = true;
                                    $shiphawk_error = (string) $responceObject->error;
                                    //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                    $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                }else{
                                    // if $rate_filter = 'best' then it is only one rate
                                    if(($is_multi_zip)||($rate_filter == 'best')) {
                                        Mage::getSingleton('core/session')->setMultiZipCode(true);
                                        $toOrder[$responceObject[0]->id]['product_ids'] = $this->getProductIds($discount_items);
                                        $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                        $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                        $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                        $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                        $toOrder[$responceObject[0]->id]['carrier'] = $this->getCarrierName($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['packing_info'] = $this->getPackeges($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                        $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                        $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                    }else{
                                        Mage::getSingleton('core/session')->setMultiZipCode(false);
                                        foreach ($responceObject as $responce) {
                                            $toOrder[$responce->id]['product_ids'] = $this->getProductIds($discount_items);
                                            $toOrder[$responce->id]['price'] = $helper->getSummaryPrice($responce);
                                            $toOrder[$responce->id]['name'] = $responce->shipping->service;//
                                            $toOrder[$responce->id]['items'] = $discount_items;
                                            $toOrder[$responce->id]['from_zip'] = $from_zip;
                                            $toOrder[$responce->id]['to_zip'] = $to_zip;
                                            $toOrder[$responce->id]['carrier'] = $this->getCarrierName($responce);
                                            $toOrder[$responce->id]['packing_info'] = $this->getPackeges($responce);
                                            $toOrder[$responce->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$responce->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$responce->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        }
                                    }
                                }
                            }
                        }else{
                            $api_error = true;
                            foreach($checkattributes as $rate_error) {
                                //Mage::log('ShipHawk error: '.$rate_error, null, 'ShipHawk.log');
                                $helper->shlog('ShipHawk error: '.$rate_error);
                            }
                        }
                    }else{

                        $grouped_items_per_product_by_zip = $this->getGroupedItemsByZipPerProduct($items_);

                        if(count($grouped_items_per_product_by_zip) > 1 ) {
                            $is_multi_zip = true;
                        }

                        if($is_multi_zip) {
                            $rate_filter = 'best';
                        }

                        foreach ($grouped_items_per_product_by_zip as $from_zip=>$items_per_product) {

                            $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                            if(empty($checkattributes)) {

                                $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_per_product);

                                foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                    //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                    $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                    $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $discount_items[0]['zip'];
                                    $location_type = $discount_items[0]['location_type'];

                                    // 4. one carrier, multi origin, origin per product
                                    $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                    $helper->shlog('from zip: ' . $from_zip, 'shiphawk-get-rate.log');
                                    $helper->shlog('to zip: ' . $to_zip, 'shiphawk-get-rate.log');
                                    $helper->shlog('carrier type: ' . $carrier_type, 'shiphawk-get-rate.log');

                                    $ship_responces[] = $responceObject;

                                    if(is_object($responceObject)) {
                                        $api_error = true;
                                        $shiphawk_error = (string) $responceObject->error;
                                        //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                        $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                    }else{
                                        // if $rate_filter = 'best' then it is only one rate
                                        if(($is_multi_zip)||($rate_filter == 'best')) {
                                            Mage::getSingleton('core/session')->setMultiZipCode(true);
                                            $toOrder[$responceObject[0]->id]['product_ids'] = $this->getProductIds($discount_items);
                                            $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                            $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                            $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                            $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                            $toOrder[$responceObject[0]->id]['carrier'] = $this->getCarrierName($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['packing_info'] = $this->getPackeges($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        }else{
                                            Mage::getSingleton('core/session')->setMultiZipCode(false);
                                            foreach ($responceObject as $responce) {
                                                $toOrder[$responce->id]['product_ids'] = $this->getProductIds($discount_items);
                                                $toOrder[$responce->id]['price'] = $helper->getSummaryPrice($responce);
                                                $toOrder[$responce->id]['name'] = $responce->shipping->service;//
                                                $toOrder[$responce->id]['items'] = $discount_items;
                                                $toOrder[$responce->id]['from_zip'] = $from_zip;
                                                $toOrder[$responce->id]['to_zip'] = $to_zip;
                                                $toOrder[$responce->id]['carrier'] = $this->getCarrierName($responce);
                                                $toOrder[$responce->id]['packing_info'] = $this->getPackeges($responce);
                                                $toOrder[$responce->id]['carrier_type'] = $carrier_type;
                                                $toOrder[$responce->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                                $toOrder[$responce->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                            }
                                        }
                                    }
                                }
                            }else{
                                $api_error = true;
                                foreach($checkattributes as $rate_error) {
                                    //Mage::log('ShipHawk error: '.$rate_error, null, 'ShipHawk.log');
                                    $helper->shlog('ShipHawk error: '.$rate_error);
                                }
                            }

                        }

                    }
                }
            }


            if(!$api_error) {
                $services               = $this->getServices($ship_responces, $toOrder);
                $name_service           = '';
                $summ_price             = 0;
                $package_info           = '';
                $multi_shipping_price   = 0;

                foreach ($services as $id_service=>$service) {
                    if (!$is_multi_zip) {
                        //add ShipHawk shipping
                        //$shipping_price = $helper->getTotalDiscountShippingPrice($service['price'], $toOrder[$id_service]);
                        $shipping_price = $service['price'];
                        if((empty($service['shiphawk_discount_fixed']))&&(empty($service['shiphawk_discount_percentage']))) {
                            $shipping_price = $helper->getDiscountShippingPrice($service['price']);
                        }else{
                            $shipping_price = $helper->getProductDiscountMarkupPrice($service['price'], $service['shiphawk_discount_percentage'], $service['shiphawk_discount_fixed']);
                        }

                        if($is_admin == false) {
                            $result->append($this->_getShiphawkRateObject($service['name'], $shipping_price, $service['price'], $service['accessorial']));
                        }else{
                            $result->append($this->_getShiphawkRateObject($service['name'] . ' - ' . $service['carrier'], $shipping_price, $service['price'], $service['accessorial']));
                        }

                    }else{
                        $name_service .= $service['name'] . ', ';
                        $summ_price += $service['price'];

                        $shipping_price = $service['price'];
                        if((empty($service['shiphawk_discount_fixed']))&&(empty($service['shiphawk_discount_percentage']))) {
                            $shipping_price = $helper->getDiscountShippingPrice($service['price']);
                        }else{
                            $shipping_price = $helper->getProductDiscountMarkupPrice($service['price'], $service['shiphawk_discount_percentage'], $service['shiphawk_discount_fixed']);
                        }

                        $multi_shipping_price += $shipping_price;
                        //$multi_shipping_price += $helper->getTotalDiscountShippingPrice($service['price'], $toOrder[$id_service]);
                    }
                }

                //save rate_id info for Book
                Mage::getSingleton('core/session')->setShiphawkBookId($toOrder);

                //save rate filter to order
                Mage::getSingleton('core/session')->setShiphawkRateFilter($rate_filter);

                if($is_multi_zip) {
                    //add ShipHawk shipping
                    $name_service   = 'Shipping from multiple locations';
                    $shipping_price = $multi_shipping_price;
                    Mage::getSingleton('core/session')->setSummPrice($summ_price);

                    foreach($toOrder  as $rateid=>$rate_data) {
                        $package_info .=  $rate_data['name']. ' - ' . $rate_data['carrier']  . ' - ' .$rate_data['packing_info'];
                    }

                    Mage::getSingleton('core/session')->setPackageInfo($package_info);
                    $result->append($this->_getShiphawkRateObject($name_service, $shipping_price, $summ_price, null));
                }

                //for save shiphawk amount in order with rate_filter = best
                /*if ($rate_filter == 'best') {
                    Mage::getSingleton('core/session')->setSummPrice($summ_price);
                    Mage::getSingleton('core/session')->setPackageInfo($this->getPackeges($responceObject[0]));
                }*/
            }

        }catch (Mage_Core_Exception $e) {
            Mage::logException($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e->getMessage());
        }
        return $result;
    }

    /**
     * Returns Allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            'ground'    =>  'Ground delivery',
        );
    }

    public function  getProductIds($_items) {
        $products_ids = array();
        foreach($_items as $_item) {
            $products_ids[] = $_item['product_id'];
        }
        return $products_ids;
    }

    /**
     * Get Standard rate object
     *
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getShiphawkRateObject($method_title, $price, $true_price, $accessorial)
    {
        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        $rate = Mage::getModel('shipping/rate_result_method');

        $ship_rate_id = str_replace('-', '_', str_replace(',', '', str_replace(' ', '_', $method_title.$true_price)));

        $rate->setCarrier($this->_code);
        //$rate->setCarrierTitle($this->getConfigData('title'));
        $rate->setCarrierTitle('ShipHawk');
        $rate->setMethodTitle($method_title);
        $rate->setMethod($ship_rate_id);
        $rate->setPrice($price);
        $rate->setCost($price);
        if(!empty($accessorial)) {
           $rate->setMethodDescription($accessorial);//maybe this fire error in system.log ERR (3): Recoverable Error: Object of class stdClass could not be converted to string
        }

        return $rate;
    }

    public function getShiphawkItems($request) {
        $items          = array();
        $productModel   = Mage::getModel('catalog/product');

        foreach ($request->getAllItems() as $item) {
            $product_id = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($product_id);

            $freightClass       = $product->getShiphawkFreightClass();
            $freightClassValue  = '';

            if (!empty($freightClass)) {
                $attr = $productModel->getResource()->getAttribute('shiphawk_freight_class');
                $freightClassValue = $attr->getSource()->getOptionText($freightClass);
            }

            $type_id = $product->getTypeId();
            if($type_id == 'simple') {
                $product_qty = (($product->getShiphawkQuantity() > 0)) ? $product->getShiphawkQuantity() : 1;

                /** @var $helper Shiphawk_Shipping_Helper_Data */
                $helper = Mage::helper('shiphawk_shipping');
                $carrier_type = $helper->getProductCarrierType($product);

                //hack for admin shipment in popup
                $qty_ordered = ($item->getQty() > 0 ) ? $item->getQty() : $item->getData('qty_ordered');

                if($product->getWeight() > 0) {
                    $items[] = array(
                        'width' => $product->getShiphawkWidth(),
                        'length' => $product->getShiphawkLength(),
                        'height' => $product->getShiphawkHeight(),
                        'weight' => $product->getWeight(),
                        'value' => $this->getShipHawkItemValue($product),
                        //'quantity' => $product_qty*$item->getQty(),
                        'quantity' => $product_qty*$qty_ordered,
                        'packed' => $this->getIsPacked($product),
                        'id' => $product->getShiphawkTypeOfProductValue(),
                        'zip'=> $this->getOriginZip($product),
                        'product_id'=> $product_id,
                        'xid'=> $product_id,
                        'origin'=> $this->getShiphawkShippingOrigin($product),
                        'location_type'=> $this->getOriginLocation($product),
                        'require_crating'=> false,
                        'nmfc'=> '',
                        'freight_class'=> $freightClassValue,
                        'shiphawk_carrier_type'=> $carrier_type,
                        'shiphawk_discount_fixed'=> $product->getShiphawkDiscountFixed(),
                        'shiphawk_discount_percentage'=> $product->getShiphawkDiscountPercentage(),
                    );
                }else{
                    $items[] = array(
                        'width' => $product->getShiphawkWidth(),
                        'length' => $product->getShiphawkLength(),
                        'height' => $product->getShiphawkHeight(),
                        'weight' => (string) self::DEFAULT_WEIGHT, //todo move to config?
                        'value' => $this->getShipHawkItemValue($product),
                        //'quantity' => $product_qty*$item->getQty(),
                        'quantity' => $product_qty*$qty_ordered,
                        'packed' => $this->getIsPacked($product),
                        'id' => $product->getShiphawkTypeOfProductValue(),
                        'zip'=> $this->getOriginZip($product),
                        'product_id'=> $product_id,
                        'xid'=> $product_id,
                        'origin'=> $this->getShiphawkShippingOrigin($product),
                        'location_type'=> $this->getOriginLocation($product),
                        'require_crating'=> false,
                        'nmfc'=> '',
                        'freight_class'=> $freightClassValue,
                        'shiphawk_carrier_type'=> $carrier_type,
                        'shiphawk_discount_fixed'=> $product->getShiphawkDiscountFixed(),
                        'shiphawk_discount_percentage'=> $product->getShiphawkDiscountPercentage(),
                    );
                }
            }
        }

        return $items;
    }

    public function getShiphawkShippingOrigin($product) {
        $product_origin_id = $product->getShiphawkShippingOrigins();
        /** @var $helper Shiphawk_Shipping_Helper_Data */
        $helper = Mage::helper('shiphawk_shipping');
        if ($product_origin_id) {
            return $product_origin_id;
        }

        if ((empty($product_origin_id)) && ($helper->checkShipHawkOriginAttributes($product))) {
            return 'origin_per_product';
        }

        return null;

    }

    public function getShippingZip() {
        if (Mage::app()->getStore()->isAdmin()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }else{
            /** @var $cart Mage_Checkout_Model_Cart */
            $cart = Mage::getSingleton('checkout/cart');
            $quote = $cart->getQuote();
        }
        $shippingAddress = $quote->getShippingAddress();
        $zip_code = $shippingAddress->getPostcode();
        return $zip_code;
    }

    public function getShipHawkItemValue($product) {
        if($product->getShiphawkQuantity() > 0) {
            $product_price = $product->getPrice()/$product->getShiphawkQuantity();
        }else{
            $product_price = $product->getPrice();
        }
        $item_value = ($product->getShiphawkItemValue() > 0) ? $product->getShiphawkItemValue() : $product_price;
        return $item_value;
    }

    public function getOriginZip($product) {
        $default_origin_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        $shipping_origin_id = $product->getData('shiphawk_shipping_origins');

        $helper = Mage::helper('shiphawk_shipping');
        /* check if all origin attributes are set */
        $per_product = $helper->checkShipHawkOriginAttributes($product);

        if($per_product == true) {
            return $product->getData('shiphawk_origin_zipcode');
        }

        if($shipping_origin_id) {
            // get zip code from Shiping Origin
            $shipping_origin = Mage::getModel('shiphawk_shipping/origins')->load($shipping_origin_id);
            $product_origin_zip_code = $shipping_origin->getData('shiphawk_origin_zipcode');
            return $product_origin_zip_code;
        }

        return $default_origin_zip;
    }

    public function getOriginLocation($product) {
        $default_origin_location = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_location_type');

        $shipping_origin_id = $product->getData('shiphawk_shipping_origins');

        $helper = Mage::helper('shiphawk_shipping');
        /* check if all origin attributes are set */
        $per_product = $helper->checkShipHawkOriginAttributes($product);

        if($per_product == true) {
            //$product->getAttributeText('brand');
            return $product->getAttributeText('shiphawk_origin_location');
        }

        if($shipping_origin_id) {
            // get zip code from Shiping Origin
            $shipping_origin = Mage::getModel('shiphawk_shipping/origins')->load($shipping_origin_id);
            $product_origin_zip_code = $shipping_origin->getData('shiphawk_origin_location');
            return $product_origin_zip_code;
        }

        return $default_origin_location;
    }

    public function getIsPacked($product) {
        $default_is_packed = Mage::getStoreConfig('carriers/shiphawk_shipping/item_is_packed');
        $product_is_packed = $product->getShiphawkItemIsPacked();
        $product_is_packed = ($product_is_packed == 2) ? $default_is_packed : $product_is_packed;

        return ($product_is_packed ? 'true' : 'false');
    }

    /* sort items by origin id */
    public function getGroupedItemsByZip($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['origin']][] = $item;
        }
        return $tmp;
    }

    /* sort items by origin zip code */
    public function getGroupedItemsByZipPerProduct($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['zip']][] = $item;
        }
        return $tmp;
    }

    /* sort items by carrier type */
    public function getGroupedItemsByCarrierType($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['shiphawk_carrier_type']][] = $item;
        }
        return $tmp;
    }

    /* sort items by discount or markup */
    public function getGroupedItemsByDiscountOrMarkup($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['shiphawk_discount_percentage']. '-' .$item['shiphawk_discount_fixed']][] = $item;
        }
        return $tmp;
    }

    public function getServices($ship_responces, $toOrder) {

        $services = array();
        $helper = Mage::helper('shiphawk_shipping');
        foreach($ship_responces as $ship_responce) {
            if(is_array($ship_responce)) {
                foreach($ship_responce as $object) {
                    $services[$object->id]['name'] = $this->_getServiceName($object);

                    $services[$object->id]['price'] = $helper->getSummaryPrice($object);
                    $services[$object->id]['carrier'] = $this->getCarrierName($object);

                    /* packing info */
                    $services[$object->id]['packing']['price'] = $object->packing->price;
                    $services[$object->id]['packing']['info'] = $this->getPackeges($object);
                    $services[$object->id]['delivery'] = ''; //todo delivery
                    $services[$object->id]['carrier_type'] = ''; //todo carrier type

                    /* accesorial info */
                    $services[$object->id]['accessorial'] = $object->shipping->carrier_accessorial;

                    /* discount-markup by product or by sys. conf. */

                    foreach($toOrder as $rate_id=>$rate_data) {
                        if($rate_id == $object->id){
                            $services[$object->id]['shiphawk_discount_fixed'] = $rate_data['shiphawk_discount_fixed'];
                            $services[$object->id]['shiphawk_discount_percentage'] = $rate_data['shiphawk_discount_percentage'];
                        }
                    }
                }
            }
        }

        return $services;
    }

    public function  getPackeges($object) {
        $result = array();
        $i=0;
        $package_info = '';
        foreach ($object->packing->packages as $package_object) {
            $result[$i]['tracking_number'] = $package_object->tracking_number;
            $result[$i]['tracking_url'] = $package_object->tracking_url;
            $result[$i]['packing_type'] = $package_object->packing_type;
            $result[$i]['dimensions']['length'] = $package_object->dimensions->length;
            $result[$i]['dimensions']['width'] = $package_object->dimensions->width;
            $result[$i]['dimensions']['height'] = $package_object->dimensions->height;
            $result[$i]['dimensions']['weight'] = $package_object->dimensions->weight;
            $result[$i]['dimensions']['volume'] = $package_object->dimensions->volume;
            $result[$i]['dimensions']['density'] = $package_object->dimensions->density;

//            $package_info .= 'package #' . $i . ' ( '. $package_object->packing_type .' ): ' . 'length: ' .  $package_object->dimensions->length . ', ' .
//                'width: ' .  $package_object->dimensions->width . ', ' .
//                'height: ' .  $package_object->dimensions->height . ', ' .
//                'weight: ' .  $package_object->dimensions->weight . ', ' .
//                'volume: ' .  $package_object->dimensions->volume . ', ' .
//                'density: ' .  $package_object->dimensions->density . ' ';

            $package_info .= $package_object->dimensions->length .
                'x' . $package_object->dimensions->width .
                'x' . $package_object->dimensions->height .
                ', ' . $package_object->dimensions->weight . ' lbs. ';

            $i++;
        }

        return $package_info;
    }

    protected function _getServiceName($object) {

        if ( $object->shipping->carrier_type == "Small Parcel" ) {
            return $object->shipping->service;
        }

        if ( $object->shipping->carrier_type == "Blanket Wrap" ) {
            return "Standard White Glove Delivery (3-6 weeks)";
        }

        if ( ( ( $object->shipping->carrier_type == "LTL" ) || ( $object->shipping->carrier_type == "3PL" ) || ( $object->shipping->carrier_type == "Intermodal" ) ) && ($object->delivery->price == 0) ) {
            return "Curbside Delivery (1-2 weeks)";
        }

        if ( ( ( $object->shipping->carrier_type == "LTL" ) || ( $object->shipping->carrier_type == "3PL" ) || ( $object->shipping->carrier_type == "Intermodal" ) ) && ($object->delivery->price > 0) ) {
            return "Expedited White Glove Delivery (2-3 weeks)";
        }

        if ( $object->shipping->carrier_type == "Home Delivery" ) {
            return "Home Delivery - " . $object->shipping->service . " (1-2 weeks)";
        }

        return $object->shipping->service;

    }

    public function getCarrierName($object) {
        return $object->shipping->carrier_friendly_name;
    }

    /*
    1. If carrier_type = "Small Parcel" display name should be what's included in field [Service] (example: Ground)

    2. If carrier_type = "Blanket Wrap" display name should be:
    "Standard White Glove Delivery (3-6 weeks)"

    3. If carrier_type = "LTL","3PL","Intermodal" AND delivery field inside [details][price]=$0.00 display name should be:
    "Curbside delivery (1-2 weeks)"

    4. If carrier_type = "LTL","3PL" "Intermodal" AND delivery field inside [details][price] > $0.00 display name should be:
    "Expedited White Glove Delivery (2-3 weeks)"

    Additional rule for naming (both frontend and backend):

    If carrier_type = "Home Delivery" display name should be:
    "Home Delivery - {{
    {Service name from received rate}
    }} (1-2 weeks)"
    ==> example: Home Delivery - One Man (1-2 weeks)

    */

    public function isTrackingAvailable()
    {
        return true;
    }


}