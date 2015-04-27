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

    public function getRateFilter($is_admin = false)
    {
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

    public function getSipHawkCode($shiphawk_book_id, $shipping_code) {
        $result = array();

        foreach ($shiphawk_book_id as $rate_id=>$method_data) {
            //if( strpos($shipping_description, $method_data['name']) !== false ) {
            //if( $shipping_code == $method_data['price'] ) {
              if($this->getOriginalShipHawkShippingPrice($shipping_code, $method_data['price'])) {
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

}