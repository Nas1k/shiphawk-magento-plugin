<?php
class Shiphawk_Shipping_Model_Api extends Mage_Core_Model_Abstract
{
    public function buildShiphawkRequest($from_zip, $to_zip, $items, $rate_filter, $carrier_type, $location_type, $shLocationType, $destination_accessorials = null){
        $helper = Mage::helper('shiphawk_shipping');
        $api_key = $helper->getApiKey();
        //$url_api_rates = $helper->getApiUrl() . 'rates/full?api_key=' . $api_key;
        $url_api_rates = $helper->getApiUrl() . 'rates?api_key=' . $api_key;

        $curl = curl_init();

        if($carrier_type == '') {
            $items_array = array(
                'from_zip'=> $from_zip,
                'to_zip'=> $to_zip,
                'rate_filter' => $rate_filter,
                'items' => $items,
                'from_type' => $location_type,
                'to_type' => $shLocationType,
                'destination_accessorials' => $destination_accessorials,
            );
        }else{
            $items_array = array(
                'from_zip'=> $from_zip,
                'to_zip'=> $to_zip,
                'rate_filter' => $rate_filter,
                'carrier_type' => $carrier_type,
                'items' => $items,
                'from_type' => $location_type,
                'to_type' => $shLocationType,
                'destination_accessorials' => $destination_accessorials,
            );
        }

        $req_name = 'Request-rates' . $from_zip . '-' .$to_zip .'.log';
        $helper->shlog($items_array, $req_name);

        $items_array =  json_encode($items_array);

        curl_setopt($curl, CURLOPT_URL, $url_api_rates);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $items_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($items_array)
            )
        );

        return $curl;
    }

    public function getShiphawkRate($from_zip, $to_zip, $items, $rate_filter, $carrier_type, $location_type, $shLocationType, $destination_accessorials = null) {

        $helper = Mage::helper('shiphawk_shipping');
        $api_key = $helper->getApiKey();
        //$url_api_rates = $helper->getApiUrl() . 'rates/full?api_key=' . $api_key;
        $url_api_rates = $helper->getApiUrl() . 'rates?api_key=' . $api_key;

        $curl = curl_init();

        if($carrier_type == '') {
            $items_array = array(
                'from_zip'=> $from_zip,
                'to_zip'=> $to_zip,
                'rate_filter' => $rate_filter,
                'items' => $items,
                'from_type' => $location_type,
                'to_type' => $shLocationType,
                'destination_accessorials' => $destination_accessorials,
            );
        }else{
            $items_array = array(
                'from_zip'=> $from_zip,
                'to_zip'=> $to_zip,
                'rate_filter' => $rate_filter,
                'carrier_type' => $carrier_type,
                'items' => $items,
                'from_type' => $location_type,
                'to_type' => $shLocationType,
                'destination_accessorials' => $destination_accessorials,
            );
        }
        $req_name = 'Request-rates' . $from_zip . '-' .$to_zip .'.log';
        $helper->shlog($items_array, $req_name);

        $items_array =  json_encode($items_array);

        curl_setopt($curl, CURLOPT_URL, $url_api_rates);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $items_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($items_array)
            )
        );

        $resp = curl_exec($curl);

        $arr_res = json_decode($resp);

        curl_close($curl);
        return $arr_res;
    }

    public function toBook($order, $rate_id, $products_ids, $accessories = array(), $is_auto = false, $self_packed, $is_rerate = null, $multi_front = null)
    {
        $ship_addr = $order->getShippingAddress()->getData();
        $bill_addr = $order->getBillingAddress()->getData();
        $order_increment_id = $order->getIncrementId();
        $helper = Mage::helper('shiphawk_shipping');

        $api_key = Mage::helper('shiphawk_shipping')->getApiKey();
        $api_url = Mage::helper('shiphawk_shipping')->getApiUrl();
        $url_api = $api_url . 'shipments?api_key=' . $api_key;

        $self_packed = $self_packed ? 'true' : 'false';

        /* get shiphawk origin data from first product, because products are grouped by origin (or by zip code) and have same address */
        $origin_product = Mage::getModel('catalog/product')->load($products_ids['product_ids'][0]);
        $per_product = Mage::helper('shiphawk_shipping')->checkShipHawkOriginAttributes($origin_product);
        $origin_address_product = $this->_getProductOriginData($products_ids['product_ids'][0], $per_product);
        /* */

        $curl = curl_init();

        $default_origin_address = $this->_getDefaultOriginData();

        $order_email = $ship_addr['email'];

        if (Mage::getStoreConfig('carriers/shiphawk_shipping/order_received') == 1) {
            $administrator_email = Mage::getStoreConfig('carriers/shiphawk_shipping/administrator_email');
            $order_email = ($administrator_email) ? $administrator_email : $ship_addr['email'];
        }

        $origin_address = (empty($origin_address_product)) ? $default_origin_address : $origin_address_product;

        /* For accessories */

        if ($multi_front) {
            $itemsAccessories = $accessories; //accessories already grouped by carrier
        }else{
            // all accessories saved in order
            $orderAccessories = $order->getShiphawkShippingAccessories();

            $itemsAccessories = $this->getAccessoriesForBook($accessories, $orderAccessories);

            if ($is_auto) {
                $orderAccessories = json_decode($orderAccessories, true);
                if($is_rerate) {

                    foreach($accessories as $orderAccessoriesType => $orderAccessor) {
                        foreach($orderAccessor as $key => $orderAccessorValues) {
                            $itemsAccessories[] = array('id' => str_replace("'", '', $key));
                        }
                    }
                }else{
                    foreach($orderAccessories as $orderAccessoriesType => $orderAccessor) {
                        foreach($orderAccessor as $key => $orderAccessorValues) {
                            $itemsAccessories[] = array('id' => str_replace("'", '', $key));
                        }
                    }
                }
            }
        }

        $next_bussines_day = date('Y-m-d', strtotime('now +1 Weekday'));
        $items_array = array(
            'rate_id'=> $rate_id,
            'order_email'=> $order_email,
            'xid'=>$order_increment_id,
            'self_packed'=>$self_packed,
            'insurance'=>'true',
            'origin_address' =>
                $origin_address,
            'destination_address' =>
                array(
                    'first_name' => $ship_addr['firstname'],
                    'last_name' => $ship_addr['lastname'],
                    'street1' => $ship_addr['street'],
                    'phone_number' => $ship_addr['telephone'],
                    'city' => $ship_addr['city'],
                    'state' => $ship_addr['region'],
                    'zip' => $ship_addr['postcode'],
                    'email' => $ship_addr['email']
                ),
            'billing_address' =>
                array(
                    'first_name' => $bill_addr['firstname'],
                    'last_name' => $bill_addr['lastname'],
                    'street1' => $bill_addr['street'],
                    'phone_number' => $bill_addr['telephone'],
                    'city' => $bill_addr['city'],
                    'state' => $bill_addr['region'], //'NY',
                    'zip' => $bill_addr['postcode'],
                    'email' => $bill_addr['email']
                ),
            'pickup' =>
                array(
                    array(
                        'start_time' => $next_bussines_day.'T04:00:00.645-07:00',
                        'end_time' => $next_bussines_day.'T20:00:00.645-07:00',
                    ),
                    array(
                        'start_time' => $next_bussines_day.'T04:00:00.645-07:00',
                        'end_time' => $next_bussines_day.'T20:00:00.646-07:00',
                    )
                ),

            'accessorials' => $itemsAccessories

        );


        $helper->shlog($items_array, 'shiphawk-book-request.log');

        $items_array =  json_encode($items_array);

        curl_setopt($curl, CURLOPT_URL, $url_api);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $items_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($items_array)
            )
        );

        $resp = curl_exec($curl);
        $arr_res = json_decode($resp);

        //$helper->shlog($arr_res, 'shiphawk-book.log');

        curl_close($curl);

        return $arr_res;

    }

    protected function _getDefaultOriginData() {
        $origin_address = array();

        $origin_address['first_name'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_first_name');
        $origin_address['last_name'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_last_name');
        $origin_address['street1'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_address');
        $origin_address['street2'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_address2');
        $origin_address['state'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_state');
        $origin_address['city'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_city');
        $origin_address['zip'] = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');
        $origin_address['phone_number'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_phone');
        $origin_address['email'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_email');

        return $origin_address;
    }

    protected function _getProductOriginData($products_id, $per_product = false) {
        $origin_address_product = array();

        try
        {
            // get first product item
            $origin_product = Mage::getModel('catalog/product')->load($products_id);

            $shipping_origin_id = $origin_product->getData('shiphawk_shipping_origins');
            $helper = Mage::helper('shiphawk_shipping');


            /* if product origin id == default (origin id == '') and product have all per product origin attribute
            than get origin address from first product in origin group */
            if($per_product == true) {

                $origin_address_product['first_name'] = $origin_product->getData('shiphawk_origin_firstname');
                $origin_address_product['last_name'] = $origin_product->getData('shiphawk_origin_lastname');
                $origin_address_product['street1'] = $origin_product->getData('shiphawk_origin_addressline1');
                $origin_address_product['street2'] = $origin_product->getData('shiphawk_origin_addressline2');
                $origin_address_product['state'] = $origin_product->getData('shiphawk_origin_state');
                $origin_address_product['city'] = $origin_product->getData('shiphawk_origin_city');
                $origin_address_product['zip'] = $origin_product->getData('shiphawk_origin_zipcode');
                $origin_address_product['phone_number'] = $origin_product->getData('shiphawk_origin_phonenum');
                $origin_address_product['email'] = $origin_product->getData('shiphawk_origin_email');
            }else{
                if($shipping_origin_id) {
                    /* if product have origin id, then get origin address from origin model */
                    $shipping_origin = Mage::getModel('shiphawk_shipping/origins')->load($shipping_origin_id);

                    $origin_address_product['first_name'] = $shipping_origin->getData('shiphawk_origin_firstname');
                    $origin_address_product['last_name'] = $shipping_origin->getData('shiphawk_origin_lastname');
                    $origin_address_product['street1'] = $shipping_origin->getData('shiphawk_origin_addressline1');
                    $origin_address_product['street2'] = $shipping_origin->getData('shiphawk_origin_addressline2');
                    $origin_address_product['state'] = $shipping_origin->getData('shiphawk_origin_state');
                    $origin_address_product['city'] = $shipping_origin->getData('shiphawk_origin_city');
                    $origin_address_product['zip'] = $shipping_origin->getData('shiphawk_origin_zipcode');
                    $origin_address_product['phone_number'] = $shipping_origin->getData('shiphawk_origin_phonenum');
                    $origin_address_product['email'] = $shipping_origin->getData('shiphawk_origin_email');

                }
            }

        }
        catch(Exception $e)
        {
            Mage::log($e->getMessage());
        }

        return $origin_address_product;
    }

    protected function getOriginAddress($origin_address_product, $default_origin_address) {


        foreach($origin_address_product as $key=>$value) {

            if($key != 'origin_address2') {
                if(empty($value)) {
                    return $default_origin_address;
                }
            }
        }

        return $origin_address_product;

    }


    /**
     * Auto booking. Save shipment in sales_order_place_after event, if manual booking set to No
     * We can save only new shipment. Existing shipments are not editable
     *
     *
     */
    public function saveshipment($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->load($orderId);
            $helper = Mage::helper('shiphawk_shipping');
            $api = Mage::getModel('shiphawk_shipping/api');

            $shLocationType = $order->getShiphawkLocationType();

            $shiphawk_rate_data = unserialize($order->getData('shiphawk_book_id')); //rate id
            $shiphawk_multi_shipping = unserialize($order->getData('shiphawk_multi_shipping')); // rates grouped by items
            $chosen_shipping_methods = unserialize($order->getChosenMultiShippingMethods()); // get chosen multi shipping rates

            $carrier_model = Mage::getModel('shiphawk_shipping/carrier');
            $is_multi_zip = (count($shiphawk_multi_shipping) > 1) ? true : false;

            $is_admin = $helper->checkIsAdmin();
            $is_backend_order = $helper->checkIfOrderIsBackend($order);
            $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin, $order);

            $accessories = array();
            $multi_front = null;

            $pre_accessories = null;

            $accessories_for_rates = unserialize($order->getShiphawkCustomerAccessorials());
            //$accessories_per_carriers = unserialize($order->getChosenAccessoriesPerCarrier());

            $orderAccessories = json_decode($order->getShiphawkShippingAccessories());

            if (!empty($accessories_for_rates))
                foreach ($accessories_for_rates as $access_rate) {
                    $pre_accessories[$access_rate] = 'true';
                }

            if($is_multi_zip === true) {
                foreach($shiphawk_multi_shipping as $pr_ids=>$rates_data) {
                    $is_rate = false;
                    $from_zip = $rates_data[0]['from_zip'];
                    $to_zip = $rates_data[0]['to_zip'];
                    $location_type = $rates_data[0]['items'][0]['location_type'];
                    $carrier_type = $rates_data[0]['carrier_type'];
                    $self_pack = $rates_data[0]['self_pack'];
                    $disabled = $rates_data[0]['shiphawk_disabled'];

                    if(!$disabled) {
                        $responseObject = $this->getShiphawkRate($from_zip, $to_zip, $rates_data[0]['items'], $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories);

                        if(is_object($responseObject))
                            if (property_exists($responseObject, 'error')) {
                                $helper->shlog('ShipHawk response: '.$responseObject->error);
                                $helper->sendErrorMessageToShipHawk($responseObject->error);
                                continue;
                            };

                        foreach ($responseObject as $response) {
                            $shipping_price = (string) $helper->getShipHawkPrice($response, $self_pack);
                            if($is_backend_order) {
                                $shipping_price = round ($helper->getShipHawkPrice($response, $self_pack),2);
                                foreach ($shiphawk_rate_data as $rate_data) {
                                    if(round ($rate_data['price'], 2) == $shipping_price) {
                                        $rate_id        = $response->id;
                                        //accessories    = $response->shipping->carrier_accessorial; // no accessorials for backend multi parcel order
                                        $rate_name      = $carrier_model->_getServiceName($response);
                                        $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
                                        $package_info    = Mage::getModel('shiphawk_shipping/carrier')->getPackeges($response);
                                        $is_rate = true;
                                        break 2;
                                    }
                                }
                            }else{
                                foreach($chosen_shipping_methods as $shipping_code) {
                                    if($helper->getOriginalShipHawkShippingPrice($shipping_code, $shipping_price)) {
                                        $rate_id        = $response->id;
                                        $accessories_from_rate    = $response->shipping->carrier_accessorial;
                                        //$accessories = $api->getAccessoriesForBookMultiParcel($shipping_price, $accessories_per_carriers, $accessories_from_rate);
                                        $accessories = $api->getAccessoriesForBook($accessories_from_rate, $orderAccessories->$shipping_code); // return array with accessories and price of this accessorials
                                        $rate_name      = $carrier_model->_getServiceName($response);
                                        $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
                                        $package_info    = Mage::getModel('shiphawk_shipping/carrier')->getPackeges($response);
                                        $is_rate = true;
                                        $multi_front = true;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if($is_rate == true) {
                            // add book
                            if($accessories['accessories']) {
                                $track_data = $api->toBook($order, $rate_id, $rates_data[0], $accessories['accessories'], false, $self_pack, null, $multi_front);
                            }else{
                                $track_data = $api->toBook($order, $rate_id, $rates_data[0], array(), false, $self_pack, null, $multi_front);
                            }

                            $accessoriesPrice = $accessories['price'];
                            if (property_exists($track_data, 'error')) {
                                Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                $helper->shlog('ShipHawk response: '.$track_data->error);
                                $helper->sendErrorMessageToShipHawk($track_data->error);
                                continue;
                            }

                            $shipment = $this->_initShipHawkShipment($order,$rates_data[0]);
                            $shipment->register();
                            $shippingShipHawkAmount = $shipping_price + $accessoriesPrice;
                            $this->_saveShiphawkShipment($shipment, $rate_name, $shippingShipHawkAmount, $package_info, $track_data->details->id);

                            // add track
                            if($track_data->details->id) {
                                $this->addTrackNumber($shipment, $track_data->details->id);
                                $this->subscribeToTrackingInfo($shipment->getId());
                            }
                        }else{
                            Mage::getSingleton('core/session')->addError("Unfortunately the method that was chosen by a customer during checkout is currently unavailable. Please contact ShipHawk's customer service to manually book this shipment.");
                            Mage::getSingleton('core/session')->setErrorPriceText("Sorry, we can't find the rate identical to the one that this order has. Please select another rate:");
                        }
                    }else{
                        // no booking for disabled items, save only magento shipping
                        $shipment = $this->_initShipHawkShipment($order,$rates_data[0]);
                        $shipment->register();
                        $shippingShipHawkAmount = $rates_data[0]['price'];
                        $this->_saveShiphawkShipment($shipment, $rates_data[0]['name'], $shippingShipHawkAmount, '',null);
                    }
                }
            }else {//single parcel
                foreach ($shiphawk_rate_data as $rate_id => $products_ids) {

                    //package info for single parcel shipment, saved early in order place after event
                    $package_info    = $order->getShiphawkShippingPackageInfo();
                    $disabled = $products_ids['shiphawk_disabled'];

                    $accessoriesPrice = Mage::helper('shiphawk_shipping')->getAccessoriesPrice($orderAccessories);
                    $shippingShipHawkAmount = $products_ids['price'] + $accessoriesPrice;

                    $accessories = $this->getAccessoriesForAutoBookSingleParcel($orderAccessories); // already chose accessories id

                    $self_pack = $products_ids['self_pack'];

                    $shipment = $this->_initShipHawkShipment($order, $products_ids);
                    $shipment->register();

                    if(!$disabled) {
                        $track_data = $api->toBook($order, $rate_id, $products_ids, $accessories, false, $self_pack, null, $multi_front = true);

                        $this->_saveShiphawkShipment($shipment, $products_ids['name'], $shippingShipHawkAmount, $package_info, $track_data->details->id);

                        $helper->shlog($track_data, 'shiphawk-book-response.log');

                        if (property_exists($track_data, 'error')) {
                            Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                            $helper->shlog('ShipHawk response: ' . $track_data->error);
                            return;
                        }

                        // add track
                        if ($track_number = $track_data->details->id) {
                            $this->addTrackNumber($shipment, $track_number);
                            // subscribe automatic after booking
                            $this->subscribeToTrackingInfo($shipment->getId());
                        }

                    }else{
                        // no booking for backup ShipHawk method
                        $this->_saveShiphawkShipment($shipment, $products_ids['name'], $shippingShipHawkAmount, $package_info, null);
                    }
                }
            }

        } catch (Mage_Core_Exception $e) {

            Mage::logException($e);

        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Initialize shipment model instance
     *
     * @return Mage_Sales_Model_Order_Shipment|bool
     */
    public function _initShipHawkShipment($order, $products_ids)
    {
        $shipment = false;
        if(is_object($order)) {

            $savedQtys = $this->_getItems($order, $products_ids);
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);
        }

        return $shipment;
    }

    public function _getItems($order, $products_ids) {

        $qty = array();
        if(is_object($order)) {
            foreach($order->getAllItems() as $eachOrderItem){

                if(in_array($eachOrderItem->getProductId(),$products_ids['product_ids'])) {
                    $Itemqty = 0;
                    $Itemqty = $eachOrderItem->getQtyOrdered()
                        - $eachOrderItem->getQtyShipped()
                        - $eachOrderItem->getQtyRefunded()
                        - $eachOrderItem->getQtyCanceled();
                    $qty[$eachOrderItem->getId()] = $Itemqty;
                }else{
                    // if type = bundle or configurable
                    // //get child product IDs
                    if(($eachOrderItem->getProductType() == 'bundle') || ($eachOrderItem->getProductType() == 'configurable')) {
                        $bundle_product = Mage::getModel('catalog/product')->load($eachOrderItem->getProductId());
                        $children_ids_by_option = $bundle_product->getTypeInstance($bundle_product)->getChildrenIds($bundle_product->getId(),false);
                            foreach($children_ids_by_option as $ids) {
                                foreach ($ids as $id) {
                                    if(in_array($id, $products_ids['product_ids'])){
                                        $Itemqty = $eachOrderItem->getQtyOrdered()
                                            - $eachOrderItem->getQtyShipped()
                                            - $eachOrderItem->getQtyRefunded()
                                            - $eachOrderItem->getQtyCanceled();
                                        $qty[$eachOrderItem->getId()] = $Itemqty;
                                        break 2;
                                    }
                                }

                            }
                    }else{
                        $qty[$eachOrderItem->getId()] = 0;
                    }
                }
            }
        }

        return $qty;
    }

    /**
     * Save shipment and order in one transaction
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return Mage_Adminhtml_Sales_Order_ShipmentController
     */
    public function _saveShiphawkShipment($shipment, $shiphawk_shipment_title = null, $shiphawk_shipment_price = null, $shiphawk_package_info = null, $shipment_id = null)
    {
        $shipment_id = (integer) $shipment_id;
        $shipment->getOrder()->setIsInProcess(true);
        $shipment->setShiphawkShippingMethodTitle($shiphawk_shipment_title);
        $shipment->setShiphawkShippingPrice($shiphawk_shipment_price);
        $shipment->setShiphawkPackageInfo($shiphawk_package_info);
        $shipment->setShiphawkShippingShipmentId($shipment_id);
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        return $this;
    }

    /**
     * Add new tracking number action
     */
    public function addTrackNumber($shipment, $number)
    {
        try {
            $carrier = 'shiphawk_shipping';
            $helper = Mage::helper('shiphawk_shipping');
            $title  = 'ShipHawk Shipping';
            if (empty($carrier)) {
                Mage::throwException($this->__('The carrier needs to be specified.'));
            }
            if (empty($number)) {
                Mage::throwException($this->__('Tracking number cannot be empty.'));
            }

            if ($shipment) {
                $track = Mage::getModel('sales/order_shipment_track')
                    ->setNumber($number)
                    ->setCarrierCode($carrier)
                    ->setTitle($title);
                $shipment->addTrack($track)
                    ->save();

            } else {
                $helper->shlog('Cannot initialize shipment for adding tracking number.', 'shiphawk-tracking.log');
            }
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage());
        } catch (Exception $e) {
            $helper = Mage::helper('shiphawk_shipping');
            $helper->shlog('Cannot add tracking number.', 'shiphawk-tracking.log');
        }

    }

    public function subscribeToTrackingInfo($shipment_id) {

        $helper = Mage::helper('shiphawk_shipping');
        $api_key = $helper->getApiKey();

        if($shipment_id) {
            try{
                $shipment = Mage::getModel('sales/order_shipment')->load($shipment_id);

                $shipment_id_track = $this->_getTrackNumber($shipment);

                //PUT /api/v3/shipments/{id}/tracking
                $subscribe_url = $helper->getApiUrl() . 'shipments/' . $shipment_id_track . '/tracking?api_key=' . $api_key;
                $callback_url = $helper->getCallbackUrl($api_key);

                $items_array = array(
                    'callback_url'=> $callback_url
                );

                $curl = curl_init();
                $items_array =  json_encode($items_array);

                curl_setopt($curl, CURLOPT_URL, $subscribe_url);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($curl, CURLOPT_POSTFIELDS, $items_array);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($items_array)
                    )
                );

                $resp = curl_exec($curl);
                $arr_res = json_decode($resp);

                $helper->shlog($arr_res, 'shiphawk-tracking.log');

                if (!empty($arr_res)) {
                    $comment = '';
                    $event_list = '';

                    if (count($arr_res->events)) {

                        foreach ($arr_res->events as $event) {
                            $event_list .= $event . '<br>';
                        }
                    }

                    try {

                        $crated_time = $this->convertDateTome($arr_res->created_at);

                        $comment = $arr_res->resource_name . ': ' . $arr_res->id  . '<br>' . 'Created: ' . $crated_time['date'] . ' at ' . $crated_time['time'] . '<br>' . $event_list;
                        $shipment->addComment($comment);
                        $shipment->sendEmail(true,$comment);

                    }catch  (Mage_Core_Exception $e) {
                        Mage::logException($e);
                    }

                }

                $shipment->save();

                curl_close($curl);

            }catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                Mage::logException($e);

            } catch (Exception $e) {
                Mage::logException($e);

            }

        }else{

            Mage::logException($this->__('No ShipHawk tracking number'));
        }

    }

    public function convertDateTome ($date_time) {
        $result = array();
        $t = explode('T', $date_time);
        $result['date'] = date("m/d/y", strtotime($t[0]));

        $result['time'] = date("g:i a", strtotime(substr($t[1], 0, -1)));

        return $result;
    }

    protected function _getTrackNumber($shipment) {

        foreach($shipment->getAllTracks() as $tracknum)
        {
            //ShipHawk track number only one
            if($tracknum->getCarrierCode() == 'shiphawk_shipping') {
                return $tracknum->getNumber();
            }
        }
        return null;
    }

    public function getShipmentStatus($shipment_id_track) {

        $helper = Mage::helper('shiphawk_shipping');
        $api_key = $helper->getApiKey();

        $subscribe_url = $helper->getApiUrl() . 'shipments/' . $shipment_id_track . '/tracking?api_key=' . $api_key;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $subscribe_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $resp = curl_exec($curl);
        $arr_res = json_decode($resp);
        curl_close($curl);
        return $arr_res;

    }

    /**
     * For book shipping with accessories
     *
     * @param $accessories - accessorials from rate
     * @param $orderAccessories - chosen accessorials in order
     * @return array
     *
     * @version 20150624, WhideGroup
     */
    public function getAccessoriesForBook($accessories, $orderAccessories) {

        if (empty($accessories) || empty($orderAccessories)) {
            return array();
        }

        $helper = Mage::helper('shiphawk_shipping');

        $result = array();
        $itemsAccessories = array();
        $price = 0;
        foreach ($accessories as $accessoriesType => $accessor) {  // $accessoriesType - origin, destination
            foreach($accessor as $accessorRow) { // $accessorRow - id, side, price, accessorial_type, accessorial_options, default
                foreach($orderAccessories as $orderAccessoriesType=>$orderAccValues) { //

                        foreach ($orderAccValues as $orderAccData) {
                            $orderAccessorValuesName = str_replace("'", '', $orderAccData->name);
                            $orderAccessorValuesName = trim($orderAccessorValuesName);

                            $accessorName = (string)$accessorRow->accessorial_type . ' (' . (string)$accessorRow->accessorial_options . ')';
                            $accessorName = trim($accessorName);

                            /*if (str_replace("'", '', $orderAccessoriesType) == $accessoriesType && $accessorName == $orderAccessorValuesName && (round($accessorRow->price,2) == round($orderAccData->value,2))) {*/
                            if (str_replace("'", '', $orderAccessoriesType) == $accessoriesType && $accessorName == $orderAccessorValuesName) {
                                $itemsAccessories[] = array('id' => $accessorRow->id);
                                $price += $accessorRow->price;
                            }
                        }

                }
            }
        }

        $result['accessories'] = $itemsAccessories;
        $result['price'] = $price;

        if (empty($itemsAccessories)) {

            $helper->shlog('Empty accessories!', 'shiphawk-book.log');

            $helper->shlog($accessories, 'shiphawk-book.log');

            $helper->shlog($orderAccessories, 'shiphawk-book.log');
        } else {

            $helper->shlog('Accessories!', 'shiphawk-book.log');

            $helper->shlog($itemsAccessories, 'shiphawk-book.log');
        }

        return $result;
    }

    public function getAccessoriesForAutoBookSingleParcel($accessoriesPriceData) {
        $itemsAccessories = array();

        if(!empty($accessoriesPriceData)) {
            foreach($accessoriesPriceData as $rate_code => $types) {
                foreach($types as $type => $values) {
                    foreach($values as $key => $data) {

                        $itemsAccessories[] = array('id' => trim($data->id, "'"));
                    }
                }
            }
        }

        return $itemsAccessories;
    }

    public function getDataForTateRequest() {

    }

}