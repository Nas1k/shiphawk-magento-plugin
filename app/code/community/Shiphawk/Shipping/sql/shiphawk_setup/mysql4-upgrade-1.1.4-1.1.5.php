<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$installer->updateAttribute('catalog_product', 'shiphawk_type_of_product', 'frontend_label', 'Type of Item', 1);
$installer->updateAttribute('catalog_product', 'shiphawk_quantity', 'frontend_label', 'Number of items per Product', 2);
$installer->updateAttribute('catalog_product', 'shiphawk_item_is_packed', 'frontend_label', 'Packaged?', 3);
$installer->updateAttribute('catalog_product', 'shiphawk_length', 'frontend_label', 'Length', 4);
$installer->updateAttribute('catalog_product', 'shiphawk_width', 'frontend_label', 'Width', 5);
$installer->updateAttribute('catalog_product', 'shiphawk_height', 'frontend_label', 'Height', 6);
$installer->updateAttribute('catalog_product', 'shiphawk_carrier_type', 'frontend_label', 'Carrier Type', 7);
$installer->updateAttribute('catalog_product', 'shiphawk_freight_class', 'frontend_label', 'Freight Class', 8);
$installer->updateAttribute('catalog_product', 'shiphawk_item_value', 'frontend_label', 'Item Value', 9);
$installer->updateAttribute('catalog_product', 'shiphawk_discount_percentage', 'frontend_label', 'Markup or Discount Percentage', 10);
$installer->updateAttribute('catalog_product', 'shiphawk_discount_fixed', 'frontend_label', 'Markup or Discount Flat Amount', 11);
$installer->updateAttribute('catalog_product', 'shiphawk_type_of_product_value', 'frontend_label', 'Origin Contact:', 12);


$installer->endSetup();