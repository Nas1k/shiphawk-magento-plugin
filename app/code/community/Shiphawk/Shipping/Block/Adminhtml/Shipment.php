<?php
class Shiphawk_Shipping_Block_Adminhtml_Shipment extends Mage_Core_Block_Template
{
    /**
     * Get ShipHawk Shipping Rate for order with not ShipHawk shipping method
     * @param $order
     * @return array|null
     */
    public function getNewShipHawkRate($order) {

        $carrier = Mage::getModel('shiphawk_shipping/carrier');
        $api = Mage::getModel('shiphawk_shipping/api');
        $helper = Mage::helper('shiphawk_shipping');

        $shLocationType = $order->getShiphawkLocationType();

        $result = array();

        $items = $carrier->getShiphawkItems($order);

        /* sort items by origin id */
        $grouped_items_by_zip = $carrier->getGroupedItemsByZip($items);

        // sort items by carrier type
        $grouped_items_by_carrier_type = $carrier->getGroupedItemsByCarrierType($items);
        $grouped_items_by_disabled_shipping = $carrier->getGroupedItemsByDisabledShipping($items);

        $error_message = 'Sorry, not all products have necessary ShipHawk fields filled in. Please add necessary data for next products (or check required attributes):';

        $shippingAddress = $order->getShippingAddress();
        $to_zip = $shippingAddress->getPostcode();

        $ship_responces = array();
        $toOrder= array();
        $api_error = false;
        $is_multi_zip = false;
        $is_multi_carrier = false;
        $api_calls_params = array();
        $pre_accessories = null;
        $name_service = '';
        $multiple_price = null;

        if(count($grouped_items_by_zip) > 1) {
            $is_multi_zip = true;
        }

        if(count($grouped_items_by_carrier_type) > 1) {
            $is_multi_carrier = true;
            $is_multi_zip = true;
        }

        $is_admin = $helper->checkIsAdmin();
        $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin, $order);
        $carrier_type = Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');
        $error_text_from_config = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_error_message');
        $fixed_rate_applicable = Mage::getStoreConfig('carriers/shiphawk_shipping/applicable');
        $method_title = Mage::getStoreConfig('carriers/shiphawk_shipping/rate_title');

        $custom_packing_price_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_custom_packing_price');

        $self_pack = $helper->getSelfPacked();

        $charge_customer_for_packing = Mage::getStoreConfig('carriers/shiphawk_shipping/charge_customer_for_packing');

        $result['error'] = '';
        $result['multiple_shipments_id'] = null;
        //default origin zip code
        $from_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        if(count($grouped_items_by_disabled_shipping) > 1)  {
            //Mage::getSingleton('core/session')->setMultiZipCode(true);
            $is_multi_zip = true;
        }

