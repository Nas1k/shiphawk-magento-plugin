<?php
class Shiphawk_Shipping_Adminhtml_ShipmentController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Get rate and book shipments order, Manual booking
     *
     *
     * @return null
     */
    public function saveshipmentAction()
    {
        $orderId= $this->getRequest()->getParam('order_id');
        $sUrl = $this->getRequest()->getParam('sUrl');
        $response = array();
        $response['error_text'] = null;
        $response['order_id'] = null;
        $response['sUrl'] = null;

        try {
            $order = Mage::getModel('sales/order')->load($orderId);

            $shLocationType = $order->getShiphawkLocationType();

            $shiphawk_rate_data = unserialize($order->getData('shiphawk_book_id')); //rate id

            $api = Mage::getModel('shiphawk_shipping/api');
            $helper = Mage::helper('shiphawk_shipping');

            $items = Mage::getModel('shiphawk_shipping/carrier')->getShiphawkItems($order);

            $grouped_items_by_zip = Mage::getModel('shiphawk_shipping/carrier')->getGroupedItemsByZip($items);

            $shipping_description = $order->getShippingDescription();

            $is_multi_zip = (count($grouped_items_by_zip) > 1) ? true : false;
            $is_admin = $helper->checkIsAdmin();

            $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin, $order);

            //$carrier_type = Mage::getStoreConfig('carriers/shiphawk_shipping/carrier_type');

            if($is_multi_zip) {
                $rate_filter = 'best';
            }

            $accessories = array();

            foreach($shiphawk_rate_data as $rate_id=>$products_ids) {
                    $is_rate = false;

                    if(($is_multi_zip)||($rate_filter == 'best')) {
                        /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                        $from_zip = $products_ids['items'][0]['zip'];
                        $location_type = $products_ids['items'][0]['location_type'];

                        $carrier_type = $products_ids['carrier_type'];
                        $self_pack = $products_ids['self_pack'];

                        $responceObject = $api->getShiphawkRate($from_zip, $products_ids['to_zip'], $products_ids['items'], $rate_filter, $carrier_type, $location_type, $shLocationType);
                        // get only one method for each group of product
                        if(property_exists($responceObject, 'error')) {
                            if($responceObject->error) {
                                $shiphawk_error = $responceObject->error;
                                $helper->shlog('ShipHawk response: '. $shiphawk_error);
                                $helper->sendErrorMessageToShipHawk($shiphawk_error);
                                $is_rate = false;
                            }
                        }else{
                            $rate_id        = $responceObject->id;
                            $accessories    = $responceObject->shipping->carrier_accessorial;
                            $is_rate = true;
                        }

                    }else{
                        /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                        $from_zip = $products_ids['items'][0]['zip'];
                        $location_type = $products_ids['items'][0]['location_type'];

                        $carrier_type = $products_ids['carrier_type'];

                        $self_pack = $products_ids['self_pack'];

                        $responceObject = $api->getShiphawkRate($from_zip, $products_ids['to_zip'], $products_ids['items'], $rate_filter, $carrier_type, $location_type, $shLocationType);

                        $accessoriesPriceData = json_decode($order->getData('shiphawk_shipping_accessories'));
                        $accessoriesPrice = Mage::helper('shiphawk_shipping')->getAccessoriesPrice($accessoriesPriceData);
                        // ShipHawk Shipping Amount includes accessories price
                        $original_shipping_price = floatval($order->getShiphawkShippingAmount() - $accessoriesPrice);
                        foreach ($responceObject as $responce) {

                            // shipping rate price from new response
                            $shipping_price = $helper->getShipHawkPrice($responce, $self_pack);
                            if(round($original_shipping_price,3) == round($shipping_price,3)) {
                                $rate_id        = $responce->id;
                                $accessories    = $responce->shipping->carrier_accessorial;
                                $is_rate = true;
                                break;
                          }
                        }
                    }

                    if($is_rate == true) {
                        // add book
                        $track_data = $api->toBook($order, $rate_id, $products_ids, $accessories, false, $self_pack);

                        if (property_exists($track_data, 'error')) {
                            Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                            $helper->shlog('ShipHawk response: '.$track_data->error);
                            $helper->sendErrorMessageToShipHawk($track_data->error);
                            continue;
                        }

                        $shipment = $api->_initShipHawkShipment($order,$products_ids);
                        $shipment->register();
                        $api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price']);

                        // add track
                        if($track_data->details->id) {
                            $api->addTrackNumber($shipment, $track_data->details->id);

                            $api->subscribeToTrackingInfo($shipment->getId());
                        }

                        $shipmentCreatedMessage = $this->__('The shipment has been created.');
                        $this->_getSession()->addSuccess($shipmentCreatedMessage);
                    }else{
                        //Mage::getSingleton('core/session')->addError("Unfortunately the method that was chosen by a customer during checkout is currently unavailable. Please contact ShipHawk's customer service to manually book this shipment.");
                        Mage::getSingleton('core/session')->setErrorPriceText("Sorry, we can't find the rate identical to the one that this order has. Please select another rate:");

                        $response['error_text'] = "Sorry, we can't find the rate identical to the one that this order has. Please select another rate:";
                        $response['order_id'] = $orderId;
                        $response['sUrl'] = $sUrl;
                    }
            }

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            //$this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Cannot save shipment.'));
          //  $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
        }

        //$this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
        $this->getResponse()->setBody(json_encode($response));
    }

    /* Show PopUp for new ShipHawk Shipment */
    public function newshipmentAction()
    {
        $orderId= $this->getRequest()->getParam('order_id');

        try {
            $order = Mage::getModel('sales/order')->load($orderId);

            $this->loadLayout();

            $this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('shiphawk_shipping/adminhtml_shipment')->setTemplate('shiphawk/shipment.phtml')->setOrder($order));

            $this->renderLayout();

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());

        } catch (Exception $e) {
            Mage::logException($e);
        }

    }

    /**
     * Shipment booking in admin for order with no Shiphawk method or for missing rate shipments
     */
    public function newbookAction() {

        $params =  $this->getRequest()->getParams();

        $orderId = $params['order_id'];
        $shiphawk_rate_id = $params['shipping_method'];
        $is_multi = $params['is_multi'];
        if(array_key_exists('multi_price', $params) ) {
            $multi_price = $params['multi_price'];
        }

        $shipmentCreatedMessage = $this->__('Something went wrong');

        try {
            $order = Mage::getModel('sales/order')->load($orderId);
            $shiphawk_rate_data = Mage::getSingleton('core/session')->getData('new_shiphawk_book_id', true);
            $api = Mage::getModel('shiphawk_shipping/api');
            $helper = Mage::helper('shiphawk_shipping');

            foreach($shiphawk_rate_data as $rate_id=>$products_ids) {

                    // add book
                    if($is_multi == 0) {
                        if($shiphawk_rate_id == $rate_id) {
                            $self_pack = $products_ids['self_pack'];
                            $accessories = array();
                            /* For accessories */
                            $accessoriesPrice   = 0;
                            $accessoriesData    = array();
                            if(array_key_exists('accessories', $params)) {
                                $accessories = $params['accessories'];
                                if(!empty($accessories)) {
                                    foreach($accessories as $typeName => $type) {
                                        foreach($type as $name => $values) {
                                            foreach($values as $key => $value) {
                                                $accessoriesData[$typeName][$key]['name'] = $name;
                                                $accessoriesData[$typeName][$key]['value'] = (float)$value;

                                                $accessoriesPrice += (float)$value;
                                            }
                                        }
                                    }
                                }
                            }

                            $track_data = $api->toBook($order,$rate_id,$products_ids, $accessoriesData, true, $self_pack, true);

                            if (property_exists($track_data, 'error')) {
                                Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                $helper->shlog('ShipHawk response: '.$track_data->error);
                                continue;
                            }

                            $order->setShiphawkShippingAmount($products_ids['price'] + $accessoriesPrice); //resave shipping price
                            $order->setShiphawkShippingAccessories(json_encode($accessoriesData)); // resave accessories
                            $order->save();

                            $shipment = $api->_initShipHawkShipment($order,$products_ids);
                            $shipment->register();
                            $api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price']);

                            // add track
                            $track_number = $track_data->details->id;

                            $api->addTrackNumber($shipment, $track_number);
                            $api->subscribeToTrackingInfo($shipment->getId());

                            $shipmentCreatedMessage = $this->__('The shipment has been created.');
                            $this->_getSession()->addSuccess($shipmentCreatedMessage);
                        }
                    }else{
                        $self_pack = $products_ids['self_pack'];
                        $accessories = array();
                        if(array_key_exists('accessories', $params)) {
                            $accessories = $params['accessories'];
                        }
                        $track_data = $api->toBook($order,$rate_id,$products_ids, $accessories, false, $self_pack);

                        if (property_exists($track_data, 'error')) {
                            Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                            $helper->shlog('ShipHawk response: '.$track_data->error);
                            continue;
                        }

                        $order->setShiphawkShippingAmount($multi_price);
                        $order->save();

                        $shipment = $api->_initShipHawkShipment($order,$products_ids);
                        $shipment->register();
                        $api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price']);

                        // add track
                        $track_number = $track_data->details->id;

                        $api->addTrackNumber($shipment, $track_number);
                        $api->subscribeToTrackingInfo($shipment->getId());

                        $shipmentCreatedMessage = $this->__("The multi-origin shipment's has been created.");
                        $this->_getSession()->addSuccess($shipmentCreatedMessage);
                    }
            }

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());

        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Cannot save shipment.'));
        }

        $this->getResponse()->setBody( json_encode($shipmentCreatedMessage) );
    }

    /**
     * For set Shiphawk location type value to session
     *
     * @version 20150701
     */
    public function setlocationtypeAction() {
        $locationType = $this->getRequest()->getPost('location_type');

        if (empty($locationType)) {
            $this->getResponse()->setBody('Result: location type is empty.');
            return;
        }

        $locationType = $locationType != 'residential' && $locationType != 'commercial' ? 'residential' : $locationType;

        Mage::getSingleton('checkout/session')->setData('shiphawk_location_type_shipping', $locationType);

        $this->getResponse()->setBody('Result: ok.');
    }

    /**
     * Set accessories price for Update Totals button in admin (New order view)
     *
     * @version 20150701
     */
    public function setaccessoriespriceAction() {
        $params = $this->getRequest()->getParams();
        $accessories_price = $params['accessories_price'];
        $shiphawk_override_cost = $params['shiphawk_override_cost'];

        /*if (empty($accessories_price)) {
            $this->getResponse()->setBody('accessories price is empty.');
            return;
        }*/

        Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
        Mage::getSingleton('core/session')->unsetData('shiphawk_override_cost');

        Mage::getSingleton('core/session')->setData('admin_accessories_price', $accessories_price);
        Mage::getSingleton('core/session')->setData('shiphawk_override_cost', $shiphawk_override_cost);

        //$this->getResponse()->setBody('Result: ok.');
    }
}