<?php
class Shiphawk_Shipping_Model_Api extends Mage_Core_Model_Abstract
{
    public function buildShiphawkRequest($from_zip, $to_zip, $items, $rate_filter, $carrier_type, $location_type, $shLocationType){
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
            );
        }

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

    public function getShiphawkRate($from_zip, $to_zip, $items, $rate_filter, $carrier_type, $location_type, $shLocationType) {

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
            );
        }

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

    public function toBook($order, $rate_id, $products_ids, $accessories = array(), $is_auto = false, $self_packed, $is_rerate = null)
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
        $orderAccessories = $order->getShiphawkShippingAccessories();

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
        } else {
            $itemsAccessories = $this->getAccessoriesForBook($accessories, $orderAccessories);
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


        $helper->shlog($items_array, 'shiphawk-book.log');

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

            $shiphawk_rate_data = unserialize($order->getData('shiphawk_book_id')); //rate id

            foreach($shiphawk_rate_data as $rate_id=>$products_ids) {
                $shipment = $this->_initShipHawkShipment($order,$products_ids);

                $shipment->register();

                $this->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price']);

                $self_pack = $products_ids['self_pack'];

                // add book, auto booking - true
                $track_data = $this->toBook($order, $rate_id, $products_ids, array(), true, $self_pack);

                $helper->shlog($track_data, 'shiphawk-book-response.log');

                if (property_exists($track_data, 'error')) {
                    Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                    //Mage::getSingleton('core/session')->addError($track_data->error);
                    $helper->shlog('ShipHawk response: '.$track_data->error);
                    return;
                }

                // add track
                if($track_number = $track_data->details->id) {
                    $this->addTrackNumber($shipment, $track_number);
                    // subscribe automaticaly after book
                    $this->subscribeToTrackingInfo($shipment->getId());
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
                    $qty[$eachOrderItem->getId()] = 0;
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
    public function _saveShiphawkShipment($shipment, $shiphawk_shipment_title = null, $shiphawk_shipment_price = null)
    {
        $shipment->getOrder()->setIsInProcess(true);
        $shipment->setShiphawkShippingMethodTitle($shiphawk_shipment_title);
        $shipment->setShiphawkShippingPrice($shiphawk_shipment_price);
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

        return $arr_res;

    }

    /**
     * For book shipping with accessories
     *
     * @param $accessories
     * @param $orderAccessories
     * @return array
     *
     * @version 20150624, WhideGroup
     */
    public function getAccessoriesForBook($accessories, $orderAccessories) {

        if (empty($accessories) || empty($orderAccessories)) {
            return array();
        }

        $helper = Mage::helper('shiphawk_shipping');

        $orderAccessories = json_decode($orderAccessories, true);
        $itemsAccessories = array();

        foreach ($accessories as $accessoriesType => $accessor) {
            foreach($accessor as $accessorRow) {
                foreach($orderAccessories as $orderAccessoriesType => $orderAccessor) {
                    foreach($orderAccessor as $orderAccessorValues) {
                        $orderAccessorValuesName = str_replace("'", '', $orderAccessorValues['name']);
                        $orderAccessorValuesName = trim($orderAccessorValuesName);

                        $accessorName = (string)$accessorRow->accessorial_type . ' (' . (string)$accessorRow->accessorial_options . ')';
                        $accessorName = trim($accessorName);

                        if (str_replace("'", '', $orderAccessoriesType) == $accessoriesType && $accessorName == $orderAccessorValuesName) {
                            $itemsAccessories[] = array('id' => $accessorRow->id);
                        }
                    }
                }
            }
        }

        if (empty($itemsAccessories)) {

            $helper->shlog('Empty accessories!', 'shiphawk-book.log');

            $helper->shlog($accessories, 'shiphawk-book.log');

            $helper->shlog($orderAccessories, 'shiphawk-book.log');
        } else {

            $helper->shlog('Accessories!', 'shiphawk-book.log');

            $helper->shlog($itemsAccessories, 'shiphawk-book.log');
        }

        return $itemsAccessories;
    }

}