        foreach ($grouped_items_by_disabled_shipping as $disable=>$product_items) {
            if($disable == 1) {
                //disable the ShipHawk shipping method at the product level
                $api_error = true;
                if (($fixed_rate_applicable == 0)||(($fixed_rate_applicable == 1)&&($is_multi_zip == false))) {

                    echo $error_text_from_config;

                } else {
                    $fixed_price = $carrier->_getFixedPrice($product_items);
                    // if shiphawk is disabled, from zip by default
                    $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;

                    $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($product_items);
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
                $grouped_items_by_zip = $carrier->getGroupedItemsByZip($product_items);
                $grouped_items_by_carrier_type = $carrier->getGroupedItemsByCarrierType($product_items);

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

                        $grouped_items_by_origin = $carrier->getGroupedItemsByZip($items_);

                        foreach ($grouped_items_by_origin as $origin_id => $items__) {

                            if ($origin_id != 'origin_per_product') { // product has origin id or primary origin

                                if ($origin_id) {
                                    $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                                    $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                                }

                                $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items__, $rate_filter);

                                $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items__);

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
                                            'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories),
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
                                            echo $error_text_from_config;
                                            break 3;
                                        } else {
                                            $fixed_price = $carrier->_getFixedPrice($discount_items);
                                            $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;

                                            $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($discount_items);
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

                                $grouped_items_per_product_by_zip = $carrier->getGroupedItemsByZipPerProduct($items__);
                                foreach ($grouped_items_per_product_by_zip as $from_zip => $items_per_product) {

                                    $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);


                                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                    $from_zip = $items_[0]['zip'];
                                    $location_type = $items_[0]['location_type'];

                                    $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items_per_product);
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
                                                'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories),
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
                                                echo $error_text_from_config;
                                                break 3;
                                            } else {
                                                $fixed_price = $carrier->_getFixedPrice($discount_items);
                                                $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;

                                                $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($discount_items);
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

                            $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items_);
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
                                        'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories),
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
                                        echo $error_text_from_config;
                                        break 2;
                                    } else {
                                        $fixed_price = $carrier->_getFixedPrice($discount_items);
                                        $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;

                                        $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($discount_items);
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
                            $grouped_items_per_product_by_zip = $carrier->getGroupedItemsByZipPerProduct($items_);

                            if (count($grouped_items_per_product_by_zip) > 1) {
                                $is_multi_zip = true;
                            }

                            foreach ($grouped_items_per_product_by_zip as $from_zip => $items_per_product) {

                                $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                                $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items_per_product);

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
                                            'api_call' => $api->buildShiphawkRequest($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories),
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
                                            echo $error_text_from_config;
                                            break 2;
                                        } else {
                                            $fixed_price = $carrier->_getFixedPrice($discount_items);
                                            $fixed_rate_id = $method_title . '_' . $from_zip . '_' . $to_zip . '_' . $fixed_price;

                                            $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($discount_items);
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

        do {
            curl_multi_exec($mh, $running);
        } while ($running);

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
                    echo $error_text_from_config;
                    break;
                } else {

                    $fixed_price = $carrier->_getFixedPrice($api_calls_params[$i]['discount_items']);
                    $fixed_rate_id = $method_title . '_' . $api_calls_params[$i]['from_zip'] . '_' . $api_calls_params[$i]['to_zip'] . '_' . $fixed_price;

                    $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($api_calls_params[$i]['discount_items']);
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
                    break;

                }
            }

            if (is_object($api_responses[$i])) {
                $api_error = true;
                if (property_exists($api_responses[$i], 'error')) {
                    $shiphawk_error = (string)$api_responses[$i]->error;
                    $helper->shlog('ShipHawk response: ' . $shiphawk_error);
                    $helper->sendErrorMessageToShipHawk($shiphawk_error);
                    if ($fixed_rate_applicable == 0) {
                        echo $error_text_from_config;
                        break;
                    }
                }

                if ($fixed_rate_applicable != 0) {
                    $fixed_price = $carrier->_getFixedPrice($api_calls_params[$i]['discount_items']);
                    $fixed_rate_id = $method_title . '_' . $api_calls_params[$i]['from_zip'] . '_' . $api_calls_params[$i]['to_zip'] . '_' . $fixed_price;

                    $toOrder[$fixed_rate_id]['product_ids'] = $carrier->getProductIds($api_calls_params[$i]['discount_items']);
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
                    break;
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

                    $toOrder[$api_responses[$i][0]->id]['product_ids'] = $carrier->getProductIds($api_calls_params[$i]['discount_items']);
                    $toOrder[$api_responses[$i][0]->id]['price'] = $service_price;
                    $toOrder[$api_responses[$i][0]->id]['name'] = $api_responses[$i][0]->shipping->service;
                    $toOrder[$api_responses[$i][0]->id]['items'] = $api_calls_params[$i]['discount_items'];
                    $toOrder[$api_responses[$i][0]->id]['from_zip'] = $api_calls_params[$i]['from_zip'];
                    $toOrder[$api_responses[$i][0]->id]['to_zip'] = $api_calls_params[$i]['to_zip'];
                    $toOrder[$api_responses[$i][0]->id]['carrier'] = $carrier->getCarrierName($api_responses[$i][0]);
                    $toOrder[$api_responses[$i][0]->id]['packing_info'] = $carrier->getPackeges($api_responses[$i][0]);
                    $toOrder[$api_responses[$i][0]->id]['carrier_type'] = $api_calls_params[$i]['carrier_type'];
                    $toOrder[$api_responses[$i][0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                    $toOrder[$api_responses[$i][0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                    $toOrder[$api_responses[$i][0]->id]['self_pack'] = $api_calls_params[$i]['self_pack'];
                    $toOrder[$api_responses[$i][0]->id]['custom_products_packing_price'] = $api_calls_params[$i]['custom_products_packing_price'];
                    $toOrder[$api_responses[$i][0]->id]['rate_price_for_group'] = round($rate_price_for_group, 2);
                    $toOrder[$api_responses[$i][0]->id]['shiphawk_disabled'] = null;
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

                        $toOrder[$responseItem->id]['product_ids'] = $carrier->getProductIds($api_calls_params[$i]['discount_items']);
                        $toOrder[$responseItem->id]['price'] = $helper->getShipHawkPrice($responseItem, $self_pack, $charge_customer_for_packing);
                        $toOrder[$responseItem->id]['name'] = $responseItem->shipping->service;//
                        $toOrder[$responseItem->id]['items'] = $api_calls_params[$i]['discount_items'];
                        $toOrder[$responseItem->id]['from_zip'] = $api_calls_params[$i]['from_zip'];
                        $toOrder[$responseItem->id]['to_zip'] = $api_calls_params[$i]['to_zip'];
                        $toOrder[$responseItem->id]['carrier'] = $carrier->getCarrierName($responseItem);
                        $toOrder[$responseItem->id]['packing_info'] = $carrier->getPackeges($responseItem);
                        $toOrder[$responseItem->id]['carrier_type'] = $api_calls_params[$i]['carrier_type'];
                        $toOrder[$responseItem->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                        $toOrder[$responseItem->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                        $toOrder[$responseItem->id]['self_pack'] = $api_calls_params[$i]['self_pack'];
                        $toOrder[$responseItem->id]['custom_products_packing_price'] = $api_calls_params[$i]['custom_products_packing_price'];
                        $toOrder[$responseItem->id]['rate_price_for_group'] = round($rate_price_for_group, 2);
                        $toOrder[$responseItem->id]['shiphawk_disabled'] = null;
                    }
                }
            }
        }
        //end process responses into old data objects



        //if(!$api_error) {
            if($is_multi_zip) {
                $name_service = 'Shipping from multiple location';
                $multiple_price = $carrier->getSumOfCheapestRates($toOrder);
                $multiple_shipments_id = $carrier->getSumOfCheapestRatesId($toOrder);
                $result['multiple_shipments_id'] = $multiple_shipments_id;
                $result['summ_price'] = $multiple_price;
            }

            //save rate_id info for Book in PopUP
            Mage::getSingleton('core/session')->setNewShiphawkBookId($toOrder);

            //save rate filter to order
            Mage::getSingleton('core/session')->setShiphawkRateFilter($rate_filter);

        /*}else{
            echo $error_text_from_config;
            $result['error'] = $error_text_from_config;
        }*/

        $result['name_service'] = $name_service;


        $result['rate_filter'] = $rate_filter;
        $result['is_multi_zip'] = $is_multi_zip;
        $result['to_order'] = $toOrder;

        return $result;
    }

}
