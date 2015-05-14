<?php
class Shiphawk_Shipping_Block_Shipping_Tracking_Popup extends Mage_Shipping_Block_Tracking_Popup
{
    public function getTrackingInfo()
    {
        $_results = parent::getTrackingInfo();
        return $_results;
    }

}
