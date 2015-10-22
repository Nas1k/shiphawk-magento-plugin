<?php
class Shiphawk_Shipping_IndexController extends Mage_Core_Controller_Front_Action
{
    public function trackingAction() {

        $api_key_from_url = $this->getRequest()->getParam('api_key');
        $data_from_shiphawk = json_decode(file_get_contents('php://input'));
        $api_key = Mage::helper('shiphawk_shipping')->getApiKey();

        //curl -X POST -H Content-Type:application/json -d '{"event":"shipment.status_update","status":"in_transit","updated_at":"2015-01-14T10:43:16.702-08:00","shipment_id":1010226}' http://shiphawk.devigor.wdgtest.com/index.php/shiphawk/index/tracking?api_key=3331b35952ec7d99338a1cc5c496b55c
        //curl -X POST -H Content-Type:application/json -d '{"event":"shipment.status_update","status":"in_transit","updated_at":"2015-01-14T10:43:16.702-08:00","shipment_id":1015967}' http://shiphawk.devigor.wdgtest.com/index.php/shiphawk/index/tracking?api_key=e1919f54fb93f63866f06049d6d45751

        $helper = Mage::helper('shiphawk_shipping');
        if($api_key_from_url == $api_key) {
            try {
            $data_from_shiphawk = (array) $data_from_shiphawk;
            $track_number = $data_from_shiphawk['shipment_id'];
            $shipment_track = Mage::getResourceModel('sales/order_shipment_track_collection')->addAttributeToFilter('track_number', $track_number)->getFirstItem();

            $shipment = Mage::getModel('sales/order_shipment')->load($shipment_track->getParentId());

            $helper->shlog($data_from_shiphawk, 'shiphawk-tracking.log');

            $shipment_status_updates = Mage::getStoreConfig('carriers/shiphawk_shipping/shipment_status_updates');
            $updates_tracking_url =    Mage::getStoreConfig('carriers/shiphawk_shipping/updates_tracking_url');
            $comment = '';

            $crated_time = $this->convertDateTome($data_from_shiphawk['updated_at']);

                if($data_from_shiphawk['event'] == 'shipment.status_update') {
                    switch ($data_from_shiphawk['status']) {
                        case 'in_transit':
                            $comment = "Shipment status changed to In Transit (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment is with the carrier and is in transit.";
                            break;
                        case 'confirmed':
                            $comment = "Shipment status changed to Confirmed (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment has been successfully confirmed.";
                            break;
                        case 'scheduled_for_pickup':
                            $comment = "Shipment status changed to Scheduled (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment has been scheduled for pickup.";
                            break;
                        case 'agent_prep':
                            $comment = "Shipment status changed to Agent Prep (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment is now being professionally prepared for carrier pickup.";
                            break;
                        case 'delivered':
                            $comment = "Shipment status changed to Delivered (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment has been delivered!";
                            break;
                        case 'cancelled':
                            $comment = "Shipment status changed to Cancelled (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment has been cancelled successfully.";
                            break;
                        case 'ready_for_carrier_pickup':
                            $comment = "Shipment status changed to Ready for Carrier Pickup (" . $crated_time['date'] . " at " . $crated_time['time'] . "). Your shipment has been successfully dispatched to the carrier.";
                            break;
                        default:
                            $comment = "Shipment status is ".$comment = "Shipment status is ";
                    }

                    $shipment->addComment($comment);
                    if($shipment_status_updates) {
                        $shipment->sendUpdateEmail(true, $comment);
                    }
                }

                if($data_from_shiphawk['event'] == 'shipment.tracking_update') {
                    $comment = $data_from_shiphawk['updated_at'] . 'There is a tracking number available for your shipment - ' . $data_from_shiphawk['tracking_number'];
                    if ($data_from_shiphawk['tracking_url']) {
                        $comment .= ' <a href="' . $data_from_shiphawk['tracking_url'] . '" target="_blank">Click here to track.</a>';
                    }


                    $shipment->addComment($comment);
                    if($updates_tracking_url) {
                        $shipment->sendUpdateEmail(true, $comment);
                    }
                }


            $shipment->save();
            }catch (Mage_Core_Exception $e) {
                Mage::logException($e);
            } catch (Exception $e) {
                Mage::logException($e);
            }

        }
    }

