<?php

class Shiphawk_Shipping_Model_Carrier
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
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

        $ship_responces = array();
        $toOrder= array();
        $api_error = false;

        $is_multi_carrier = false;

        Mage::getSingleton('core/session')->unsetData('multi_zip_code');

        if(count($grouped_items_by_zip) > 1)  {
            Mage::getSingleton('core/session')->setMultiZipCode(true);
        }

        if(count($grouped_items_by_carrier_type) > 1)  {
            $is_multi_carrier = true;
            Mage::getSingleton('core/session')->setMultiZipCode(true);
        }

        $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin);

        $custom_packing_price_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_custom_packing_price');

        $self_pack = $helper->getSelfPacked();

        $charge_customer_for_packing = Mage::getStoreConfig('carriers/shiphawk_shipping/charge_customer_for_packing');

        //default origin zip code from config settings
        $from_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        try {

            /* items has various carrier type */
            if($is_multi_carrier) {
                Mage::getSingleton('core/session')->setMultiZipCode(true);// because multi carrier type
                foreach($grouped_items_by_carrier_type as $carrier_type=>$items_) {

                    if($carrier_type) {
                        $carrier_type = explode(',', $carrier_type);
                    }else{
                        $carrier_type = '';
                    }

                    $grouped_items_by_origin = $this->getGroupedItemsByZip($items_);

                    foreach($grouped_items_by_origin as $origin_id=>$items__) {

                        if ($origin_id != 'origin_per_product') { // product has origin id or primary origin

                            if($origin_id) {
                                $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                                $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                            }

                            $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items__, $rate_filter);

                            if(empty($checkattributes)) {

                                $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items__);

                                foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {
                                    // get zipcode and location type from first item in grouped by origin (zipcode) products
                                    $from_zip = $items__[0]['zip'];
                                    $location_type = $items__[0]['location_type'];
                                    // 1. multi carrier, multi origin, not origin per product
                                    $responseObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                    $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                    $custom_products_packing_price = 0;
                                    if($custom_packing_price_setting) {
                                        $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                    }

                                    $ship_responces[] = $responseObject;

                                    if(is_object($responseObject)||(empty($responseObject))) {
                                        $api_error = true;
                                        $shiphawk_error = (string) $responseObject->error;
                                        $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                        $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                    }else{
                                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                        $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                        $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                        foreach ($responseObject as $response) {
                                            $service_price = $helper->getShipHawkPrice($response, $self_pack, $charge_customer_for_packing);
                                            //$rate_price_for_group = $helper->getSummaryPrice($response, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                            if(empty($flat_markup_discount)&&(empty($percentage_markup_discount))) {
                                                $rate_price_for_group = $helper->getDiscountShippingPrice($service_price);
                                            }else{
                                                $rate_price_for_group = $helper->getProductDiscountMarkupPrice($service_price, $percentage_markup_discount, $flat_markup_discount);
                                            }

                                            $service_name  = $response->shipping->service;
                                            $toOrder[$response->id]['product_ids'] = $this->getProductIds($discount_items);
                                            $toOrder[$response->id]['price'] = $service_price;
                                            $toOrder[$response->id]['name'] = $service_name;
                                            $toOrder[$response->id]['items'] = $discount_items;
                                            $toOrder[$response->id]['from_zip'] = $from_zip;
                                            $toOrder[$response->id]['to_zip'] = $to_zip;
                                            $toOrder[$response->id]['carrier'] = $this->getCarrierName($response);
                                            $toOrder[$response->id]['packing_info'] = $this->getPackeges($response);
                                            $toOrder[$response->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$response->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$response->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                            $toOrder[$response->id]['self_pack'] = $self_pack;
                                            $toOrder[$response->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                            $toOrder[$response->id]['rate_price_for_group'] = round($rate_price_for_group,2);
                                        }
                                    }
                                }
                            }else{
                                $api_error = true;
                                foreach($checkattributes as $rate_error) {
                                    $helper->shlog('ShipHawk error: '.$rate_error);
                                    $helper->sendErrorMessageToShipHawk($rate_error);
                                }
                            }
                        }else{ // product items has all required shipping origin fields

                            $grouped_items_per_product_by_zip = $this->getGroupedItemsByZipPerProduct($items__);

                            foreach ($grouped_items_per_product_by_zip as $from_zip=>$items_per_product) {

                                $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                                if(empty($checkattributes)) {
                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $items_[0]['zip'];
                                    $location_type = $items_[0]['location_type'];

                                    $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_per_product);

                                    foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                        if($carrier_type) {
                                            $carrier_type = explode(',', $carrier_type);
                                        }else{
                                            $carrier_type = '';
                                        }

                                        // 2. multi carrier, multi origin, origin per product
                                        $responseObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                        $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                        $custom_products_packing_price = 0;
                                        if($custom_packing_price_setting) {
                                            $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                        }

                                        $ship_responces[] = $responseObject;

                                        if(is_object($responseObject)||(empty($responseObject))) {
                                            $api_error = true;
                                            $shiphawk_error = (string) $responseObject->error;
                                            $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                            $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                        }else{
                                            //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                            $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                            $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                            foreach ($responseObject as $response) {
                                                $service_price = $helper->getShipHawkPrice($response, $self_pack, $charge_customer_for_packing);
                                                //$rate_price_for_group = $helper->getSummaryPrice($response, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                                if(empty($flat_markup_discount)&&(empty($percentage_markup_discount))) {
                                                    $rate_price_for_group = $helper->getDiscountShippingPrice($service_price);
                                                }else{
                                                    $rate_price_for_group = $helper->getProductDiscountMarkupPrice($service_price, $percentage_markup_discount, $flat_markup_discount);
                                                }

                                                $service_name  = $response->shipping->service;
                                                $toOrder[$response->id]['product_ids'] = $this->getProductIds($discount_items);
                                                $toOrder[$response->id]['price'] = $service_price;
                                                $toOrder[$response->id]['name'] = $service_name;
                                                $toOrder[$response->id]['items'] = $discount_items;
                                                $toOrder[$response->id]['from_zip'] = $from_zip;
                                                $toOrder[$response->id]['to_zip'] = $to_zip;
                                                $toOrder[$response->id]['carrier'] = $this->getCarrierName($response);
                                                $toOrder[$response->id]['packing_info'] = $this->getPackeges($response);
                                                $toOrder[$response->id]['carrier_type'] = $carrier_type;
                                                $toOrder[$response->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                                $toOrder[$response->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                                $toOrder[$response->id]['self_pack'] = $self_pack;
                                                $toOrder[$response->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                                $toOrder[$response->id]['rate_price_for_group'] = round($rate_price_for_group,2);
                                            }
                                        }
                                    }
                                }else{
                                    $api_error = true;
                                    foreach($checkattributes as $rate_error) {
                                        $helper->shlog('ShipHawk error: '.$rate_error);
                                        $helper->sendErrorMessageToShipHawk($rate_error);
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
                    if($items_[0]['shiphawk_carrier_type']) {
                        $carrier_type = (explode(',', $items_[0]['shiphawk_carrier_type'])) ? (explode(',', $items_[0]['shiphawk_carrier_type'])) : Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');
                    }else{
                        $carrier_type = '';
                    }

                    if ($origin_id != 'origin_per_product') {

                        if($origin_id) {
                            $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                            $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                        }

                        $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_, $rate_filter);

                        if(empty($checkattributes)) {

                            $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_);

                            if(count($grouped_items_by_discount_or_markup)>1) {
                                Mage::getSingleton('core/session')->setMultiZipCode(true);
                            }

                            foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                $from_zip = $discount_items[0]['zip'];
                                $location_type = $discount_items[0]['location_type'];

                                // 3. one carrier, multi origin, not origin per product
                                $responseObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                $custom_products_packing_price = 0;
                                if($custom_packing_price_setting) {
                                    $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                }

                                $ship_responces[] = $responseObject;

                                if(is_object($responseObject)||(empty($responseObject))) {
                                    $api_error = true;
                                    $shiphawk_error = (string) $responseObject->error;
                                    $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                    $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                }else{
                                    //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                    $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                    $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                    foreach ($responseObject as $response) {
                                        $service_price = $helper->getShipHawkPrice($response, $self_pack, $charge_customer_for_packing);
                                        //$rate_price_for_group = $helper->getSummaryPrice($response, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                        if(empty($flat_markup_discount)&&(empty($percentage_markup_discount))) {
                                            $rate_price_for_group = $helper->getDiscountShippingPrice($service_price);
                                        }else{
                                            $rate_price_for_group = $helper->getProductDiscountMarkupPrice($service_price, $percentage_markup_discount, $flat_markup_discount);
                                        }

                                        $service_name  = $response->shipping->service;
                                        $toOrder[$response->id]['product_ids'] = $this->getProductIds($discount_items);
                                        $toOrder[$response->id]['price'] = $service_price;
                                        $toOrder[$response->id]['name'] = $service_name;
                                        $toOrder[$response->id]['items'] = $discount_items;
                                        $toOrder[$response->id]['from_zip'] = $from_zip;
                                        $toOrder[$response->id]['to_zip'] = $to_zip;
                                        $toOrder[$response->id]['carrier'] = $this->getCarrierName($response);
                                        $toOrder[$response->id]['packing_info'] = $this->getPackeges($response);
                                        $toOrder[$response->id]['carrier_type'] = $carrier_type;
                                        $toOrder[$response->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                        $toOrder[$response->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        $toOrder[$response->id]['self_pack'] = $self_pack;
                                        $toOrder[$response->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                        $toOrder[$response->id]['rate_price_for_group'] = round($rate_price_for_group,2);
                                    }
                                }
                            }
                        }else{
                            $api_error = true;
                            foreach($checkattributes as $rate_error) {
                                $helper->shlog('ShipHawk error: '.$rate_error);
                                $helper->sendErrorMessageToShipHawk($rate_error);
                            }
                        }
                    }else{
                        /* product items has per product origin, grouped by zip code */
                        $grouped_items_per_product_by_zip = $this->getGroupedItemsByZipPerProduct($items_);

                        if(count($grouped_items_per_product_by_zip) > 1 ) {
                            Mage::getSingleton('core/session')->setMultiZipCode(true);
                        }

                        foreach ($grouped_items_per_product_by_zip as $from_zip=>$items_per_product) {

                            $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                            if(empty($checkattributes)) {

                                $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_per_product);

                                if(count($grouped_items_by_discount_or_markup)>1) {
                                    Mage::getSingleton('core/session')->setMultiZipCode(true);
                                }

                                foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {
                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $discount_items[0]['zip'];
                                    $location_type = $discount_items[0]['location_type'];

                                    // 4. one carrier, multi origin, origin per product
                                    $responseObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                    $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                    $custom_products_packing_price = 0;

                                    if($custom_packing_price_setting) {
                                        $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                    }

                                    $ship_responces[] = $responseObject;

                                    if(is_object($responseObject)||(empty($responseObject))) {
                                        $api_error = true;
                                        $shiphawk_error = (string) $responseObject->error;
                                        $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                        $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                    }else{
                                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                        $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                        $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                        foreach ($responseObject as $response) {
                                            $service_price = $helper->getShipHawkPrice($response, $self_pack, $charge_customer_for_packing);
                                            //$rate_price_for_group = $helper->getSummaryPrice($response, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                            if(empty($flat_markup_discount)&&(empty($percentage_markup_discount))) {
                                                $rate_price_for_group = $helper->getDiscountShippingPrice($service_price);
                                            }else{
                                                $rate_price_for_group = $helper->getProductDiscountMarkupPrice($service_price, $percentage_markup_discount, $flat_markup_discount);
                                            }

                                            $service_name  = $response->shipping->service;
                                            $toOrder[$response->id]['product_ids'] = $this->getProductIds($discount_items);
                                            $toOrder[$response->id]['price'] = $service_price;
                                            $toOrder[$response->id]['name'] = $service_name;
                                            $toOrder[$response->id]['items'] = $discount_items;
                                            $toOrder[$response->id]['from_zip'] = $from_zip;
                                            $toOrder[$response->id]['to_zip'] = $to_zip;
                                            $toOrder[$response->id]['carrier'] = $this->getCarrierName($response);
                                            $toOrder[$response->id]['packing_info'] = $this->getPackeges($response);
                                            $toOrder[$response->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$response->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$response->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                            $toOrder[$response->id]['self_pack'] = $self_pack;
                                            $toOrder[$response->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                            $toOrder[$response->id]['rate_price_for_group'] = round($rate_price_for_group,2);
                                        }
                                    }
                                }
                            }else{
                                $api_error = true;
                                foreach($checkattributes as $rate_error) {
                                    $helper->shlog('ShipHawk error: '.$rate_error);
                                    $helper->sendErrorMessageToShipHawk($rate_error);
                                }
                            }
                        }
                    }
                }
            }

            if(!$api_error) {
                $services               = $this->getServices($ship_responces, $toOrder, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting);
                $package_info           = '';

                $free_shipping_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/free_method');

                $cheapest_rate_id = $this->_getCheapestRateId($services);

                foreach ($services as $id_service=>$service) {
                        //add ShipHawk shipping
                        //$shipping_price = $helper->getTotalDiscountShippingPrice($service['price'], $toOrder[$id_service]);
                        $shipping_price = $service['price'];
                        if((empty($service['shiphawk_discount_fixed']))&&(empty($service['shiphawk_discount_percentage']))) {
                            $shipping_price = $helper->getDiscountShippingPrice($service['price']);
                        }else{
                            $shipping_price = $helper->getProductDiscountMarkupPrice($service['price'], $service['shiphawk_discount_percentage'], $service['shiphawk_discount_fixed']);
                        }

                        if($free_shipping_setting != 'none') {
                            if ($request->getFreeShipping()) {

                                // get cheapest rate id
                                if($cheapest_rate_id == $id_service){
                                    $shipping_price = 0;
                                }

                                if ($free_shipping_setting == 'all') {
                                    $shipping_price = 0;
                                }
                            }
                        }

                        if($is_admin == false) {
                            $result->append($this->_getShiphawkRateObject($service['name'], $shipping_price, $service['shiphawk_price'], $service['accessorial']));
                        }else{
                            $result->append($this->_getShiphawkRateObject($service['name'] . ' - ' . $service['carrier'], $shipping_price, $service['shiphawk_price'], $service['accessorial']));
                        }
                }

                if(Mage::getSingleton('core/session')->getMultiZipCode()) {
                    $multiple_price = Mage::getSingleton('checkout/session')->getData('multiple_price_checkout');
                    if(is_null($multiple_price)){
                        $multiple_price = $this->getSumOfCheapestRates($toOrder);
                    }

                    Mage::getSingleton('checkout/session')->unsetData('multiple_price_checkout');

                    $result->append($this->_getShiphawkMultipleShippingRateObject($multiple_price));
                }

                //save rate_id info for Book
                Mage::getSingleton('core/session')->setShiphawkBookId($toOrder);

                //$helper->shlog($toOrder, 'shiphawk-toOrder.log');

                //save rate filter to order
                Mage::getSingleton('core/session')->setShiphawkRateFilter($rate_filter);

            }else{
                $error_text_from_config = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_error_message');
                Mage::getSingleton('core/session')->setShiphawkErrorRate($error_text_from_config);
            }

        }catch (Mage_Core_Exception $e) {
            Mage::logException($e);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $result;
    }

    protected function _getFreeShippingRate()
    {
        $rate = Mage::getModel('shipping/rate_result_method');
        /* @var $rate Mage_Shipping_Model_Rate_Result_Method */
        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle($this->getConfigData('title'));
        $rate->setMethod('free_shipping');
        $rate->setMethodTitle('Free Shipping');
        $rate->setPrice(0);
        $rate->setCost(0);
        return $rate;
    }


    /**
     * Returns Allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            'ground'    =>  'Ground delivery'
        );
    }

    public function  getProductIds($_items) {
        $products_ids = array();
        foreach($_items as $_item) {
            $products_ids[] = $_item['product_id'];
        }
        return $products_ids;
    }

    public function getSumOfCheapestRates($toOrder) {
        $sum_price = 0;
        $tmp = array();
        foreach($toOrder as $rate_id=>$data) {
            $tmp[serialize($data['product_ids'])][] = $data['rate_price_for_group'];
        }

        foreach ($tmp as $pr_ids => $array_prices) {
            $sum_price += min($array_prices);
        }

        return $sum_price;
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
        $rate->setCarrierTitle('ShipHawk');
        $rate->setMethodTitle($method_title);
        $rate->setMethod($ship_rate_id);
        $rate->setPrice($price);
        $rate->setCost($price);
        if(!empty($accessorial)) {
           $rate->setMethodDescription(serialize($accessorial));
        }

        return $rate;
    }

    /**
     * Get Standard rate object
     * @param Price
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getShiphawkMultipleShippingRateObject($price) {
        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        $rate = Mage::getModel('shipping/rate_result_method');

        $ship_rate_id = 'Shipping_from_multiple_location';

        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle('ShipHawk');
        $rate->setMethodTitle('Shipping from multiple location');
        $rate->setMethod($ship_rate_id);
        $rate->setPrice($price);
        $rate->setCost($price);

        return $rate;
    }

    public function getShiphawkItems($request) {
        $items          = array();
        $productModel   = Mage::getModel('catalog/product');

        $custom_packing_price_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_custom_packing_price');

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
            $parent_item = $item->getParentItem();
            if(($type_id == 'simple')&&(empty($parent_item))) {
                $product_qty = (($product->getShiphawkQuantity() > 0)) ? $product->getShiphawkQuantity() : 1;

                /** @var $helper Shiphawk_Shipping_Helper_Data */
                $helper = Mage::helper('shiphawk_shipping');
                $carrier_type = $helper->getProductCarrierType($product);

                //hack for admin shipment in popup
                $qty_ordered = ($item->getQty() > 0 ) ? $item->getQty() : $item->getData('qty_ordered');

                $items[] = array(
                    'width' => $product->getShiphawkWidth(),
                    'length' => $product->getShiphawkLength(),
                    'height' => $product->getShiphawkHeight(),
                    'weight' => ($product->getWeight()) ? ($product->getWeight()) : 0,
                    'value' => $this->getShipHawkItemValue($product),
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
                    'shiphawk_custom_packing_price'=> (($this->getIsPacked($product) == 'true')&&($custom_packing_price_setting)) ? null  : $product->getShiphawkCustomPackingPrice(),
                );
            }

            // single product in Bundle Product
            if(($type_id == 'simple')&&(is_object($parent_item))) {
                $product_qty = (($product->getShiphawkQuantity() > 0)) ? $product->getShiphawkQuantity() : 1;

                $qty_bundle_product = $parent_item->getQty();

                if($qty_bundle_product > 0) {
                    $product_qty = $qty_bundle_product * $product_qty;
                }

                /** @var $helper Shiphawk_Shipping_Helper_Data */
                $helper = Mage::helper('shiphawk_shipping');
                $carrier_type = $helper->getProductCarrierType($product);

                //hack for admin shipment in popup
                $qty_ordered = ($item->getQty() > 0 ) ? $item->getQty() : $item->getData('qty_ordered');

                $items[] = array(
                    'width' => $product->getShiphawkWidth(),
                    'length' => $product->getShiphawkLength(),
                    'height' => $product->getShiphawkHeight(),
                    'weight' => ($product->getWeight()) ? ($product->getWeight()) : 0,
                    'value' => $this->getShipHawkItemValue($product),
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
                    'shiphawk_custom_packing_price'=> (($this->getIsPacked($product) == 'true')&&($custom_packing_price_setting)) ? null  : $product->getShiphawkCustomPackingPrice(),
                );
            }
        }

        return $items;
    }

    public function getShiphawkShippingOrigin($product) {

        /** @var $helper Shiphawk_Shipping_Helper_Data */
        $helper = Mage::helper('shiphawk_shipping');

        if($helper->checkShipHawkOriginAttributes($product)) {
            return 'origin_per_product';
        }

        $product_origin_id = $product->getShiphawkShippingOrigins();

        if ($product_origin_id) {
            return $product_origin_id;
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

    /* sort items by shiphawk_custom_packing_price */
    public function getGroupedItemsByCustomPackingPrice($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['shiphawk_custom_packing_price']][] = $item;
        }
        return $tmp;
    }

    /* Group ShipHawk rates by product items */
    public function groupRatesByItems($toOrder, $_rates = null) {
        $tmp = array();
        foreach($toOrder as $rate_id=>$data) {
            $tmp[serialize($data['product_ids'])][] = $data['rate_price_for_group'];
        }

        $tt = array();
        foreach($_rates as $_rate){
            foreach ($tmp as $pr_ids=>$prices) {
                if(in_array(round($_rate->getPrice(),2),$prices)){
                    $tt[$pr_ids][] = $_rate;
                }
            }
        }

        return $tt;
    }

    public function sortMultipleShiping($toOrder) {
        $tmp = array();
        foreach($toOrder as $rate_id=>$data) {
            $tmp[serialize($data['product_ids'])][] = $data;
        }
        return $tmp;
    }

    protected function _getCheapestRateId($services) {
        $t = array();
        foreach ($services as $id_service=>$service) {
                $t[$id_service] = $service['price'];
        }
        asort($t);
        $rate_id = key($t);

        return $rate_id;
    }

    public function getServices($ship_responces, $toOrder, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting) {

        $services = array();
        $helper = Mage::helper('shiphawk_shipping');
        foreach($ship_responces as $ship_responce) {
            if(is_array($ship_responce)) {
                $custom_products_packing_price = 0;
                foreach($ship_responce as $object) {
                    $services[$object->id]['name'] = $this->_getServiceName($object);

                    foreach($toOrder as $rate_id=>$rate_data) {
                        if($rate_id == $object->id){
                            $services[$object->id]['custom_products_packing_price'] = $rate_data['custom_products_packing_price'];
                            $custom_products_packing_price = $rate_data['custom_products_packing_price'];
                            break;
                        }
                    }

                    $services[$object->id]['price'] = $helper->getSummaryPrice($object, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                    $services[$object->id]['carrier'] = $this->getCarrierName($object);
                    $services[$object->id]['shiphawk_price'] = $helper->getShipHawkPrice($object, $self_pack, $charge_customer_for_packing);

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
                            break;
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
            //$result[$i]['dimensions']['density'] = $package_object->dimensions->density;

            $package_info .= $package_object->dimensions->length .
                'x' . $package_object->dimensions->width .
                'x' . $package_object->dimensions->height .
                ', ' . $package_object->dimensions->weight . ' lbs. ';

            $i++;
        }

        return $package_info;
    }

    public function _getServiceName($object) {

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