<?php
class Shiphawk_Shipping_Model_Api extends Mage_Core_Model_Abstract
{
    public function getShiphawkRate($from_zip, $to_zip, $items, $rate_filter) {

        $helper = Mage::helper('shiphawk_shipping');
        $api_key = $helper->getApiKey();
        //$url_api_rates = $helper->getApiUrl() . 'rates/full?api_key=' . $api_key;
        $url_api_rates = $helper->getApiUrl() . 'rates/standard?api_key=' . $api_key;

        $from_type  = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_location_type');
        //$rate_filter = $helper->getRateFilter();
        $curl = curl_init();

        //TODO if products has various Origin Location Type ?
        $items_array = array(
            'from_zip'=> $from_zip,
            'to_zip'=> $to_zip,
            'rate_filter' => $rate_filter,
            'items' => $items,
            'from_type' => $from_type
        );

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

    public function toBook($order,$rate_id,$products_ids)
    {
        $ship_addr = array();
        $bill_addr = array();

        if(is_object($order)) {
            $ship_addr = $order->getShippingAddress()->getData();
            $bill_addr = $order->getBillingAddress()->getData();
            $order_increment_id = $order->getIncrementId();
        }

        $api_key = Mage::helper('shiphawk_shipping')->getApiKey();
        $api_url = Mage::helper('shiphawk_shipping')->getApiUrl();
        $url_api = $api_url . 'shipments/book?api_key=' . $api_key;

        /* */
        $origin_address_product = $this->_getProductOriginData($products_ids['product_ids'][0]);
        /* */

        $curl = curl_init();

        $origin_address = $this->_getOriginData();

        $order_email = $ship_addr['email'];

        if (Mage::getStoreConfig('carriers/shiphawk_shipping/order_received') == 1) {
            $administrator_email = Mage::getStoreConfig('carriers/shiphawk_shipping/administrator_email');
            $order_email = ($administrator_email) ? $administrator_email : $ship_addr['email'];
        }

        $next_bussines_day = date('Y-m-d', strtotime('now +1 Weekday'));
        $items_array = array(
            'rate_id'=> $rate_id,
            'order_email'=> $order_email,
            'xid'=>$order_increment_id,
            'origin_address' =>
                array(
                    'first_name' => $origin_address_product['origin_first_name'] ? $origin_address_product['origin_first_name'] : $origin_address['origin_first_name'],
                    'last_name' => $origin_address_product['origin_last_name'] ? $origin_address_product['origin_last_name'] : $origin_address['origin_last_name'],
                    'address_line_1' => $origin_address_product['origin_address'] ? $origin_address_product['origin_address'] : $origin_address['origin_address'],
                    'address_line_2' => $origin_address_product['origin_address2'] ? $origin_address_product['origin_address2'] : $origin_address['origin_address2'],
                    'phone_num' => $origin_address_product['origin_phone'] ? $origin_address_product['origin_phone'] : $origin_address['origin_phone'],
                    'city' => $origin_address_product['origin_city'] ? $origin_address_product['origin_city'] : $origin_address['origin_city'],
                    'state' => $origin_address_product['origin_state'] ? $origin_address_product['origin_state'] : $origin_address['origin_state'],
                    'zipcode' => $origin_address_product['default_origin_zip'] ? $origin_address_product['default_origin_zip'] : $origin_address['default_origin_zip']
                ),
            'destination_address' =>
                array(
                    'first_name' => $ship_addr['firstname'],
                    'last_name' => $ship_addr['lastname'],
                    'address_line_1' => $ship_addr['street'],
                    'phone_num' => $ship_addr['telephone'],
                    'city' => $ship_addr['city'],
                    'state' => $ship_addr['region'],
                    'zipcode' => $ship_addr['postcode']
                ),
            'billing_address' =>
                array(
                    'first_name' => $bill_addr['firstname'],
                    'last_name' => $bill_addr['lastname'],
                    'address_line_1' => $bill_addr['street'],
                    'phone_num' => $bill_addr['telephone'],
                    'city' => $bill_addr['city'],
                    'state' => $bill_addr['region'], //'NY',
                    'zipcode' => $bill_addr['postcode']
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

            'accessorials' => array()

        );

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

        curl_close($curl);

        return $arr_res;

    }

    protected function _getOriginData() {
        $origin_address = array();

        $origin_address['origin_first_name'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_first_name');
        $origin_address['origin_last_name'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_last_name');
        $origin_address['origin_address'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_address');
        $origin_address['origin_address2'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_address2');
        $origin_address['origin_state'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_state');
        $origin_address['origin_city'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_city');
        $origin_address['default_origin_zip'] = Mage::getStoreConfig('carriers/shiphawk_shipping/default_origin');
        $origin_address['origin_phone'] = Mage::getStoreConfig('carriers/shiphawk_shipping/origin_phone');

        return $origin_address;
    }

    protected function _getProductOriginData($products_id) {
        $origin_address_product = array();

        try
        {
            //$order_items = $order->getAllItems();
            // get first product item
            $origin_product = Mage::getModel('catalog/product')->load($products_id);

            $shipping_origin_id = $origin_product->getData('shiphawk_shipping_origins');

            $shipping_origin = Mage::getModel('shiphawk_shipping/origins')->load($shipping_origin_id);

            $origin_address_product['origin_first_name'] = $shipping_origin->getData('shiphawk_origin_firstname');
            $origin_address_product['origin_last_name'] = $shipping_origin->getData('shiphawk_origin_lastname');
            $origin_address_product['origin_address'] = $shipping_origin->getData('shiphawk_origin_addressline1');
            $origin_address_product['origin_address2'] = $shipping_origin->getData('shiphawk_origin_addressline2');
            $origin_address_product['origin_state'] = $shipping_origin->getData('shiphawk_origin_state');
            $origin_address_product['origin_city'] = $shipping_origin->getData('shiphawk_origin_city');
            $origin_address_product['default_origin_zip'] = $shipping_origin->getData('shiphawk_origin_zipcode');
            $origin_address_product['origin_phone'] = $shipping_origin->getData('shiphawk_origin_phonenum');
            //todo shiphawk_origin_location ?
        }
        catch(Exception $e)
        {
         Mage::log($e->getMessage());
        }

        return $origin_address_product;
    }

    /**
     * Save shipment
     * We can save only new shipment. Existing shipments are not editable
     *
     * @return null
     */
    public function saveshipment($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->load($orderId);

            $shiphawk_rate_data = unserialize($order->getData('shiphawk_book_id')); //rate id

            foreach($shiphawk_rate_data as $rate_id=>$products_ids) {
                $shipment = $this->_initShipHawkShipment($order,$products_ids);

                $shipment->register();

                $this->_saveShiphawkShipment($shipment);

                // add book

                $track_data = $this->toBook($order,$rate_id,$products_ids);

                // add track
                if($track_number = $track_data->shipment_id) {
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
    public function _saveShiphawkShipment($shipment)
    {
        $shipment->getOrder()->setIsInProcess(true);
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
                Mage::log('Cannot initialize shipment for adding tracking number.');
            }
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage());
        } catch (Exception $e) {
            Mage::log('Cannot add tracking number.');
        }

    }

    public function subscribeToTrackingInfo($shipment_id) {

        $helper = Mage::helper('shiphawk_shipping');
        $api_key = $helper->getApiKey();

        if($shipment_id) {
            try{
                $shipment = Mage::getModel('sales/order_shipment')->load($shipment_id);

                $shipment_id_track = $this->_getTrackNumber($shipment);

                $subscribe_url = $helper->getApiUrl() . 'shipments/' . $shipment_id_track . '/subscribe?api_key=' . $api_key;
                $callback_url = $helper->getCallbackUrl($api_key);

                $items_array = array(
                    'callback_url'=> $callback_url
                );

                $curl = curl_init();
                $items_array =  json_encode($items_array);

                curl_setopt($curl, CURLOPT_URL, $subscribe_url);
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

                if (!empty($arr_res)) {
                    $comment = '';
                    $event_list = '';
                    //$shipment->addComment($resp);
                    if (count($arr_res->events)) {

                        foreach ($arr_res->events as $event) {
                            $event_list .= $event . '<br>';
                        }
                    }

                    try {
                        Mage::log($arr_res, null, 'tracking.log');
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
        ///2015-04-01T15:57:42Z
        //todo check time
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

    public function getShipmentStatus($shipment_id) {
        //curl -X POST -H Content-Type:application/json -d '{"event":"shipment.status_update","status":"in_transit","updated_at":"2015-01-14T10:43:16.702-08:00","shipment_id":1015985}' http://shiphawk.devigor.wdgtest.com/index.php/shiphawk/index/tracking?api_key=e1919f54fb93f63866f06049d6d45751

        //GET /api/v1/shipments/{shipment_id}/status

    }


}