    public function convertDateTome ($date_time) {
        ///2015-04-01T15:57:42Z
        $result = array();
        $t = explode('T', $date_time);
        $result['date'] = date("m/d/y", strtotime($t[0]));

        $result['time'] = date("g:i a", strtotime(substr($t[1], 0, -1)));

        return $result;
    }

    /* suggest items type in product page */
    public function searchAction() {

        $search_tag = trim(strip_tags($this->getRequest()->getPost('search_tag')));

        $api_key = Mage::helper('shiphawk_shipping')->getApiKey();
        $api_url = Mage::helper('shiphawk_shipping')->getApiUrl();

        $url_api = $api_url . 'items/search?q='.$search_tag.'&api_key='.$api_key;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url_api,
            CURLOPT_POST => false
        ));

        $resp = curl_exec($curl);
        $arr_res = json_decode($resp);

        $responce_array = array();
        $responce = array();

        $helper = Mage::helper('shiphawk_shipping');

        if(is_object($arr_res)) {
         if(($arr_res->error)) {
            $helper->shlog($arr_res->error);
            $responce_html = '';
            $responce['shiphawk_error'] = $arr_res->error;
         }
        }else{
            foreach ((array) $arr_res as $el) {
                $responce_array[$el->id] = $el->name.' ('.$el->category_name. ' - ' . $el->subcategory->name . ')';
            }

            $responce_html="<ul>";

            foreach($responce_array as $key=>$value) {
                $responce_html .='<li class="type_link" id='.$key.' onclick="setItemid(this)" >'.$value.'</li>';
            }

            $responce_html .="</ul>";
        }
        $responce['responce_html'] = $responce_html;
        curl_close($curl);

        $this->getResponse()->setBody( json_encode($responce) );
    }

    public function originsAction() {
        $origin_id = trim(strip_tags($this->getRequest()->getPost('origin_id')));

        $is_mass_action = $this->getRequest()->getPost('is_mass_action');

        $origins_collection = $collection = Mage::getModel('shiphawk_shipping/origins')->getCollection();

        $responce = '<select name="product[shiphawk_shipping_origins]" id="shiphawk_shipping_origins">';

        if($is_mass_action == 1) {
            $responce = '<select name="attributes[shiphawk_shipping_origins]" id="shiphawk_shipping_origins" disabled>';
        }

        $responce .= '<option value="">Primary origin</option>';

        foreach($origins_collection as $origin) {
            if ($origin_id != $origin->getId()) {
                $responce .= '<option value="'.$origin->getId().'">'.$origin->getShiphawkOriginTitle(). '</option>';
            }else{
                $responce .= '<option selected value="'.$origin->getId().'">'.$origin->getShiphawkOriginTitle().  '</option>';
            }
        }

        $responce .='</select>';

        $this->getResponse()->setBody( json_encode($responce) );
    }

    public function getbolAction() {

        $shipments_id = $this->getRequest()->getPost('shipments_id');

        $responce_BOL = Mage::helper('shiphawk_shipping')->getBOLurl($shipments_id);

        $responce = array();

        if (property_exists($responce_BOL, 'url')) {

            $path_to_save_bol_pdf = Mage::getBaseDir('media'). DS .'shiphawk'. DS .'bol';
            $BOLpdf = $path_to_save_bol_pdf . DS .  $shipments_id . '.pdf';

            if (file_get_contents($BOLpdf)) {
                $responce['bol_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'media' . DS . 'shiphawk'. DS .'bol' . DS . $shipments_id . '.pdf';
                $this->getResponse()->setBody( json_encode($responce) );
            }else{

                file_put_contents($BOLpdf, file_get_contents($responce_BOL->url));

                $responce['bol_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'media' . DS . 'shiphawk'. DS .'bol' . DS . $shipments_id . '.pdf';

                $this->getResponse()->setBody( json_encode($responce) );
            }

        }else{
            if (property_exists($responce_BOL, 'error')){
                $responce['shiphawk_error'] = $responce_BOL->error;
            }

            $this->getResponse()->setBody( json_encode($responce) );

        }

    }
}