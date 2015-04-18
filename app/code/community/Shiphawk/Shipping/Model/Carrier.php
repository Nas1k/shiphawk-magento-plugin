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
        // hide ShipHawk method on frontend , alow only in admin area
        if (($hide_on_frontend == 1) && (!$is_admin)) {
            return $result;
        }

        $to_zip = $this->getShippingZip();
        $api = Mage::getModel('shiphawk_shipping/api');
        $items = $this->getShiphawkItems($request);

        $grouped_items_by_zip = $this->getGroupedItemsByZip($items);

        $ship_responces = array();
        $toOrder= array();
        $api_error = false;
        $is_multi_zip = (count($grouped_items_by_zip) > 1) ? true : false;
        $rate_filter =  Mage::helper('shiphawk_shipping')->getRateFilter();
        if($is_multi_zip) {
            $rate_filter = 'best';
        }
        try {
            foreach($grouped_items_by_zip as $from_zip=>$items_) {
                $checkattributes = $helper->checkShipHawkAttributes($from_zip, $to_zip, $items_, $rate_filter);


                if(empty($checkattributes)) {
                    $responceObject = $api->getShiphawkRate($from_zip, $to_zip, $items_, $rate_filter);
                    $ship_responces[] = $responceObject;

                    if(is_object($responceObject)) {
                        $api_error = true;
                        Mage::log('ShipHawk responce: '.$responceObject->error, null, 'ShipHawk.log');
                    }else{
                        // if $rate_filter = 'best' then it is only one rate
                        if(($is_multi_zip)||($rate_filter == 'best')) {
                            Mage::getSingleton('core/session')->setMultiZipCode(true);
                            $toOrder[$responceObject[0]->id]['product_ids'] = $this->getProductIds($items_);
                            $toOrder[$responceObject[0]->id]['price'] = $responceObject[0]->summary->price;
                            $toOrder[$responceObject[0]->id]['name'] = $responceObject[0]->summary->service;
                            $toOrder[$responceObject[0]->id]['items'] = $items_;
                            $toOrder[$responceObject[0]->id]['from_zip'] = $from_zip;
                            $toOrder[$responceObject[0]->id]['to_zip'] = $to_zip;
                        }else{
                            Mage::getSingleton('core/session')->setMultiZipCode(false);
                            foreach ($responceObject as $responce) {
                                $toOrder[$responce->id]['product_ids'] = $this->getProductIds($items_);
                                $toOrder[$responce->id]['price'] = $responce->summary->price;
                                $toOrder[$responce->id]['name'] = $responce->summary->service;
                                $toOrder[$responce->id]['items'] = $items_;
                                $toOrder[$responce->id]['from_zip'] = $from_zip;
                                $toOrder[$responce->id]['to_zip'] = $to_zip;
                            }
                        }
                    }
                }else{
                    $api_error = true;
                }



            }

            if(!$api_error) {
                $services = $this->getServices($ship_responces);
                $name_service = '';
                $summ_price = 0;

                foreach ($services as $id_service=>$service) {
                    if (!$is_multi_zip) {
                        //add ShipHawk shipping
                        $result->append($this->_getShiphawkRateObject($service['name'], $service['price']));
                    }else{
                        $name_service .= $service['name'] . ', ';
                        $summ_price += $service['price'];
                    }
                }

                //save rate_id info for Book
                Mage::getSingleton('core/session')->setShiphawkBookId($toOrder);

                //remove last comma
                if(strlen($name_service) >2) {
                    if ($name_service{strlen($name_service)-2} == ',') {
                        $name_service = substr($name_service,0,-2);
                    }
                }

                if($is_multi_zip) {
                    //add ShipHawk shipping
                    $name_service = 'Shipping from multiple locations';
                    $result->append($this->_getShiphawkRateObject($name_service, $summ_price));
                }
            }

        }catch (Mage_Core_Exception $e) {
            Mage::logException($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e->getMessage());
        }
        return $result;
    }

    /**
     * Returns Allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            'ground'    =>  'Ground delivery',
        );
    }

    public function  getProductIds($_items) {
     $products_ids = array();
        foreach($_items as $_item) {
            $products_ids[] = $_item['product_id'];
        }
        return $products_ids;
    }

    /**
     * Get Standard rate object
     *
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getShiphawkRateObject($method_title, $price)
    {
        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        $rate = Mage::getModel('shipping/rate_result_method');

        $ship_rate_id = str_replace('-', '_', str_replace(',', '', str_replace(' ', '_', $method_title)));

        $rate->setCarrier($this->_code);
        //$rate->setCarrierTitle($this->getConfigData('title'));
        $rate->setCarrierTitle('ShipHawk');
        $rate->setMethodTitle($method_title);
        $rate->setMethod($ship_rate_id);
        $rate->setPrice($price);
        $rate->setCost($price);

        return $rate;
    }

    public function getShiphawkItems($request) {
        $items = array();

        foreach ($request->getAllItems() as $item) {
            $product_id = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($product_id);

            $product_qty = (($product->getShiphawkQuantity() > 0)) ? $product->getShiphawkQuantity() : 1;

            //hack for admin shipment in popup
            $qty_ordered = ($item->getQty() > 0 ) ? $item->getQty() : $item->getData('qty_ordered');

            if($product->getWeight() > 0) {
                $items[] = array(
                    'width' => $product->getShiphawkWidth(),
                    'length' => $product->getShiphawkLength(),
                    'height' => $product->getShiphawkHeight(),
                    'weight' => $product->getWeight(),
                    'value' => $this->getShipHawkItemValue($product),
                    //'quantity' => $product_qty*$item->getQty(),
                    'quantity' => $product_qty*$qty_ordered,
                    'packed' => $this->getIsPacked($product),
                    'id' => $product->getShiphawkTypeOfProductValue(),
                    'zip'=> $this->getOriginZip($product),
                    'product_id'=> $product_id,
                    'xid'=> $product_id,
                );
            }else{
                $items[] = array(
                    'width' => $product->getShiphawkWidth(),
                    'length' => $product->getShiphawkLength(),
                    'height' => $product->getShiphawkHeight(),
                    'value' => $this->getShipHawkItemValue($product),
                    //'quantity' => $product_qty*$item->getQty(),
                    'quantity' => $product_qty*$qty_ordered,
                    'packed' => $this->getIsPacked($product),
                    'id' => $product->getShiphawkTypeOfProductValue(),
                    'zip'=> $this->getOriginZip($product),
                    'product_id'=> $product_id,
                    'xid'=> $product_id,
                );
            }

        }

        return $items;
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

        //$product_origin_zip_code = $product->getShiphawkOriginZipcode();

        // get zip code froms Shiping Origin
        $shipping_origin_id = $product->getData('shiphawk_shipping_origins');

        $shipping_origin = Mage::getModel('shiphawk_shipping/origins')->load($shipping_origin_id);
        $product_origin_zip_code = $shipping_origin->getData('shiphawk_origin_zipcode');

        $product_origin_zip_code = (empty($product_origin_zip_code)) ? $default_origin_zip : $product_origin_zip_code;

        return $product_origin_zip_code;
    }

    public function getIsPacked($product) {
        $default_is_packed = Mage::getStoreConfig('carriers/shiphawk_shipping/item_is_packed');
        $product_is_packed = $product->getShiphawkItemIsPacked();
        $product_is_packed = ($product_is_packed == 2) ? $default_is_packed : $product_is_packed;

        return ($product_is_packed ? 'true' : 'false');
	//return $product_is_packed;
    }

    public function getGroupedItemsByZip($items) {
        $tmp = array();
        foreach($items as $item) {
            $tmp[$item['zip']][] = $item;
        }
        return $tmp;
    }

    public function getServices($ship_responces) {
        $services = array();
        foreach($ship_responces as $ship_responce) {
            if(is_array($ship_responce)) {
                foreach($ship_responce as $object) {
                    $services[$object->id]['name'] = $this->_getServiceName($object);
                    $services[$object->id]['price'] = $object->summary->price;
                }
            }
        }

        return $services;
    }

    protected function _getServiceName($object) {
        if ( $object->summary->carrier_type == "Small Parcel" ) {
            return $object->summary->service;
        }

        if ( $object->summary->carrier_type == "Blanket Wrap" ) {
            return "Standard White Glove Delivery (3-6 weeks)";
        }

        if ( ( ( $object->summary->carrier_type == "LTL" ) || ( $object->summary->carrier_type == "3PL" ) || ( $object->summary->carrier_type == "Intermodal" ) ) && ($object->details->price->delivery == 0) ) {
            return "Curbside delivery (1-2 weeks)";
        }

        if ( ( ( $object->summary->carrier_type == "LTL" ) || ( $object->summary->carrier_type == "3PL" ) || ( $object->summary->carrier_type == "Intermodal" ) ) && ($object->details->price->delivery > 0) ) {
            return "Expedited White Glove Delivery (2-3 weeks)";
        }

        return $object->summary->service;

    }

    /*
    1. If carrier_type = "Small Parcel" display name should be what's included in field [Service] (example: Ground)

    2. If carrier_type = "Blanket Wrap" display name should be:
    "Standard White Glove Delivery (3-6 weeks)"

    3. If carrier_type = "LTL","3PL","Intermodal" AND delivery field inside [details][price]=$0.00 display name should be:
    "Curbside delivery (1-2 weeks)"

    4. If carrier_type = "LTL","3PL" "Intermodal" AND delivery field inside [details][price] > $0.00 display name should be:
    "Expedited White Glove Delivery (2-3 weeks)"

    */


}
