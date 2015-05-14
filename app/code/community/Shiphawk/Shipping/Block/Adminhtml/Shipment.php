<?php
class Shiphawk_Shipping_Block_Adminhtml_Shipment extends Mage_Core_Block_Template
{
    public function getNewShipHawkRate($order) {

        $carrier = Mage::getModel('shiphawk_shipping/carrier');
        $api = Mage::getModel('shiphawk_shipping/api');
        $helper = Mage::helper('shiphawk_shipping');

        $result = array();

        $items = $carrier->getShiphawkItems($order);

        $grouped_items_by_zip = $carrier->getGroupedItemsByZip($items);

        $error_message = 'Sorry, not all products have necessary ShipHawk fields filled in. Please add necessary data for next products (or check required attributes):';

        $shippingAddress = $order->getShippingAddress();
        $to_zip = $shippingAddress->getPostcode();

        $ship_responces = array();
        $toOrder= array();
        $api_error = false;
        $is_multi_zip = false;

        if(count($grouped_items_by_zip) > 1)  {
            $is_multi_zip = true;
        }

        $is_admin = $helper->checkIsAdmin();
        $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin);
        $carrier_type = Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');

        $result['error'] = '';
        //default origin zip code
        $from_zip = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');
        //foreach($grouped_items_by_zip as $from_zip=>$items_) {
        foreach($grouped_items_by_zip as $origin_id=>$items_) {

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
                    $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $items_, $rate_filter, $carrier_type);
                    $ship_responces[] = $responceObject;

                    if(is_object($responceObject)) {
                        $api_error = true;
                        Mage::log('ShipHawk response: ' . $responceObject->error, null, 'ShipHawk.log');
                        $result['error'] = 'ShipHawk response: ' . $responceObject->error;
                        return $result;
                    }else{
                        // if $rate_filter = 'best' then it is only one rate
                        if(($is_multi_zip)||($rate_filter == 'best')) {
                            Mage::getSingleton('core/session')->setMultiZipCode(true);
                            $toOrder[$responceObject[0]->id]['product_ids'] = $carrier->getProductIds($items_);
                            $toOrder[$responceObject[0]->id]['price'] = $responceObject[0]->summary->price;
                            $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->summary->service;
                            $toOrder[$responceObject[0]->id]['items'] = $items_;
                            $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                            $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                            $toOrder[$responceObject[0]->id]['carrier'] = $responceObject[0]->summary->carrier;
                        }else{
                            Mage::getSingleton('core/session')->setMultiZipCode(false);
                            foreach ($responceObject as $responce) {
                                $toOrder[$responce->id]['product_ids'] = $carrier->getProductIds($items_);
                                $toOrder[$responce->id]['price'] = $responce->summary->price;
                                $toOrder[$responce->id]['name'] = $responce->summary->service;
                                $toOrder[$responce->id]['items'] = $items_;
                                $toOrder[$responce->id]['from_zip'] = $from_zip;
                                $toOrder[$responce->id]['to_zip'] = $to_zip;
                                $toOrder[$responce->id]['carrier'] = $responce->summary->carrier;
                            }
                        }
                    }
                }else{
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
                        $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $items_per_product, $rate_filter, $carrier_type);
                        $ship_responces[] = $responceObject;

                        if(is_object($responceObject)) {
                            $api_error = true;

                            Mage::log('ShipHawk response: ' . $responceObject->error, null, 'ShipHawk.log');
                            $result['error'] = 'ShipHawk response: ' . $responceObject->error;
                            return $result;
                        }else{
                            // if $rate_filter = 'best' then it is only one rate
                            if(($is_multi_zip)||($rate_filter == 'best')) {
                                Mage::getSingleton('core/session')->setMultiZipCode(true);
                                $toOrder[$responceObject[0]->id]['product_ids'] = $this->getProductIds($items_per_product);
                                $toOrder[$responceObject[0]->id]['price'] = $responceObject[0]->summary->price;
                                $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->summary->service;
                                $toOrder[$responceObject[0]->id]['items'] = $items_per_product;
                                $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                                $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                                $toOrder[$responceObject[0]->id]['carrier'] = $responceObject[0]->summary->carrier;
                            }else{
                                Mage::getSingleton('core/session')->setMultiZipCode(false);
                                foreach ($responceObject as $responce) {
                                    $toOrder[$responce->id]['product_ids'] = $this->getProductIds($items_per_product);
                                    $toOrder[$responce->id]['price'] = $responce->summary->price;
                                    $toOrder[$responce->id]['name'] = $responce->summary->service;
                                    $toOrder[$responce->id]['items'] = $items_per_product;
                                    $toOrder[$responce->id]['from_zip'] = $from_zip;
                                    $toOrder[$responce->id]['to_zip'] = $to_zip;
                                    $toOrder[$responce->id]['carrier'] = $responce->summary->carrier;
                                }
                            }
                        }
                    }else{
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
        $name_service = '';
        $summ_price = 0;
        if(!$api_error) {
            $services = $carrier->getServices($ship_responces);

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
