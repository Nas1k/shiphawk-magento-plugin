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
        $api = Mage::getModel('shiphawk_shipping/api');
        $hide_on_frontend = Mage::getStoreConfig('carriers/shiphawk_shipping/hide_on_frontend');

        $is_admin = $helper->checkIsAdmin();
        // hide ShipHawk method on frontend , allow only in admin area
        if (($hide_on_frontend == 1) && (!$is_admin)) {
            return $result;
        }

        if ($helper->checkIsAdmin()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }else{
            /** @var $cart Mage_Checkout_Model_Cart */
            $cart = Mage::getSingleton('checkout/cart');
            $quote = $cart->getQuote();
        }

        /* Parameters */
        if (!$quote->getIsMultiShipping()) {
            $to_zip = $this->getShippingZip();
            $to_country_code = $this->getToCountryCode();
        }else{
            $to_zip = $request->getDestPostcode();
            $to_country_code = $request->getDestCountryId();
        }

        $items = $this->getShiphawkItems($request);

        $toOrder= array();
        $api_error = false;
        $is_multi_zip = false;
        $is_multi_carrier = false;
        $api_calls_params = array();
        $rate_filter =  $helper->getRateFilter($is_admin);
        $self_pack = $helper->getSelfPacked();
        $custom_packing_price_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_custom_packing_price');
        $charge_customer_for_packing = Mage::getStoreConfig('carriers/shiphawk_shipping/charge_customer_for_packing');
        $error_text_from_config = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_error_message');
        $fixed_rate_applicable = Mage::getStoreConfig('carriers/shiphawk_shipping/applicable');
        $method_title = Mage::getStoreConfig('carriers/shiphawk_shipping/rate_title');
        $free_shipping_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/free_method');

        //default origin zip code from config settings
        $from_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        $grouped_items_by_disabled_shipping = $this->getGroupedItemsByDisabledShipping($items);
        /*END*/

        /* For location type */
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
        $shipping_address_id = $this->checkShippingAddress($request, $quote);
        if ($quote->getIsMultiShipping()) {

            if ($shipping_address_id) {
                $address = Mage::getModel('customer/address')->load($shipping_address_id);
                $shLocationType = $address->getShiphawkLocationType();
                // destination address type should be residential or commercial, default value - residential
                $shLocationType = $shLocationType != 'commercial' && $shLocationType != 'residential' ? 'residential' : $shLocationType;
            }
        }

        Mage::getSingleton('core/session')->unsetData('multi_zip_code');

        /* accessories choose in cart */
        $pre_accessories = null;
        $accessories_for_rates = $helper->getPreAccessoriesInSession();
        foreach ($accessories_for_rates as $access_rate) {
            $pre_accessories[$access_rate] = 'true';
        }

        //building api calls
        try {
            if(count($grouped_items_by_disabled_shipping) > 1)  {
                //Mage::getSingleton('core/session')->setMultiZipCode(true);
                $is_multi_zip = true;
            }

            foreach ($grouped_items_by_disabled_shipping as $disable=>$product_items) {
                if($disable == 1) {
                    //disable the ShipHawk shipping method at the product level
                    $api_error = true;
                    if (($fixed_rate_applicable == 0)||(($fixed_rate_applicable == 1)&&($is_multi_zip == false))) {
                        $error = Mage::getModel('shipping/rate_result_error');
                        $error->setCarrier('shiphawk_shipping');
                        $error->setCarrierTitle('ShipHawk');
                        $error->setErrorMessage($error_text_from_config);
                        $result->append($error);

                    } else {
                        $fixed_price = $this->_getFixedPrice($product_items);
                        // if shiphawk is disabled, from zip by default
                        $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;
                        if ($free_shipping_setting != 'none') {
                            if ($request->getFreeShipping()) {
                                $fixed_price = 0;
                            }
                        }
                        $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                        $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($product_items);
                        $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                        $toOrder[$fixed_rate_id]['name'] = $method_title;
                        $toOrder[$fixed_rate_id]['items'] = $product_items;
                        $toOrder[$fixed_rate_id]['from_zip'] = $from_zip;
                        $toOrder[$fixed_rate_id]['to_zip'] = $to_zip;
                        $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                        $toOrder[$fixed_rate_id]['packing_info'] = '';
                        $toOrder[$fixed_rate_id]['carrier_type'] = null;
                        $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                        $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                        $toOrder[$fixed_rate_id]['self_pack'] = $self_pack;
                        $toOrder[$fixed_rate_id]['custom_products_packing_price'] = 0;
                        $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                        $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;
                    }
                }else{
                    $grouped_items_by_zip = $this->getGroupedItemsByZip($product_items);
                    $grouped_items_by_carrier_type = $this->getGroupedItemsByCarrierType($product_items);

                    if(count($grouped_items_by_zip) > 1)  {
                        //Mage::getSingleton('core/session')->setMultiZipCode(true);
                        $is_multi_zip = true;
                    }

                    if(count($grouped_items_by_carrier_type) > 1)  {
                        $is_multi_carrier = true;
                        $is_multi_zip = true;
                        //Mage::getSingleton('core/session')->setMultiZipCode(true);
                    }

                    /* items has various carrier type */
                    if ($is_multi_carrier) {
                        foreach ($grouped_items_by_carrier_type as $carrier_type => $items_) {

                            if ($carrier_type) {
                                $carrier_type = explode(',', $carrier_type);
                            } else {
                                $carrier_type = '';
                            }

                            $grouped_items_by_origin = $this->getGroupedItemsByZip($items_);

                            foreach ($grouped_items_by_origin as $origin_id => $items__) {

                                if ($origin_id != 'origin_per_product') { // product has origin id or primary origin

                                    if ($origin_id) {
                                        $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                                        $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                                    }

                                    $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items__, $rate_filter);

                                    $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items__);

                                    foreach ($grouped_items_by_discount_or_markup as $mark_up_discount => $discount_items) {

                                        $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                        /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                        $from_zip = $items__[0]['zip'];
                                        $location_type = $items__[0]['location_type'];
                                        $custom_products_packing_price = 0;
                                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                        $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                        $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                        if ($custom_packing_price_setting) {
                                            $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                        }

                                        // 1. multi carrier, multi origin, not origin per product

                                        if (empty($checkattributes)) {

                                            $tempArray = array(
                                                'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories, $to_country_code),
                                                'discount_items' => $discount_items,
                                                'self_pack' => $self_pack,
                                                'charge_customer_for_packing' => $charge_customer_for_packing,
                                                'from_zip' => $from_zip,
                                                'to_zip' => $to_zip,
                                                'carrier_type' => $carrier_type,
                                                'custom_products_packing_price' => $custom_products_packing_price,
                                                'flat_markup_discount' => $flat_markup_discount,
                                                'percentage_markup_discount' => $percentage_markup_discount,
                                            );
                                            $api_calls_params[] = $tempArray;
                                        } else {
                                            $api_error = true;
                                            foreach ($checkattributes as $rate_errors) {
                                                if(is_array($rate_errors)){
                                                    foreach ($rate_errors as $rate_error) {
                                                        $helper->shlog('ShipHawk error: ' . $rate_error);
                                                    }
                                                }else{
                                                    $helper->shlog('ShipHawk error: ' . $rate_errors);
                                                    $helper->sendErrorMessageToShipHawk($rate_errors);
                                                }
                                            }

                                            if ($fixed_rate_applicable == 0) {
                                                $error = Mage::getModel('shipping/rate_result_error');
                                                $error->setCarrier('shiphawk_shipping');
                                                $error->setCarrierTitle('ShipHawk');
                                                $error->setErrorMessage($error_text_from_config);
                                                $result->append($error);
                                                break 3;
                                            } else {
                                                $fixed_price = $this->_getFixedPrice($discount_items);
                                                $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;
                                                if ($free_shipping_setting != 'none') {
                                                    if ($request->getFreeShipping()) {
                                                        $fixed_price = 0;
                                                    }
                                                }
                                                $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                                                $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($discount_items);
                                                $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                                                $toOrder[$fixed_rate_id]['name'] = $method_title;
                                                $toOrder[$fixed_rate_id]['items'] = $discount_items;
                                                $toOrder[$fixed_rate_id]['from_zip'] = $from_zip;
                                                $toOrder[$fixed_rate_id]['to_zip'] = $to_zip;
                                                $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                                                $toOrder[$fixed_rate_id]['packing_info'] = '';
                                                $toOrder[$fixed_rate_id]['carrier_type'] = $carrier_type;
                                                $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                                                $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                                                $toOrder[$fixed_rate_id]['self_pack'] = $self_pack;
                                                $toOrder[$fixed_rate_id]['custom_products_packing_price'] = $custom_products_packing_price;
                                                $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                                                $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;
                                            }
                                        }
                                    }

                                } else { // product items has all required shipping origin fields

                                    $grouped_items_per_product_by_zip = $this->getGroupedItemsByZipPerProduct($items__);
                                    foreach ($grouped_items_per_product_by_zip as $from_zip => $items_per_product) {

                                        $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);


                                        /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                        $from_zip = $items_[0]['zip'];
                                        $location_type = $items_[0]['location_type'];

                                        $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_per_product);
                                        foreach ($grouped_items_by_discount_or_markup as $mark_up_discount => $discount_items) {
                                            $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                            //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                            $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                            $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                            $custom_products_packing_price = 0;

                                            if ($custom_packing_price_setting) {
                                                $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                            }

                                            // 2. multi carrier, multi origin, origin per product

                                            if (empty($checkattributes)) {

                                                $tempArray = array(
                                                    'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories, $to_country_code),
                                                    'discount_items' => $discount_items,
                                                    'self_pack' => $self_pack,
                                                    'charge_customer_for_packing' => $charge_customer_for_packing,
                                                    'from_zip' => $from_zip,
                                                    'to_zip' => $to_zip,
                                                    'carrier_type' => $carrier_type,
                                                    'custom_products_packing_price' => $custom_products_packing_price,
                                                    'flat_markup_discount' => $flat_markup_discount,
                                                    'percentage_markup_discount' => $percentage_markup_discount,
                                                );
                                                $api_calls_params[] = $tempArray;
                                            } else {
                                                $api_error = true;
                                                foreach ($checkattributes as $rate_errors) {
                                                    if(is_array($rate_errors)){
                                                        foreach ($rate_errors as $rate_error) {
                                                            $helper->shlog('ShipHawk error: ' . $rate_error);
                                                        }
                                                    }else{
                                                        $helper->shlog('ShipHawk error: ' . $rate_errors);
                                                        $helper->sendErrorMessageToShipHawk($rate_errors);
                                                    }
                                                }

                                                if ($fixed_rate_applicable == 0) {
                                                    $error = Mage::getModel('shipping/rate_result_error');
                                                    $error->setCarrier('shiphawk_shipping');
                                                    $error->setCarrierTitle('ShipHawk');
                                                    $error->setErrorMessage($error_text_from_config);
                                                    $result->append($error);
                                                    break 3;
                                                } else {
                                                    $fixed_price = $this->_getFixedPrice($discount_items);
                                                    $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;
                                                    if ($free_shipping_setting != 'none') {
                                                        if ($request->getFreeShipping()) {
                                                            $fixed_price = 0;
                                                        }
                                                    }
                                                    $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                                                    $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($discount_items);
                                                    $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                                                    $toOrder[$fixed_rate_id]['name'] = $method_title;
                                                    $toOrder[$fixed_rate_id]['items'] = $discount_items;
                                                    $toOrder[$fixed_rate_id]['from_zip'] = $from_zip;
                                                    $toOrder[$fixed_rate_id]['to_zip'] = $to_zip;
                                                    $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                                                    $toOrder[$fixed_rate_id]['packing_info'] = '';
                                                    $toOrder[$fixed_rate_id]['carrier_type'] = $carrier_type;
                                                    $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                                                    $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                                                    $toOrder[$fixed_rate_id]['self_pack'] = $self_pack;
                                                    $toOrder[$fixed_rate_id]['custom_products_packing_price'] = $custom_products_packing_price;
                                                    $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                                                    $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;
                                                }
                                            }

                                        }

                                    }
                                }
                            }
                        }
                    } else {

                        /* all product items has one carrier type or carrier type is null in all items */
                        foreach ($grouped_items_by_zip as $origin_id => $items_) {

                            /* get carrier type from first item because items grouped by carrier type and not multi carrier */
                            /* if carrier type is null, get default carrier type from settings */
                            if ($items_[0]['shiphawk_carrier_type']) {
                                $carrier_type = (explode(',', $items_[0]['shiphawk_carrier_type'])) ? (explode(',', $items_[0]['shiphawk_carrier_type'])) : Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');
                            } else {
                                $carrier_type = '';
                            }

                            if ($origin_id != 'origin_per_product') {

                                if ($origin_id) {
                                    $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                                    $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                                }

                                $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_, $rate_filter);

                                $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_);
                                foreach ($grouped_items_by_discount_or_markup as $mark_up_discount => $discount_items) {

                                    //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                    $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                    $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $discount_items[0]['zip'];
                                    $location_type = $discount_items[0]['location_type'];
                                    $custom_products_packing_price = 0;

                                    if ($custom_packing_price_setting) {
                                        $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                    }

                                    // 3. one carrier, multi origin, not origin per product

                                    if (empty($checkattributes)) {

                                        $tempArray = array(
                                            'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories, $to_country_code),
                                            'discount_items' => $discount_items,
                                            'self_pack' => $self_pack,
                                            'charge_customer_for_packing' => $charge_customer_for_packing,
                                            'from_zip' => $from_zip,
                                            'to_zip' => $to_zip,
                                            'carrier_type' => $carrier_type,
                                            'custom_products_packing_price' => $custom_products_packing_price,
                                            'flat_markup_discount' => $flat_markup_discount,
                                            'percentage_markup_discount' => $percentage_markup_discount,
                                        );
                                        $api_calls_params[] = $tempArray;
                                    } else {
                                        $api_error = true;
                                        foreach ($checkattributes as $rate_errors) {
                                            if(is_array($rate_errors)){
                                                foreach ($rate_errors as $rate_error) {
                                                    $helper->shlog('ShipHawk error: ' . $rate_error); // too many emails, maybe ?
                                                }
                                            }else{
                                                $helper->shlog('ShipHawk error: ' . $rate_errors);
                                                $helper->sendErrorMessageToShipHawk($rate_errors);
                                            }
                                        }

                                        if ($fixed_rate_applicable == 0) {
                                            $error = Mage::getModel('shipping/rate_result_error');
                                            $error->setCarrier('shiphawk_shipping');
                                            $error->setCarrierTitle('ShipHawk');
                                            $error->setErrorMessage($error_text_from_config);
                                            $result->append($error);
                                            break 2;
                                        } else {
                                            $fixed_price = $this->_getFixedPrice($discount_items);
                                            $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;
                                            if ($free_shipping_setting != 'none') {
                                                if ($request->getFreeShipping()) {
                                                    $fixed_price = 0;
                                                }
                                            }
                                            $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                                            $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($discount_items);
                                            $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                                            $toOrder[$fixed_rate_id]['name'] = $method_title;
                                            $toOrder[$fixed_rate_id]['items'] = $discount_items;
                                            $toOrder[$fixed_rate_id]['from_zip'] = $from_zip;
                                            $toOrder[$fixed_rate_id]['to_zip'] = $to_zip;
                                            $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                                            $toOrder[$fixed_rate_id]['packing_info'] = '';
                                            $toOrder[$fixed_rate_id]['carrier_type'] = $carrier_type;
                                            $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                                            $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                                            $toOrder[$fixed_rate_id]['self_pack'] = $self_pack;
                                            $toOrder[$fixed_rate_id]['custom_products_packing_price'] = $custom_products_packing_price;
                                            $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                                            $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;
                                        }
                                    }

                                }

                            } else {
                                /* product items has per product origin, grouped by zip code */
                                $grouped_items_per_product_by_zip = $this->getGroupedItemsByZipPerProduct($items_);

                                if (count($grouped_items_per_product_by_zip) > 1) {
                                    $is_multi_zip = true;
                                }

                                foreach ($grouped_items_per_product_by_zip as $from_zip => $items_per_product) {

                                    $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                                    $grouped_items_by_discount_or_markup = $this->getGroupedItemsByDiscountOrMarkup($items_per_product);

                                    foreach ($grouped_items_by_discount_or_markup as $mark_up_discount => $discount_items) {
                                        $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                        $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                        $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                        /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                        $from_zip = $discount_items[0]['zip'];
                                        $location_type = $discount_items[0]['location_type'];
                                        $custom_products_packing_price = 0;

                                        if ($custom_packing_price_setting) {
                                            $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                        }

                                        // 4. one carrier, origin per product

                                        if (empty($checkattributes)) {

                                            $tempArray = array(
                                                'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories, $to_country_code),
                                                'discount_items' => $discount_items,
                                                'self_pack' => $self_pack,
                                                'charge_customer_for_packing' => $charge_customer_for_packing,
                                                'from_zip' => $from_zip,
                                                'to_zip' => $to_zip,
                                                'carrier_type' => $carrier_type,
                                                'custom_products_packing_price' => $custom_products_packing_price,
                                                'flat_markup_discount' => $flat_markup_discount,
                                                'percentage_markup_discount' => $percentage_markup_discount,
                                            );
                                            $api_calls_params[] = $tempArray;
                                        } else {
                                            $api_error = true;
                                            foreach ($checkattributes as $rate_errors) {
                                                if(is_array($rate_errors)){
                                                    foreach ($rate_errors as $rate_error) {
                                                        $helper->shlog('ShipHawk error: ' . $rate_error);
                                                    }
                                                }else{
                                                    $helper->shlog('ShipHawk error: ' . $rate_errors);
                                                    $helper->sendErrorMessageToShipHawk($rate_errors);
                                                }
                                            }

                                            if ($fixed_rate_applicable == 0) {
                                                $error = Mage::getModel('shipping/rate_result_error');
                                                $error->setCarrier('shiphawk_shipping');
                                                $error->setCarrierTitle('ShipHawk');
                                                $error->setErrorMessage($error_text_from_config);
                                                $result->append($error);
                                                break 2;
                                            } else {
                                                $fixed_price = $this->_getFixedPrice($discount_items);
                                                $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;
                                                if ($free_shipping_setting != 'none') {
                                                    if ($request->getFreeShipping()) {
                                                        $fixed_price = 0;
                                                    }
                                                }
                                                $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                                                $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($discount_items);
                                                $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                                                $toOrder[$fixed_rate_id]['name'] = $method_title;
                                                $toOrder[$fixed_rate_id]['items'] = $discount_items;
                                                $toOrder[$fixed_rate_id]['from_zip'] = $from_zip;
                                                $toOrder[$fixed_rate_id]['to_zip'] = $to_zip;
                                                $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                                                $toOrder[$fixed_rate_id]['packing_info'] = '';
                                                $toOrder[$fixed_rate_id]['carrier_type'] = $carrier_type;
                                                $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                                                $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                                                $toOrder[$fixed_rate_id]['self_pack'] = $self_pack;
                                                $toOrder[$fixed_rate_id]['custom_products_packing_price'] = $custom_products_packing_price;
                                                $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                                                $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;
                                            }
                                        }
                                    }

                                }
                            }
                        }
                    }
                }
            }

            //execute all API calls - multi curl
            $mh = curl_multi_init();
            foreach ($api_calls_params as $api_data) {
                curl_multi_add_handle($mh, $api_data['api_call']);
            }

            $active = null;
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($active && $mrc == CURLM_OK){
                if (curl_multi_select($mh) == -1){
                    continue;
                }
                do{
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }

            foreach ($api_calls_params as $api_data) {
                curl_multi_remove_handle($mh, $api_data['api_call']);
            }

            curl_multi_close($mh);
            $api_responses = array();
            foreach ($api_calls_params as $api_data) {
                $api_responses[] = json_decode(curl_multi_getcontent($api_data['api_call']));
            }
            $helper->shlog($api_responses, 'Response.log');
            //end multi curl

            // process responses into old data objects
            for ($i = 0; $i < count($api_responses); $i++) {

                // empty response from ShipHawk
                if (empty($api_responses[$i])) {
                    $api_error = true;
                    $shiphawk_error = 'Empty ShipHawk response';
                    $helper->shlog('ShipHawk response: ' . $shiphawk_error);
                    $helper->sendErrorMessageToShipHawk($shiphawk_error);

                    if ($fixed_rate_applicable == 0) {
                        $error = Mage::getModel('shipping/rate_result_error');
                        $error->setCarrier('shiphawk_shipping');
                        $error->setCarrierTitle('ShipHawk');
                        $error->setErrorMessage($error_text_from_config);
                        $result->append($error);

                    } else {

                        $fixed_price = $this->_getFixedPrice($api_calls_params[$i]['discount_items']);
                        $fixed_rate_id = $method_title . '_' . $api_calls_params[$i]['from_zip'] . '_' . $api_calls_params[$i]['to_zip'] . '_' . $fixed_price;
                        if ($free_shipping_setting != 'none') {
                            if ($request->getFreeShipping()) {
                                $fixed_price = 0;
                            }
                        }
                        $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                        $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($api_calls_params[$i]['discount_items']);
                        $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                        $toOrder[$fixed_rate_id]['name'] = $method_title;
                        $toOrder[$fixed_rate_id]['items'] = $api_calls_params[$i]['discount_items'];
                        $toOrder[$fixed_rate_id]['from_zip'] = $api_calls_params[$i]['from_zip'];
                        $toOrder[$fixed_rate_id]['to_zip'] = $api_calls_params[$i]['to_zip'];
                        $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                        $toOrder[$fixed_rate_id]['packing_info'] = '';
                        $toOrder[$fixed_rate_id]['carrier_type'] = $api_calls_params[$i]['carrier_type'];
                        $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                        $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                        $toOrder[$fixed_rate_id]['self_pack'] = $api_calls_params[$i]['self_pack'];
                        $toOrder[$fixed_rate_id]['custom_products_packing_price'] = $api_calls_params[$i]['custom_products_packing_price'];
                        $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                        $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;


                    }
                }

                if (is_object($api_responses[$i])) {
                    $api_error = true;
                    if (property_exists($api_responses[$i], 'error')) {
                        $shiphawk_error = (string)$api_responses[$i]->error;
                        $helper->shlog('ShipHawk response: ' . $shiphawk_error);
                        $helper->sendErrorMessageToShipHawk($shiphawk_error);
                        if ($fixed_rate_applicable == 0) {
                            $error = Mage::getModel('shipping/rate_result_error');
                            $error->setCarrier('shiphawk_shipping');
                            $error->setCarrierTitle('ShipHawk');
                            $error->setErrorMessage($error_text_from_config);
                            $result->append($error);

                        }
                    }

                    if ($fixed_rate_applicable != 0) {
                        $fixed_price = $this->_getFixedPrice($api_calls_params[$i]['discount_items']);
                        $fixed_rate_id = $method_title . '_' . $api_calls_params[$i]['from_zip'] . '_' . $api_calls_params[$i]['to_zip'] . '_' . $fixed_price;
                        if ($free_shipping_setting != 'none') {
                            if ($request->getFreeShipping()) {
                                $fixed_price = 0;
                            }
                        }
                        $result->append($this->_getShiphawkFixedRateObject($fixed_price, $fixed_rate_id));

                        $toOrder[$fixed_rate_id]['product_ids'] = $this->getProductIds($api_calls_params[$i]['discount_items']);
                        $toOrder[$fixed_rate_id]['price'] = $fixed_price;
                        $toOrder[$fixed_rate_id]['name'] = $method_title;
                        $toOrder[$fixed_rate_id]['items'] = $api_calls_params[$i]['discount_items'];
                        $toOrder[$fixed_rate_id]['from_zip'] = $api_calls_params[$i]['from_zip'];
                        $toOrder[$fixed_rate_id]['to_zip'] = $api_calls_params[$i]['to_zip'];
                        $toOrder[$fixed_rate_id]['carrier'] = 'ShipHawk';
                        $toOrder[$fixed_rate_id]['packing_info'] = '';
                        $toOrder[$fixed_rate_id]['carrier_type'] = $api_calls_params[$i]['carrier_type'];
                        $toOrder[$fixed_rate_id]['shiphawk_discount_fixed'] = null;
                        $toOrder[$fixed_rate_id]['shiphawk_discount_percentage'] = null;
                        $toOrder[$fixed_rate_id]['self_pack'] = $api_calls_params[$i]['self_pack'];
                        $toOrder[$fixed_rate_id]['custom_products_packing_price'] = $api_calls_params[$i]['custom_products_packing_price'];
                        $toOrder[$fixed_rate_id]['rate_price_for_group'] = round($fixed_price, 2);
                        $toOrder[$fixed_rate_id]['shiphawk_disabled'] = 1;

                    }
                } else {
                    // if $rate_filter = 'best' then it is only one rate
                    if ($rate_filter == 'best') {
                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                        $flat_markup_discount = $api_calls_params[$i]['flat_markup_discount'];
                        $percentage_markup_discount = $api_calls_params[$i]['percentage_markup_discount'];

                        $service_price = $helper->getShipHawkPrice($api_responses[$i][0], $self_pack, $charge_customer_for_packing);
                        //$rate_price_for_group = $helper->getSummaryPrice($response, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                        if (empty($flat_markup_discount) && (empty($percentage_markup_discount))) {
                            $rate_price_for_group = $helper->getDiscountShippingPrice($service_price);
                        } else {
                            $rate_price_for_group = $helper->getProductDiscountMarkupPrice($service_price, $percentage_markup_discount, $flat_markup_discount);
                        }

                        // fix for pre destination accessories
                        $rate_price_for_group = $rate_price_for_group + $this->getDefaultAccessoriesPrice($api_responses[$i][0]->shipping->carrier_accessorial);

                        $toOrder[$api_responses[$i][0]->id]['product_ids'] = $this->getProductIds($api_calls_params[$i]['discount_items']);
                        $toOrder[$api_responses[$i][0]->id]['price'] = $service_price;
                        $toOrder[$api_responses[$i][0]->id]['name'] = $api_responses[$i][0]->shipping->service;
                        $toOrder[$api_responses[$i][0]->id]['items'] = $api_calls_params[$i]['discount_items'];
                        $toOrder[$api_responses[$i][0]->id]['from_zip'] = $api_calls_params[$i]['from_zip'];
                        $toOrder[$api_responses[$i][0]->id]['to_zip'] = $api_calls_params[$i]['to_zip'];
                        $toOrder[$api_responses[$i][0]->id]['carrier'] = $this->getCarrierName($api_responses[$i][0]);
                        $toOrder[$api_responses[$i][0]->id]['packing_info'] = $this->getPackeges($api_responses[$i][0]);
                        $toOrder[$api_responses[$i][0]->id]['carrier_type'] = $api_calls_params[$i]['carrier_type'];
                        $toOrder[$api_responses[$i][0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                        $toOrder[$api_responses[$i][0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                        $toOrder[$api_responses[$i][0]->id]['self_pack'] = $api_calls_params[$i]['self_pack'];
                        $toOrder[$api_responses[$i][0]->id]['custom_products_packing_price'] = $api_calls_params[$i]['custom_products_packing_price'];
                        $toOrder[$api_responses[$i][0]->id]['rate_price_for_group'] = round($rate_price_for_group, 2);
                        $toOrder[$api_responses[$i][0]->id]['shiphawk_disabled'] = null;
                        $toOrder[$api_responses[$i][0]->id]['to_type_address'] = $shLocationType;
                        $toOrder[$api_responses[$i][0]->id]['destination_accessories'] = $pre_accessories; //for re-rate
                        $toOrder[$api_responses[$i][0]->id]['default_accessories'] = $api->getDefaultAccessoriesForBook($api_responses[$i][0]->shipping->carrier_accessorial);
                        $toOrder[$api_responses[$i][0]->id]['to_country_code'] = $to_country_code;
                    } else {

                        foreach ($api_responses[$i] as $responseItem) {
                            $flat_markup_discount = $api_calls_params[$i]['flat_markup_discount'];
                            $percentage_markup_discount = $api_calls_params[$i]['percentage_markup_discount'];

                            $service_price = $helper->getShipHawkPrice($responseItem, $self_pack, $charge_customer_for_packing);
                            if (empty($flat_markup_discount) && (empty($percentage_markup_discount))) {
                                $rate_price_for_group = $helper->getDiscountShippingPrice($service_price);
                            } else {
                                $rate_price_for_group = $helper->getProductDiscountMarkupPrice($service_price, $percentage_markup_discount, $flat_markup_discount);
                            }

                            // fix for pre destination accessories
                            $rate_price_for_group = $rate_price_for_group + $this->getDefaultAccessoriesPrice($responseItem->shipping->carrier_accessorial);

                            $toOrder[$responseItem->id]['product_ids'] = $this->getProductIds($api_calls_params[$i]['discount_items']);
                            $toOrder[$responseItem->id]['price'] = $helper->getShipHawkPrice($responseItem, $self_pack, $charge_customer_for_packing);
                            $toOrder[$responseItem->id]['name'] = $responseItem->shipping->service;
                            $toOrder[$responseItem->id]['items'] = $api_calls_params[$i]['discount_items'];
                            $toOrder[$responseItem->id]['from_zip'] = $api_calls_params[$i]['from_zip'];
                            $toOrder[$responseItem->id]['to_zip'] = $api_calls_params[$i]['to_zip'];
                            $toOrder[$responseItem->id]['carrier'] = $this->getCarrierName($responseItem);
                            $toOrder[$responseItem->id]['packing_info'] = $this->getPackeges($responseItem);
                            $toOrder[$responseItem->id]['carrier_type'] = $api_calls_params[$i]['carrier_type'];
                            $toOrder[$responseItem->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                            $toOrder[$responseItem->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                            $toOrder[$responseItem->id]['self_pack'] = $api_calls_params[$i]['self_pack'];
                            $toOrder[$responseItem->id]['custom_products_packing_price'] = $api_calls_params[$i]['custom_products_packing_price'];
                            $toOrder[$responseItem->id]['rate_price_for_group'] = round($rate_price_for_group, 2);
                            $toOrder[$responseItem->id]['shiphawk_disabled'] = null;
                            $toOrder[$responseItem->id]['to_type_address'] = $shLocationType;
                            $toOrder[$responseItem->id]['destination_accessories'] = $pre_accessories; // for re-rate
                            $toOrder[$responseItem->id]['default_accessories'] = $api->getDefaultAccessoriesForBook($responseItem->shipping->carrier_accessorial);
                            $toOrder[$responseItem->id]['to_country_code'] = $to_country_code;
                        }
                    }
                }
            }
            //end process responses into old data objects


            if (($fixed_rate_applicable == 0)&&($api_error)) {
                /* get error and backup rate is disable */
            }else{
                if(!empty($api_responses)) {

                    foreach ($api_responses as $api_response) {
                        if(is_object($api_response))
                        if (property_exists($api_response, 'error'))
                        continue;

                        $services = $this->getServices($api_response, $toOrder, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting);

                        $cheapest_rate_id = $this->_getCheapestRateId($services);

                        foreach ($services as $id_service => $service) {
                            //add ShipHawk shipping
                            //$shipping_price = $helper->getTotalDiscountShippingPrice($service['price'], $toOrder[$id_service]);
                            $shipping_price = $service['price'];
                            if ((empty($service['shiphawk_discount_fixed'])) && (empty($service['shiphawk_discount_percentage']))) {
                                $shipping_price = $helper->getDiscountShippingPrice($service['price']);
                            } else {
                                $shipping_price = $helper->getProductDiscountMarkupPrice($service['price'], $service['shiphawk_discount_percentage'], $service['shiphawk_discount_fixed']);
                            }

                            if ($free_shipping_setting != 'none') {
                                if ($request->getFreeShipping()) {

                                    // get cheapest rate id
                                    if ($cheapest_rate_id == $id_service) {
                                        $shipping_price = 0;
                                    }

                                    if ($free_shipping_setting == 'all') {
                                        $shipping_price = 0;
                                    }
                                }
                            }

                            if ($is_admin == false) {
                                $result->append($this->_getShiphawkRateObject($service['name'], $shipping_price, $service['shiphawk_price'], $service['accessorial'], $id_service, $is_multi_zip));
                            } else {
                                $result->append($this->_getShiphawkRateObject($service['name'] . ' - ' . $service['carrier'], $shipping_price, $service['shiphawk_price'], $service['accessorial'], $id_service, $is_multi_zip));
                            }
                        }
                    }

                }

                if ($is_multi_zip) {
                    $multiple_price = Mage::getSingleton('checkout/session')->getData('multiple_price_checkout');
                    if (is_null($multiple_price)) {
                        $multiple_price = $this->getSumOfCheapestRates($toOrder);

                        if ($free_shipping_setting != 'none') {
                            if ($request->getFreeShipping()) {

                                //if ($free_shipping_setting == 'all') {
                                    $multiple_price = 0;
                                //}
                            }
                        }
                    }

                    Mage::getSingleton('checkout/session')->unsetData('multiple_price_checkout');

                    $result->append($this->_getShiphawkMultipleShippingRateObject($multiple_price));
                    Mage::getSingleton('core/session')->setMultiZipCode(true);
                }
            }

            //save rate_id info for Book
            Mage::getSingleton('core/session')->setShiphawkBookId($toOrder);

            if ($quote->getIsMultiShipping()) {// if multi destination address order

                $shiphawk_book_id_from_quote = $quote->getShiphawkBookId();

                if($shiphawk_book_id_from_quote) {
                    $shiphawk_book_id_from_quote = unserialize($shiphawk_book_id_from_quote);
                    $shiphawk_book_id_from_quote[$shipping_address_id] = $toOrder;
                }else{
                    $shiphawk_book_id_from_quote = array();
                    $shiphawk_book_id_from_quote[$shipping_address_id] = $toOrder;
                }

                $quote->setShiphawkBookId(serialize($shiphawk_book_id_from_quote));
                $quote->save();
            }


            //save rate filter to order
            Mage::getSingleton('core/session')->setShiphawkRateFilter($rate_filter);

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

    public function checkShippingAddress($request, $quote) {
        $shippingAddresses = $quote->getAllShippingAddresses();
        $request_data = array();

        $request_data['dest_country_id'] = $request->getData('dest_country_id');
        $request_data['dest_region_id'] = $request->getData('dest_region_id');
        $request_data['dest_street'] = $request->getData('dest_street');
        $request_data['dest_city'] = $request->getData('dest_city');
        $request_data['dest_postcode'] = $request->getData('dest_postcode');

        $customer_shipping_address_id = null;
        //todo address->getStreet() = array , trim($address->getStreet()[0]) = first street
        foreach($shippingAddresses as $address) {
            if(($address->getCountryId() == $request_data['dest_country_id'])&&($address->getRegionId() == $request_data['dest_region_id'])&&(trim($address->getStreet()[0]) == trim($request_data['dest_street']))&&($address->getCity() == $request_data['dest_city'])&&($address->getPostcode() == $request_data['dest_postcode'])){
                $customer_shipping_address_id = $address->getCustomerAddressId();
            }
        }

        return $customer_shipping_address_id;
    }

    public function getSumOfCheapestRates($toOrder) {
        $sum_price = 0;
        $tmp = array();
        foreach($toOrder as $rate_id=>$data) {
            $tmp[serialize($data['product_ids'])][] = $data['rate_price_for_group'];
        }

        foreach ($tmp as $pr_ids => $array_prices) {
            $sum_price += round(min($array_prices), 2);
        }

        return $sum_price;
    }

    public function getSumOfCheapestRatesId($toOrder) {
        $cheapest_price = array();
        $tmp = array();
        foreach($toOrder as $rate_id=>$data) {
            $tmp[serialize($data['product_ids'])][] = $data['rate_price_for_group'];
        }

        foreach ($tmp as $pr_ids => $array_prices) {
            $cheapest_price[] = round(min($array_prices), 2);
        }

        return serialize($cheapest_price);
    }

    /**
     * Get Standard rate object
     *
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getShiphawkRateObject($method_title, $price, $true_price, $accessorial, $id = '', $is_multi_zip = false)
    {
        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        $rate = Mage::getModel('shipping/rate_result_method');

        $ship_rate_id = str_replace('-', '_', str_replace(',', '', str_replace(' ', '_', $method_title.$true_price)));
        if($is_multi_zip) {
            $id = substr($id,0,10);
            $ship_rate_id = str_replace('-', '_', str_replace(',', '', str_replace(' ', '_', $id.$method_title.$true_price)));
        }



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

    /**
     * Get Fixed ShipHawk rate
     * @param $products
     * @paeam $price
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getShiphawkFixedRateObject($price,$fixed_rate_id) {
        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        $rate = Mage::getModel('shipping/rate_result_method');
        $method_title = Mage::getStoreConfig('carriers/shiphawk_shipping/rate_title');

        $ship_rate_id = str_replace('-', '_', str_replace(',', '', str_replace(' ', '_', $method_title.$fixed_rate_id)));

        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle('ShipHawk');
        $rate->setMethodTitle($method_title);
        $rate->setMethod($ship_rate_id);
        $rate->setPrice($price);
        $rate->setCost($price);

        return $rate;
    }

    public function _getFixedPrice($products = array()) {
        $price = Mage::getStoreConfig('carriers/shiphawk_shipping/rate_price');
        $rate_type = Mage::getStoreConfig('carriers/shiphawk_shipping/rate_type');
        if($rate_type == 2) {
            $qty = 0;
            foreach ($products as $product) {
                $qty = $qty + $product['quantity'];
            }
            $price = $price * $qty;

        }

        return round($price,2);
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

                $disable_shiphawk_shipping = $product->getShiphawkDisableShipping();

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
                    'shiphawk_disable_shipping'=> (empty($disable_shiphawk_shipping)) ? null  : $disable_shiphawk_shipping,
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

                $disable_shiphawk_shipping = $product->getShiphawkDisableShipping();

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
                    'shiphawk_disable_shipping'=> (empty($disable_shiphawk_shipping)) ? null  : $disable_shiphawk_shipping,
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

    public function getToCountryCode() {
        if (Mage::app()->getStore()->isAdmin()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }else{
            /** @var $cart Mage_Checkout_Model_Cart */
            $cart = Mage::getSingleton('checkout/cart');
            $quote = $cart->getQuote();
        }
        $shippingAddress = $quote->getShippingAddress();
        $country_id = $shippingAddress->getCountryId();
        return $country_id;
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
            // get zip code from Shipping Origin
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
            //$tmp[serialize($data['product_ids'])][] = $data['rate_price_for_group'];
            $tmp[serialize($data['product_ids'])][] = $data['price'];
        }

        $t_rate = array();
        foreach($_rates as $_rate){
            if($_rate->getCarrier() !== 'shiphawk_shipping') continue;
            $t_price = (string) round($_rate->getPrice(),2);
            $rate_id = $_rate->getId();
            $t_rate[$rate_id] = $_rate;
        }

        $tt = array();
        foreach ($tmp as $pr_ids=>$prices) {
            foreach ($prices as $price) {
                $t_price = round($price,2);
                foreach($t_rate as $id=>$rate_object) {
                    //if($t_price == $rate_object->getPrice()) { // check price in rate code
                    if(Mage::helper('shiphawk_shipping')->getOriginalShipHawkShippingPrice($rate_object->getCode(), (string) $t_price)) {
                        $tt[$pr_ids][] = $rate_object;
                        unset($t_rate[$id]);
                        break;
                    }
                }
            }
        }

        return $tt;
    }

    /* sort items by disable shiphawk shipping parameter */
    public function getGroupedItemsByDisabledShipping($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['shiphawk_disable_shipping']][] = $item;
        }
        return $tmp;
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

    public function getCheapestRateIdForBooking($responseObject, $self_pack) {
        $t = array();
        $helper = Mage::helper('shiphawk_shipping');
        foreach ($responseObject as $response) {
            $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
            $t[$response->id] = $shipping_price;
        }
        asort($t);
        $rate_id = key($t);

        return $rate_id;
    }

    public function getServices($ship_response, $toOrder, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting) {

        $services = array();
        $helper = Mage::helper('shiphawk_shipping');
        //foreach($ship_responses as $ship_response) {
          //  if(is_array($ship_response)) {
                $custom_products_packing_price = 0;
                foreach($ship_response as $object) {
                    $services[$object->id]['name'] = $this->_getServiceName($object);

                    foreach($toOrder as $rate_id=>$rate_data) {
                        if($rate_id == $object->id){
                            $services[$object->id]['custom_products_packing_price'] = $rate_data['custom_products_packing_price'];
                            $custom_products_packing_price = $rate_data['custom_products_packing_price'];
                            break;
                        }
                    }

                    /* accesorial info */
                    $accessorial = $object->shipping->carrier_accessorial;
                    $services[$object->id]['accessorial'] = $accessorial;

                    $default_accessories_price = $this->getDefaultAccessoriesPrice($accessorial);

                    // price for customer
                    $services[$object->id]['price'] = $helper->getSummaryPrice($object, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price, $default_accessories_price);
                    $services[$object->id]['carrier'] = $this->getCarrierName($object);
                    $services[$object->id]['shiphawk_price'] = $helper->getShipHawkPrice($object, $self_pack, $charge_customer_for_packing);

                    // shipping rate price from shiphawk
                    $services[$object->id]['packing']['price'] = $object->packing->price;
                    /* packing info */
                    $services[$object->id]['packing']['info'] = $this->getPackeges($object);
                    $services[$object->id]['delivery'] = '';
                    $services[$object->id]['carrier_type'] = '';

                    /* discount-markup by product or by sys. conf. */

                    foreach($toOrder as $rate_id=>$rate_data) {
                        if($rate_id == $object->id){
                            $services[$object->id]['shiphawk_discount_fixed'] = $rate_data['shiphawk_discount_fixed'];
                            $services[$object->id]['shiphawk_discount_percentage'] = $rate_data['shiphawk_discount_percentage'];
                            break;
                        }
                    }
                }
            //}
        //}

        return $services;
    }

    public function getDefaultAccessoriesPrice($accessorial) {
        $default_accessories_price = 0;
        if(!empty($accessorial)) {
            $pre_accerories = Mage::helper('shiphawk_shipping')->preSetAccessories();
            foreach($accessorial as $orderAccessoriesType => $orderAccessor) {
                if ($orderAccessoriesType == 'destination') {
                    foreach($orderAccessor as $orderAccessorValues) {
                        //if(!in_array($orderAccessorValues->accessorial_type, $pre_accerories)) {
                        if($orderAccessorValues->default){
                            $default_accessories_price += $orderAccessorValues->price;
                        }
                        //}
                    }
                }
            }
        }

        return $default_accessories_price;
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