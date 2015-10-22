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

        $error_message = 'Sorry, not all products have necessary ShipHawk fields filled in. Please add necessary data for next products (or check required attributes):';

        $shippingAddress = $order->getShippingAddress();
        $to_zip = $shippingAddress->getPostcode();

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

        $is_admin = $helper->checkIsAdmin();
        $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin, $order);
        $carrier_type = Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');

        $custom_packing_price_setting = Mage::getStoreConfig('carriers/shiphawk_shipping/shiphawk_custom_packing_price');

        $self_pack = $helper->getSelfPacked();

        $charge_customer_for_packing = Mage::getStoreConfig('carriers/shiphawk_shipping/charge_customer_for_packing');

        $result['error'] = '';
        //default origin zip code
        $from_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');

        /* items has various carrier type */
        if($is_multi_carrier) {
            foreach($grouped_items_by_carrier_type as $carrier_type=>$items_) {

                if($carrier_type) {
                    $carrier_type = explode(',', $carrier_type);
                }else{
                    $carrier_type = '';
                }

                $grouped_items_by_origin = $carrier->getGroupedItemsByZip($items_);

                foreach($grouped_items_by_origin as $origin_id=>$items__) {

                    if ($origin_id != 'origin_per_product') { // product has origin id or primary origin

                        $rate_filter = 'best'; // multi carrier

                        if($origin_id) {
                            $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                            $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                        }

                        $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items__, $rate_filter);

                        if(empty($checkattributes)) {

                            $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items__);

                            foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {
                                /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                $from_zip = $items__[0]['zip'];
                                $location_type = $items__[0]['location_type'];
                                // 1. multi carrier, multi origin, not origin per product
                                $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                $custom_products_packing_price = 0;

                                if($custom_packing_price_setting) {
                                    $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                }

                                $ship_responces[] = $responceObject;

                                if(is_object($responceObject)) {
                                    $api_error = true;
                                    $shiphawk_error = (string) $responceObject->error;
                                    //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                    $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                    $error_message = 'ShipHawk error: '. $shiphawk_error;
                                    echo $error_message ;
                                    $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                    return null;
                                }else{
                                    // if $rate_filter = 'best' then it is only one rate
                                    if(($is_multi_zip)||($rate_filter == 'best')) {
                                        //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                        $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                        $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                        $toOrder[$responceObject[0]->id]['product_ids'] = $carrier->getProductIds($discount_items);
                                        //$toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                        //$toOrder[$responceObject[0]->id]['price'] = $helper->getShipHawkPrice($responceObject[0], $self_pack, $charge_customer_for_packing);
                                        $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0], $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                        $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                        $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                        $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                        $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                        $toOrder[$responceObject[0]->id]['carrier'] = $carrier->getCarrierName($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['packing_info'] = $carrier->getPackeges($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                        $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                        $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        $toOrder[$responceObject[0]->id]['self_pack'] = $self_pack;
                                        $toOrder[$responceObject[0]->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                        $toOrder[$responceObject[0]->id]['carrier_accessorial'] = $responceObject[0]->shipping->carrier_accessorial;
                                        //$responce->shipping->carrier_accessorial;
                                    }
                                }

                                if($is_multi_zip) {
                                    Mage::getSingleton('core/session')->setMultiZipCode(true);
                                }
                            }

                        }else{
                            $api_error = true;
                            /*foreach($checkattributes as $rate_error) {
                                $helper->shlog('ShipHawk error: '.$rate_error);
                            }*/
                            echo $error_message . '<br />';
                            if(!empty($checkattributes['items']['name']))
                                if(count($checkattributes['items']['name'])>0)
                                    foreach($checkattributes['items']['name'] as $names) {
                                        echo $names . '<br />';
                                    }

                            if (!empty($checkattributes['from_zip'])) {
                                echo 'From Zip' . '<br />';
                            }
                            if (!empty($checkattributes['to_zip'])) {
                                echo 'To Zip' . '<br />';
                            }

                            if (!empty($checkattributes['rate_filter'])) {
                                echo 'Rate Filter' . '<br />';
                            }
                            return null;
                        }
                    }else{ // product items has all required shipping origin fields

                        $grouped_items_per_product_by_zip = $carrier->getGroupedItemsByZipPerProduct($items__);

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

                                $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items_per_product);

                                foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                    //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                    $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                    $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                                    if($carrier_type) {
                                        $carrier_type = explode(',', $carrier_type);
                                    }else{
                                        $carrier_type = '';
                                    }

                                    // 2. multi carrier, multi origin, origin per product
                                    $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                    $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                    $custom_products_packing_price = 0;

                                    if($custom_packing_price_setting) {
                                        $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                    }

                                    $ship_responces[] = $responceObject;

                                    if(is_object($responceObject)) {
                                        $api_error = true;
                                        $shiphawk_error = (string) $responceObject->error;
                                        //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                        $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                        $error_message = 'ShipHawk error: '. $shiphawk_error;
                                        echo $error_message ;
                                        $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                        return null;
                                    }else{
                                        // if $rate_filter = 'best' then it is only one rate
                                        if(($is_multi_zip)||($rate_filter == 'best')) {

                                            $toOrder[$responceObject[0]->id]['product_ids'] = $carrier->getProductIds($discount_items);
                                            //$toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                            //$toOrder[$responceObject[0]->id]['price'] = $helper->getShipHawkPrice($responceObject[0], $self_pack, $charge_customer_for_packing);
                                            $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0], $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                            $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                            $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                            $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                            $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                            $toOrder[$responceObject[0]->id]['carrier'] = $carrier->getCarrierName($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['packing_info'] = $carrier->getPackeges($responceObject[0]);
                                            $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                            $toOrder[$responceObject[0]->id]['self_pack'] = $self_pack;
                                            $toOrder[$responceObject[0]->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                            $toOrder[$responceObject[0]->id]['carrier_accessorial'] = $responceObject[0]->shipping->carrier_accessorial;

                                        }
                                    }

                                    if($is_multi_zip) {
                                        Mage::getSingleton('core/session')->setMultiZipCode(true);
                                    }

                                }

                            }else{
                                $api_error = true;
                                /*foreach($checkattributes as $rate_error) {
                                    $helper->shlog('ShipHawk error: '.$rate_error);
                                }*/
                                echo $error_message . '<br />';
                                if(!empty($checkattributes['items']['name']))
                                    if(count($checkattributes['items']['name'])>0)
                                        foreach($checkattributes['items']['name'] as $names) {
                                            echo $names . '<br />';
                                        }

                                if (!empty($checkattributes['from_zip'])) {
                                    echo 'From Zip' . '<br />';
                                }
                                if (!empty($checkattributes['to_zip'])) {
                                    echo 'To Zip' . '<br />';
                                }

                                if (!empty($checkattributes['rate_filter'])) {
                                    echo 'Rate Filter' . '<br />';
                                }
                                return null;
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

                    if($is_multi_zip) {
                        $rate_filter = 'best';
                    }

                    if($origin_id) {
                        $shipHawkOrigin = Mage::getModel('shiphawk_shipping/origins')->load($origin_id);
                        $from_zip = $shipHawkOrigin->getShiphawkOriginZipcode();
                    }

                    $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_, $rate_filter);

                    if(empty($checkattributes)) {

                        $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items_);

                        if(count($grouped_items_by_discount_or_markup)>1) {
                            $is_multi_zip = true;
                        }

                        if($is_multi_zip) {
                            $rate_filter = 'best';
                        }

                        foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                            //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                            $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                            $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];

                            /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                            $from_zip = $discount_items[0]['zip'];
                            $location_type = $discount_items[0]['location_type'];

                            // 3. one carrier, multi origin, not origin per product
                            $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                            $helper->shlog($discount_items, 'shiphawk-items-request.log');

                            $custom_products_packing_price = 0;

                            if($custom_packing_price_setting) {
                                $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                            }

                            $ship_responces[] = $responceObject;

                            if(is_object($responceObject) or (empty($responceObject))) {
                                $api_error = true;
                                $shiphawk_error = (string) $responceObject->error;
                                //Mage::log('ShipHawk response: '. $shiphawk_error, null, 'ShipHawk.log');
                                $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                $error_message = 'ShipHawk error: '. $shiphawk_error;
                                echo $error_message ;
                                $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                return null;
                            }else{
                                // if $rate_filter = 'best' then it is only one rate
                                if(($is_multi_zip)||($rate_filter == 'best')) {

                                    $toOrder[$responceObject[0]->id]['product_ids'] = $carrier->getProductIds($discount_items);
                                    //$toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                    //$toOrder[$responceObject[0]->id]['price'] = $helper->getShipHawkPrice($responceObject[0], $self_pack, $charge_customer_for_packing);
                                    $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0], $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                    $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                    $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                    $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                    $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                    $toOrder[$responceObject[0]->id]['carrier'] = $carrier->getCarrierName($responceObject[0]);
                                    $toOrder[$responceObject[0]->id]['packing_info'] = $carrier->getPackeges($responceObject[0]);
                                    $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                    $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                    $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                    $toOrder[$responceObject[0]->id]['self_pack'] = $self_pack;
                                    $toOrder[$responceObject[0]->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                    $toOrder[$responceObject[0]->id]['carrier_accessorial'] = $responceObject[0]->shipping->carrier_accessorial;
                                }else{
                                    Mage::getSingleton('core/session')->setMultiZipCode(false);
                                    foreach ($responceObject as $responce) {
                                        $toOrder[$responce->id]['product_ids'] = $carrier->getProductIds($discount_items);
                                        //$toOrder[$responce->id]['price'] = $helper->getSummaryPrice($responce);
                                        //$toOrder[$responce->id]['price'] = $helper->getShipHawkPrice($responce, $self_pack, $charge_customer_for_packing);
                                        $toOrder[$responce->id]['price'] = $helper->getSummaryPrice($responce, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                        $toOrder[$responce->id]['name'] = $responce->shipping->service;//
                                        $toOrder[$responce->id]['items'] = $discount_items;
                                        $toOrder[$responce->id]['from_zip'] = $from_zip;
                                        $toOrder[$responce->id]['to_zip'] = $to_zip;
                                        $toOrder[$responce->id]['carrier'] = $carrier->getCarrierName($responce);
                                        $toOrder[$responce->id]['packing_info'] = $carrier->getPackeges($responce);
                                        $toOrder[$responce->id]['carrier_type'] = $carrier_type;
                                        $toOrder[$responce->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                        $toOrder[$responce->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        $toOrder[$responce->id]['self_pack'] = $self_pack;
                                        $toOrder[$responce->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                        $toOrder[$responce->id]['carrier_accessorial'] = $responce->shipping->carrier_accessorial;
                                    }
                                }

                                if($is_multi_zip) {
                                    Mage::getSingleton('core/session')->setMultiZipCode(true);
                                }
                            }
                        }
                    }else{
                        $api_error = true;
                        /*foreach($checkattributes as $rate_error) {
                            $helper->shlog('ShipHawk error: '.$rate_error);
                        }*/
                        echo $error_message . '<br />';
                        if(!empty($checkattributes['items']['name']))
                            if(count($checkattributes['items']['name'])>0)
                                foreach($checkattributes['items']['name'] as $names) {
                                    echo $names . '<br />';
                                }

                        if (!empty($checkattributes['from_zip'])) {
                            echo 'From Zip' . '<br />';
                        }
                        if (!empty($checkattributes['to_zip'])) {
                            echo 'To Zip' . '<br />';
                        }

                        if (!empty($checkattributes['rate_filter'])) {
                            echo 'Rate Filter' . '<br />';
                        }
                        return null;
                    }
                }else{

                    /* product items has per product origin, grouped by zip code */
                    $grouped_items_per_product_by_zip = $carrier->getGroupedItemsByZipPerProduct($items_);

                    if(count($grouped_items_per_product_by_zip) > 1 ) {
                        $is_multi_zip = true;
                    }

                    if($is_multi_zip) {
                        $rate_filter = 'best';
                    }

                    foreach ($grouped_items_per_product_by_zip as $from_zip=>$items_per_product) {

                        $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_per_product, $rate_filter);

                        if(empty($checkattributes)) {

                            $grouped_items_by_discount_or_markup = $carrier->getGroupedItemsByDiscountOrMarkup($items_per_product);

                            if(count($grouped_items_by_discount_or_markup)>1) {
                                $is_multi_zip = true;
                            }

                            if($is_multi_zip) {
                                $rate_filter = 'best';
                            }

                            foreach($grouped_items_by_discount_or_markup as $mark_up_discount=>$discount_items) {

                                //get percentage and flat markup-discount from first item, because all item in group has identical markup-discount
                                $flat_markup_discount = $discount_items[0]['shiphawk_discount_fixed'];
                                $percentage_markup_discount = $discount_items[0]['shiphawk_discount_percentage'];
                                /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                                $from_zip = $discount_items[0]['zip'];
                                $location_type = $discount_items[0]['location_type'];

                                // 4. one carrier, multi origin, origin per product
                                $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $discount_items, $rate_filter, $carrier_type, $location_type, $shLocationType);
                                $helper->shlog($discount_items, 'shiphawk-items-request.log');

                                $custom_products_packing_price = 0;

                                if($custom_packing_price_setting) {
                                    $custom_products_packing_price = $helper->getCustomPackingPriceSumm($discount_items);
                                }

                                $ship_responces[] = $responceObject;

                                if(is_object($responceObject)) {
                                    $api_error = true;
                                    $shiphawk_error = (string) $responceObject->error;
                                    $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                    $error_message = 'ShipHawk error: '. $shiphawk_error;
                                    echo $error_message ;
                                    $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                    return null;
                                }else{
                                    // if $rate_filter = 'best' then it is only one rate
                                    if(($is_multi_zip)||($rate_filter == 'best')) {

                                        $toOrder[$responceObject[0]->id]['product_ids'] = $carrier->getProductIds($discount_items);
                                        //$toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0]);
                                        //$toOrder[$responceObject[0]->id]['price'] = $helper->getShipHawkPrice($responceObject[0], $self_pack, $charge_customer_for_packing);
                                        $toOrder[$responceObject[0]->id]['price'] = $helper->getSummaryPrice($responceObject[0], $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                        $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->shipping->service;//
                                        $toOrder[$responceObject[0]->id]['items'] = $discount_items;
                                        $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                        $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                        $toOrder[$responceObject[0]->id]['carrier'] = $carrier->getCarrierName($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['packing_info'] = $carrier->getPackeges($responceObject[0]);
                                        $toOrder[$responceObject[0]->id]['carrier_type'] = $carrier_type;
                                        $toOrder[$responceObject[0]->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                        $toOrder[$responceObject[0]->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                        $toOrder[$responceObject[0]->id]['self_pack'] = $self_pack;
                                        $toOrder[$responceObject[0]->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                        $toOrder[$responceObject[0]->id]['carrier_accessorial'] = $responceObject[0]->shipping->carrier_accessorial;
                                    }else{
                                        Mage::getSingleton('core/session')->setMultiZipCode(false);
                                        foreach ($responceObject as $responce) {
                                            $toOrder[$responce->id]['product_ids'] = $carrier->getProductIds($discount_items);
                                            //$toOrder[$responce->id]['price'] = $helper->getSummaryPrice($responce);
                                            //$toOrder[$responce->id]['price'] = $helper->getShipHawkPrice($responce, $self_pack, $charge_customer_for_packing);
                                            $toOrder[$responce->id]['price'] = $helper->getSummaryPrice($responce, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting, $custom_products_packing_price);
                                            $toOrder[$responce->id]['name'] = $responce->shipping->service;//
                                            $toOrder[$responce->id]['items'] = $discount_items;
                                            $toOrder[$responce->id]['from_zip'] = $from_zip;
                                            $toOrder[$responce->id]['to_zip'] = $to_zip;
                                            $toOrder[$responce->id]['carrier'] = $carrier->getCarrierName($responce);
                                            $toOrder[$responce->id]['packing_info'] = $carrier->getPackeges($responce);
                                            $toOrder[$responce->id]['carrier_type'] = $carrier_type;
                                            $toOrder[$responce->id]['shiphawk_discount_fixed'] = $flat_markup_discount;
                                            $toOrder[$responce->id]['shiphawk_discount_percentage'] = $percentage_markup_discount;
                                            $toOrder[$responce->id]['self_pack'] = $self_pack;
                                            $toOrder[$responce->id]['custom_products_packing_price'] = $custom_products_packing_price;
                                            $toOrder[$responce->id]['carrier_accessorial'] = $responce->shipping->carrier_accessorial;
                                        }
                                    }

                                    if($is_multi_zip) {
                                        Mage::getSingleton('core/session')->setMultiZipCode(true);
                                    }
                                }
                            }
                        }else{
                            $api_error = true;
                            /*foreach($checkattributes as $rate_error) {
                                $helper->shlog('ShipHawk error: '.$rate_error);
                            }*/

                            echo $error_message . '<br />';
                            if(!empty($checkattributes['items']['name']))
                                if(count($checkattributes['items']['name'])>0)
                                    foreach($checkattributes['items']['name'] as $names) {
                                        echo $names . '<br />';
                                    }

                            if (!empty($checkattributes['from_zip'])) {
                                echo 'From Zip' . '<br />';
                            }
                            if (!empty($checkattributes['to_zip'])) {
                                echo 'To Zip' . '<br />';
                            }

                            if (!empty($checkattributes['rate_filter'])) {
                                echo 'Rate Filter' . '<br />';
                            }
                            return null;
                        }

                    }

                }
            }
        }

        $name_service = '';
        $summ_price = 0;
        if(!$api_error) {

            $services               = $carrier->getServices($ship_responces, $toOrder, $self_pack, $charge_customer_for_packing, $custom_packing_price_setting);

            foreach ($services as $id_service=>$service) {
                if (!$is_multi_zip) {

                }else{
                    $name_service .= $service['name'] . ', ';
                    $summ_price += $service['price'];
                }
            }
            //save rate_id info for Book in PopUP
            Mage::getSingleton('core/session')->setNewShiphawkBookId($toOrder);

            //remove last comma
            if(strlen($name_service) >2) {
                if ($name_service{strlen($name_service)-2} == ',') {
                    $name_service = substr($name_service,0,-2);
                }
            }
        }

        $result['name_service'] = $name_service;
        $result['summ_price'] = $summ_price;
        $result['rate_filter'] = $rate_filter;
        $result['is_multi_zip'] = $is_multi_zip;
        $result['to_order'] = $toOrder;

        return $result;
    }

}
