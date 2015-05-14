<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();
// Remove Product Attribute

/*$origins_attributes = array('shiphawk_origin_firstname', 'shiphawk_origin_lastname', 'shiphawk_origin_addressline1', 'shiphawk_origin_addressline2',
'shiphawk_origin_city', 'shiphawk_origin_state', 'shiphawk_origin_zipcode', 'shiphawk_origin_phonenum', 'shiphawk_origin_location', 'shiphawk_origin_email');

foreach($origins_attributes as $code) {
    $attr = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode('catalog_product',$code);
    if(!(null == $attr->getId())) {
        //attribute does exist
        $installer->removeAttribute('catalog_product', $code);
    }

}*/

$installer->endSetup();