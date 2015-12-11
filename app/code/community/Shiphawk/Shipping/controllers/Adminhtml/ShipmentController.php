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

            $shiphawk_rate_data = unserialize($order->getData('shiphawk_book_id')); // rate id
            if (empty($shiphawk_rate_data)) {
                Mage::getSingleton('core/session')->addError("Unfortunately the method that was chosen by a customer during checkout is currently unavailable. Please contact ShipHawk's customer service to manually book this shipment.");
                $response['error_text'] = "Unfortunately the method that was chosen by a customer during checkout is currently unavailable. Please contact ShipHawk's customer service to manually book this shipment.";
                $this->getResponse()->setBody(json_encode($response));
                throw new Exception($response['error_text']);
            }

            $shiphawk_multi_shipping = unserialize($order->getData('shiphawk_multi_shipping')); // rates grouped by items
            $chosen_shipping_methods = unserialize($order->getChosenMultiShippingMethods()); // get chosen multi shipping rates empty for admin order ?

            $api = Mage::getModel('shiphawk_shipping/api');
            $helper = Mage::helper('shiphawk_shipping');
            $carrier_model = Mage::getModel('shiphawk_shipping/carrier');

            $is_multi_zip = (count($shiphawk_multi_shipping) > 1) ? true : false;
            $is_admin = $helper->checkIsAdmin();
            $is_backend_order = $helper->checkIfOrderIsBackend($order);
            $multi_front = null;

            $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter($is_admin, $order);

            $pre_accessories = null;

            $accessories_for_rates = unserialize($order->getShiphawkCustomerAccessorials());

            $orderAccessories = json_decode($order->getShiphawkShippingAccessories());

            if (!empty($accessories_for_rates))
            foreach ($accessories_for_rates as $access_rate) {
                $pre_accessories[$access_rate] = 'true';
            }

            $accessories = array();

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
                            $responseObject = $api->getShiphawkRate($from_zip, $to_zip, $rates_data[0]['items'], $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories);

                            if(is_object($responseObject))
                                if (property_exists($responseObject, 'error')) {
                                    Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                    $helper->shlog('ShipHawk response: '.$responseObject->error);
                                    $helper->sendErrorMessageToShipHawk($responseObject->error);
                                    $response['error_text'] = "Sorry, we can't find the rate identical to the one that this order has. Please select another rate:";
                                    $response['order_id'] = $orderId;
                                    $response['sUrl'] = $sUrl;
                                    continue;
                                };

                            $accessories['accessories'] = null;

                            if($is_backend_order) {
                                $cheapest_rate_id = $carrier_model->getCheapestRateIdForBooking($responseObject, $self_pack);
                                foreach ($responseObject as $response) {
                                    if($response->id == $cheapest_rate_id) {
                                        $rate_id        = $response->id;
                                        $accessories_from_rate    = $response->shipping->carrier_accessorial;
                                        if(!empty($accessories_from_rate))
                                        $accessories = $api->getDefaultAccessoriesForBook($accessories_from_rate); // return array with accessories and price of this accessorials
                                        $rate_name      = $carrier_model->_getServiceName($response);
                                        $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
                                        $package_info    = Mage::getModel('shiphawk_shipping/carrier')->getPackeges($response);
                                        $is_rate = true;
                                        $multi_front = true;//todo remove from function, not need
                                        break;
                                    }
                                }
                            }else{
                                foreach ($responseObject as $response) {
                                    $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
                                    foreach($chosen_shipping_methods as $shipping_code) {
                                        if($helper->getOriginalShipHawkShippingPrice($shipping_code, (string) $shipping_price)) {
                                            $rate_id        = $response->id;
                                            $accessories_from_rate    = $response->shipping->carrier_accessorial;
                                            if(!empty($accessories_from_rate))
                                            $accessories = $api->getAccessoriesForBook($accessories_from_rate, $orderAccessories->$shipping_code); // return array with accessories and price of this accessorials
                                            $rate_name      = $carrier_model->_getServiceName($response);
                                            $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
                                            $package_info    = Mage::getModel('shiphawk_shipping/carrier')->getPackeges($response);
                                            $is_rate = true;
                                            $multi_front = true;
                                            unset($chosen_shipping_methods[array_search($shipping_code,$chosen_shipping_methods)]);
                                            break 2;
                                        }
                                    }
                                }
                            }

                            if($is_rate == true) {

                                $accessoriesPrice = 0;
                                // add book
                                if(isset($accessories['accessories'])) {
                                    $track_data = $api->toBook($order, $rate_id, $rates_data[0], $accessories['accessories'], false, $self_pack, null, $multi_front);
                                    $accessoriesPrice = $accessories['price'];
                                }else{
                                    $track_data = $api->toBook($order, $rate_id, $rates_data[0], array(), false, $self_pack, null, $multi_front);
                                }


                                if (property_exists($track_data, 'error')) {
                                    Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                    $helper->shlog('ShipHawk response: '.$track_data->error);
                                    $helper->sendErrorMessageToShipHawk($track_data->error);
                                    continue;
                                }

                                $shipment = $api->_initShipHawkShipment($order,$rates_data[0]);
                                $shipment->register();

                                $shippingShipHawkAmount = $shipping_price + $accessoriesPrice;
                                $api->_saveShiphawkShipment($shipment, $rate_name, $shippingShipHawkAmount, $package_info,$track_data->details->id); // save shipping price incl accessories price

                                // add track
                                if($track_data->details->id) {
                                    $api->addTrackNumber($shipment, $track_data->details->id);// shipments id, not track number
                                    $api->subscribeToTrackingInfo($shipment->getId());
                                }

                                $shipmentCreatedMessage = $this->__('The shipment has been created.');
                                $this->_getSession()->addSuccess($shipmentCreatedMessage);

                            }else{
                                Mage::getSingleton('core/session')->addError("Unfortunately the method that was chosen by a customer during checkout is currently unavailable. Please contact ShipHawk's customer service to manually book this shipment.");
                                Mage::getSingleton('core/session')->setErrorPriceText("Sorry, we can't find the rate identical to the one that this order has. Please select another rate:");

                                $response['error_text'] = "Sorry, we can't find the rate identical to the one that this order has. Please select another rate:";
                                $response['order_id'] = $orderId;
                                $response['sUrl'] = $sUrl;
                            }
                        }else{
                            // no booking for disabled items, save only magento shipping
                            $shipment = $api->_initShipHawkShipment($order,$rates_data[0]);
                            $shipment->register();

                            $shippingShipHawkAmount = $rates_data[0]['price'];
                            $api->_saveShiphawkShipment($shipment, $rates_data[0]['name'], $shippingShipHawkAmount, '',null);

                            $shipmentCreatedMessage = $this->__('The shipment has been created.');
                            $this->_getSession()->addSuccess($shipmentCreatedMessage);
                        }
                }
            }else{
                // single parcel shipment
                foreach($shiphawk_rate_data as $rate_id=>$products_ids) {
                    $is_rate = false;
                    $package_info = '';
                    /* get zipcode and location type from first item in grouped by origin (zipcode) products */
                    $from_zip = $products_ids['items'][0]['zip'];
                    $location_type = $products_ids['items'][0]['location_type'];

                    $carrier_type = $products_ids['carrier_type'];

                    $self_pack = $products_ids['self_pack'];
                    $disabled = $products_ids['shiphawk_disabled'];

                    if(!$disabled) {
                        $responseObject = $api->getShiphawkRate($from_zip, $products_ids['to_zip'], $products_ids['items'], $rate_filter, $carrier_type, $location_type, $shLocationType, $pre_accessories);

                        if(is_object($responseObject))
                            if (property_exists($responseObject, 'error')) {
                                Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                $helper->shlog('ShipHawk response: '.$responseObject->error);
                                $helper->sendErrorMessageToShipHawk($responseObject->error);
                                $response['error_text'] = "Sorry, we can't find the rate identical to the one that this order has. Please select another rate:";
                                $response['order_id'] = $orderId;
                                $response['sUrl'] = $sUrl;
                                continue;
                            };

                        // if it is single parcel shipping, then only one shipping rate code
                        $shipping_code = $chosen_shipping_methods[0];
                        $accessoriesPrice = Mage::helper('shiphawk_shipping')->getAccessoriesPrice($orderAccessories);
                        if($is_backend_order) {
                            $original_shipping_price = floatval($order->getShiphawkShippingAmount());
                        }else{
                            $original_shipping_price = floatval($order->getShiphawkShippingAmount() - $accessoriesPrice);
                        }

                        foreach ($responseObject as $response) {

                            // shipping rate price from new response
                            $shipping_price = $helper->getShipHawkPrice($response, $self_pack);
                            if(round($original_shipping_price,2) == round($shipping_price,2)) {
                                $rate_id        = $response->id;
                                $accessories_from_rate    = $response->shipping->carrier_accessorial;
                                if(!empty($accessories_from_rate))
                                $accessories = $api->getAccessoriesForBook($accessories_from_rate, $orderAccessories->$shipping_code); // return array with accessories and price of this accessorials
                                $package_info    = Mage::getModel('shiphawk_shipping/carrier')->getPackeges($response);
                                $is_rate = true;
                                $multi_front = true;
                                break;
                            }
                        }

                        if($is_rate == true) {
                            // add book
                            //$track_data = $api->toBook($order, $rate_id, $products_ids, $accessories, false, $self_pack);
                            if(isset($accessories['accessories'])) {
                                $track_data = $api->toBook($order, $rate_id, $products_ids, $accessories['accessories'], false, $self_pack, null, $multi_front);
                            }else{
                                $track_data = $api->toBook($order, $rate_id, $products_ids, array(), false, $self_pack, null, $multi_front);
                            }

                            if (property_exists($track_data, 'error')) {
                                Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                $helper->shlog('ShipHawk response: '.$track_data->error);
                                $helper->sendErrorMessageToShipHawk($track_data->error);
                                continue;
                            }

                            $shipment = $api->_initShipHawkShipment($order,$products_ids);
                            $shipment->register();
                            if(isset($accessories['price'])) {
                                $shippingShipHawkAmount = $products_ids['price'] + $accessories['price'];
                            }else{
                                $shippingShipHawkAmount = $products_ids['price'];
                            }

                            $api->_saveShiphawkShipment($shipment, $products_ids['name'], $shippingShipHawkAmount, $package_info, $track_data->details->id);

                            // add track
                            if($track_data->details->id) {
                                $api->addTrackNumber($shipment, $track_data->details->id);
                                $api->subscribeToTrackingInfo($shipment->getId());
                            }

                            $shipmentCreatedMessage = $this->__('The shipment has been created.');
                            $this->_getSession()->addSuccess($shipmentCreatedMessage);

                        }else{
                            Mage::getSingleton('core/session')->setErrorPriceText("Sorry, we can't find the rate identical to the one that this order has. Please select another rate:");

                            $response['error_text'] = "Sorry, we can't find the rate identical to the one that this order has. Please select another rate:";
                            $response['order_id'] = $orderId;
                            $response['sUrl'] = $sUrl;
                        }
                    }else{
                        // no booking for disabled items, save only magento shipping
                        $shipment = $api->_initShipHawkShipment($order,$products_ids);
                        $shipment->register();

                        $shippingShipHawkAmount = $products_ids['price'];
                        $api->_saveShiphawkShipment($shipment, $products_ids['name'], $shippingShipHawkAmount, '', null);

                        $shipmentCreatedMessage = $this->__('The shipment has been created.');
                        $this->_getSession()->addSuccess($shipmentCreatedMessage);
                    }

                }
            }

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            //$this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
            $this->getResponse()->setBody(json_encode($response));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Cannot save shipment.'));
            $this->getResponse()->setBody(json_encode($response));
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

        if(array_key_exists('multiple_shipments_id', $params) ) {
            $multiple_shipments_id = unserialize($params['multiple_shipments_id']);
        }

        $shipmentCreatedMessage = $this->__('Something went wrong');

        try {
            $order = Mage::getModel('sales/order')->load($orderId);
            $shiphawk_rate_data = Mage::getSingleton('core/session')->getData('new_shiphawk_book_id', true);
            $api = Mage::getModel('shiphawk_shipping/api');
            $helper = Mage::helper('shiphawk_shipping');

            foreach($shiphawk_rate_data as $rate_id=>$products_ids) {

                    $disabled = $products_ids['shiphawk_disabled'];
                    // add book
                    if($is_multi == 0) { // single parcel shipment
                        if($shiphawk_rate_id == $rate_id) {
                            $self_pack = $products_ids['self_pack'];

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

                            if(!$disabled) {
                                $track_data = $api->toBook($order,$rate_id,$products_ids, $accessoriesData, true, $self_pack, true, null);

                                if (property_exists($track_data, 'error')) {
                                    Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                    $helper->shlog('ShipHawk response: '.$track_data->error);
                                    continue;
                                }

                                $package_info = '';
                                $shippingShipHawkAmount = $products_ids['price'] + $accessoriesPrice;
                                $order->setShiphawkShippingAmount($shippingShipHawkAmount); //resave shipping price
                                $order->setShiphawkShippingAccessories(json_encode($accessoriesData)); // resave accessories
                                $order->save();

                                $shipment = $api->_initShipHawkShipment($order,$products_ids);
                                $shipment->register();
                                //$api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price']);
                                $api->_saveShiphawkShipment($shipment, $products_ids['name'], $shippingShipHawkAmount, $package_info,$track_data->details->id); // save shipping price incl accessories price

                                // add track
                                $track_number = $track_data->details->id;

                                $api->addTrackNumber($shipment, $track_number);
                                $api->subscribeToTrackingInfo($shipment->getId());

                                $shipmentCreatedMessage = $this->__('The shipment has been created.');
                                $this->_getSession()->addSuccess($shipmentCreatedMessage);
                            }else{
                                // no booking for backup ShipHawk method

                                $shippingShipHawkAmount = $products_ids['price'];
                                $order->setShiphawkShippingAmount($shippingShipHawkAmount); //resave shipping price
                                $order->save();

                                $shipment = $api->_initShipHawkShipment($order,$products_ids);
                                $shipment->register();

                                $api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price'], '',null); // save shipping price incl accessories price

                                $shipmentCreatedMessage = $this->__('The shipment has been created.');
                                $this->_getSession()->addSuccess($shipmentCreatedMessage);

                            }

                        }
                    }else{
                        if(in_array($products_ids['rate_price_for_group'], $multiple_shipments_id)) {
                            // multi parcel shipping. book shipments with cheapest price.
                            $self_pack = $products_ids['self_pack'];
                            $accessories = array();
                            $package_info = '';

                            if(!$disabled) {
                                $track_data = $api->toBook($order, $rate_id, $products_ids, $accessories, false, $self_pack, true, null);

                                if (property_exists($track_data, 'error')) {
                                    Mage::getSingleton('core/session')->addError("The booking was not successful, please try again later.");
                                    $helper->shlog('ShipHawk response: ' . $track_data->error);
                                    continue;
                                }

                                $order->setShiphawkShippingAmount($multi_price);
                                $order->save();

                                $shipment = $api->_initShipHawkShipment($order, $products_ids);
                                $shipment->register();

                                //$api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price']);
                                $api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price'], $package_info, $track_data->details->id);// save shipping price incl accessories price

                                // add track
                                $track_number = $track_data->details->id;

                                $api->addTrackNumber($shipment, $track_number);
                                $api->subscribeToTrackingInfo($shipment->getId());

                                $shipmentCreatedMessage = $this->__("The multi-origin shipment's has been created.");
                                $this->_getSession()->addSuccess($shipmentCreatedMessage);
                            }else{
                                // no booking for backup ShipHawk method
                                $order->setShiphawkShippingAmount($multi_price);
                                $order->save();

                                $shipment = $api->_initShipHawkShipment($order, $products_ids);
                                $shipment->register();

                                $api->_saveShiphawkShipment($shipment, $products_ids['name'], $products_ids['price'], '', null);

                                $shipmentCreatedMessage = $this->__("The multi-origin shipment's has been created.");
                                $this->_getSession()->addSuccess($shipmentCreatedMessage);
                            }

                        }
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

        if ((floatval($shiphawk_override_cost) >= 0)&&($shiphawk_override_cost != '')) {
            Mage::getSingleton('core/session')->unsetData('shiphawk_override_cost');
            Mage::getSingleton('core/session')->setData('shiphawk_override_cost', $shiphawk_override_cost);
        }

        Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
        Mage::getSingleton('core/session')->setData('admin_accessories_price', $accessories_price);
    }
}