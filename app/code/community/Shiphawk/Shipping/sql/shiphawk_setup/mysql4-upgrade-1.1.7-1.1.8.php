<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$packing_price = array (
    'attribute_set'     =>  'Default',
    'group'             => 'ShipHawk Attributes',
    'label'             => 'Custom Packing Price',
    'visible'           => true,
    'type'              => 'varchar',
    'apply_to'          => 'simple',
    'input'             => 'text',
    'system'            => false,
    'required'          => false,
    'user_defined'      => 1,
);

$installer->addAttribute('catalog_product','shiphawk_custom_packing_price', $packing_price);

/* for sortOrder */

$installer->updateAttribute('catalog_product', 'shiphawk_type_of_product', 'frontend_label', 'Type of Item', 10);
$installer->updateAttribute('catalog_product', 'shiphawk_quantity', 'frontend_label', 'Number of items per Product', 20);
$installer->updateAttribute('catalog_product', 'shiphawk_item_is_packed', 'frontend_label', 'Is Product Already Packed?', 30);
$installer->updateAttribute('catalog_product', 'shiphawk_custom_packing_price', 'frontend_label', 'Custom Packing Price', 40);
$installer->updateAttribute('catalog_product', 'shiphawk_length', 'frontend_label', 'Length', 50);
$installer->updateAttribute('catalog_product', 'shiphawk_width', 'frontend_label', 'Width', 60);
$installer->updateAttribute('catalog_product', 'shiphawk_height', 'frontend_label', 'Height', 70);
$installer->updateAttribute('catalog_product', 'shiphawk_carrier_type', 'frontend_label', 'Carrier Type', 80);
$installer->updateAttribute('catalog_product', 'shiphawk_freight_class', 'frontend_label', 'Freight Class', 90);
$installer->updateAttribute('catalog_product', 'shiphawk_item_value', 'frontend_label', 'Item Value', 100);
$installer->updateAttribute('catalog_product', 'shiphawk_discount_percentage', 'frontend_label', 'Markup or Discount Percentage', 110);
$installer->updateAttribute('catalog_product', 'shiphawk_discount_fixed', 'frontend_label', 'Markup or Discount Flat Amount', 120);
$installer->updateAttribute('catalog_product', 'shiphawk_shipping_origins', 'frontend_label', 'Shipping Origins', 121);
$installer->updateAttribute('catalog_product', 'shiphawk_type_of_product_value', 'frontend_label', 'Origin Contact:', 125);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_firstname', 'frontend_label', 'Origin First Name', 130);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_lastname', 'frontend_label', 'Origin Last Name', 140);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_addressline1', 'frontend_label', 'Origin Address', 150);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_addressline2', 'frontend_label', 'Origin Address 2', 160);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_city', 'frontend_label', 'Origin City', 170);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_state', 'frontend_label', 'Origin State', 180);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_zipcode', 'frontend_label', 'Origin Zipcode', 190);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_phonenum', 'frontend_label', 'Origin Phone', 200);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_email', 'frontend_label', 'Origin Email', 210);
$installer->updateAttribute('catalog_product', 'shiphawk_origin_location', 'frontend_label', 'Origin Location', 220);



$installer->endSetup();