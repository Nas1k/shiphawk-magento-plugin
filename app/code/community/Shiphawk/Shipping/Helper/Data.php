<?php

class Shiphawk_Shipping_Helper_Data extends
    Mage_Core_Helper_Abstract
{
    /**
     * Get api key
     *
     * @return mixed
     */
    public function getApiKey()
    {
        return Mage::getStoreConfig('carriers/shiphawk_shipping/api_key');
    }

    /**
     * Get callback url for shipments
     *
     * @return mixed
     */
    public function getCallbackUrl($api_key)
    {
        return Mage::getUrl('shiphawk/index/tracking', array('api_key' => $api_key));
    }

    public function getRateFilter($is_admin = false, $order = null)
    {
        if($order) {
            if($order->getShiphawkRateFilter()) {
                return $order->getShiphawkRateFilter();
            }
        }

        if ($is_admin == true) {
            return Mage::getStoreConfig('carriers/shiphawk_shipping/admin_rate_filter');
        }

        return Mage::getStoreConfig('carriers/shiphawk_shipping/rate_filter');
    }

    /**
     * Get api url
     *
     * @return mixed
     */
    public function getApiUrl()
    {
        //return 'https://sandbox.shiphawk.com/api/v1/';
        return Mage::getStoreConfig('carriers/shiphawk_shipping/gateway_url');
    }

    /**
     * Get Shiphawk attributes codes
     *
     * @return array
     */
    public function getAttributes()
    {
        $shiphawk_attributes = array('shiphawk_length','shiphawk_width', 'shiphawk_height', 'shiphawk_origin_zipcode', 'shiphawk_origin_firstname', 'shiphawk_origin_lastname'
        ,'shiphawk_origin_addressline1','shiphawk_origin_phonenum','shiphawk_origin_city','shiphawk_origin_state','shiphawk_type_of_product','shiphawk_type_of_product_value'
        ,'shiphawk_quantity', 'shiphawk_item_value','shiphawk_item_is_packed','shiphawk_origin_location');

        return $shiphawk_attributes;
    }

    public function isShipHawkShipping($shipping_code) {
        $result = strpos($shipping_code, 'shiphawk_shipping');
        return $result;
    }

    public function getShipHawkCode($shiphawk_book_id, $shipping_code) {
        $result = array();

        foreach ($shiphawk_book_id as $rate_id=>$method_data) {
            //if( strpos($shipping_description, $method_data['name']) !== false ) {
            //if( $shipping_code == $method_data['price'] ) {
            $shipping_price = (string) $method_data['price'];
              if($this->getOriginalShipHawkShippingPrice($shipping_code, $shipping_price)) {
                $result = array($rate_id => $method_data);
                return $result;
            }
        }
        return $result;
    }

    public function checkIsAdmin () {
        if(Mage::app()->getStore()->isAdmin())
        {
            return true;
        }

        if(Mage::getDesign()->getArea() == 'adminhtml')
        {
            return true;
        }

        return false;
    }

    public  function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function checkShipHawkAttributes($from_zip, $to_zip, $items_, $rate_filter) {
        $error = array();
        if (empty($from_zip)) {
            $error['from_zip'] = 1;
        }

        if (empty($to_zip)) {
            $error['to_zip'] = 1;
        }

        if (empty($rate_filter)) {
            $error['rate_filter'] = 1;
        }

        foreach ($items_ as $item) {

            if($this->checkItem($item)) {
                $error['items']['name'][] = $this->checkItem($item);
            }
        }

        return $error;
    }

    public function checkItem($item) {
        $product_name = Mage::getModel('catalog/product')->load($item['product_id'])->getName();

        if(empty($item['width'])) return $product_name;
        if(empty($item['length'])) return $product_name;
        if(empty($item['height'])) return $product_name;
        if(empty($item['quantity'])) return $product_name;
        if(empty($item['packed'])) return $product_name;

        return null;
    }

    public function discountPercentage($price) {
        $discountPercentage = Mage::getStoreConfig('carriers/shiphawk_shipping/discount_percentage');

        if(!empty($discountPercentage)) {
            $price = $price + ($price * ($discountPercentage/100));
        }


        return $price;
    }

    public function discountFixed($price) {
        $discountFixed = Mage::getStoreConfig('carriers/shiphawk_shipping/discount_fixed');
        if(!empty($discountFixed)) {
            $price = $price + ($discountFixed);
        }

        return $price;
    }

    public function getDiscountShippingPrice($price) {
        $price = $this->discountPercentage($price);
        $price = $this->discountFixed($price);

        if($price <= 0) {
            return 0;
        }
        return $price;
    }

    public function getOriginalShipHawkShippingPrice($shipping_code, $shipping_method_value) {
        $result = strpos($shipping_code, $shipping_method_value);
        return $result;
    }

    public function checkShipHawkOriginAttributes($product) {

        $required_origins_attributes = array('shiphawk_origin_firstname', 'shiphawk_origin_lastname', 'shiphawk_origin_addressline1', 'shiphawk_origin_city', 'shiphawk_origin_state', 'shiphawk_origin_zipcode', 'shiphawk_origin_phonenum', 'shiphawk_origin_location');

        foreach($required_origins_attributes as $attribute_code) {
            $attribute_value = $product->getData($attribute_code);
            if(empty($attribute_value)) {
                return false;
            }
        }

        return true;
    }

    public function getSummaryPrice($object) {
        return $object->shipping->price + $object->packing->price + $object->pickup->price + $object->delivery->price + $object->insurance->price;
    }

    public function getBOLurl($shipment_id) {

        $api_key = $this->getApiKey();

        $bol_url = $this->getApiUrl() . 'shipments/' . $shipment_id . '/bol?api_key=' . $api_key;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $bol_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


        $resp = curl_exec($curl);
        $arr_res = json_decode($resp);

        return $arr_res;

    }

    /**
     * For get shipping price with personal product shipping discount
     *
     * @param $price
     * @param $orderData
     * @return int
     *
     * @version 20150706
     */
    public function getTotalDiscountShippingPrice($price, $orderData) {
        $items  = $orderData['items'];
        $result = 0;

        if (empty($items)) {
            return $result;
        }

        $productModel = Mage::getModel('catalog/product');

        // if one items in pack get discount from product, if it empty then for sys. config
        if (count($items) == 1) {
            $product                    = $productModel->load($items[0]['product_id']);
            $shiphawkDiscountPercentage = $product->getShiphawkDiscountPercentage();
            $shiphawkDiscountFixed      = $product->getShiphawkDiscountFixed();

            if (empty($shiphawkDiscountPercentage) && empty($shiphawkDiscountFixed)) {
                return $this->getDiscountShippingPrice($price);
            }

            $result = $price + ($price * ($shiphawkDiscountPercentage/100));
            $result = $result + ($shiphawkDiscountFixed);

            if($result <= 0) {
                return 0;
            }
        } else {
            $discount_arr = array();
            foreach($items as $item) {
                $product                    = $productModel->load($item['product_id']);
                $shiphawkDiscountPercentage = $product->getShiphawkDiscountPercentage();
                $shiphawkDiscountFixed      = $product->getShiphawkDiscountFixed();

                if (empty($shiphawkDiscountFixed) && empty($shiphawkDiscountPercentage)) {
                    $discount_arr['empty_val'] = array('percentage' => 0, 'fixed' => 0);
                } else {
                    $discount_arr[$shiphawkDiscountPercentage . '_' . $shiphawkDiscountFixed] = array(
                        'percentage' => $shiphawkDiscountPercentage,
                        'fixed' => $shiphawkDiscountFixed
                    );
                }
            }

            if (count($discount_arr) == 1 && !empty($discount_arr['empty_val'])) {
                return $this->getDiscountShippingPrice($price);
            }

            if (count($discount_arr) > 1) {
                return $price;
            }

            foreach($discount_arr as $discount) {
                $result = $price + ($price * ($discount['percentage']/100));
                $result = $result + ($discount['fixed']);
                break;
            }
        }

        return $result;
    }

    public function getProductCarrierType($product) {
        $carrier_type =  $product->getAttributeText('shiphawk_carrier_type');

        if(($carrier_type == 'All')||(!$carrier_type)) {
            return '';
        }

        return $carrier_type;

    }

    public function getProductDiscountMarkupPrice($shipping_price, $shiphawk_discount_percentage, $shiphawk_discount_fixed) {

        if(!empty($shiphawk_discount_percentage)) {
            $shipping_price = $shipping_price + ($shipping_price * ($shiphawk_discount_percentage/100));
        }

        if(!empty($shiphawk_discount_fixed)) {
            $shipping_price = $shipping_price + ($shiphawk_discount_fixed);
        }

        if($shipping_price <= 0) {
            return 0;
        }

        return $shipping_price;
    }

    public function shlog($var, $file = 'shiphawk-error.log') {
        $enable_log = Mage::getStoreConfig('carriers/shiphawk_shipping/enable_log');

        if($enable_log == 1) {
            Mage::log($var, null, $file);
        }
    }

}