<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

// Remove shiphawk_carrier_type dropdown
$installer->removeAttribute('catalog_product', 'shiphawk_carrier_type');

$data = array (
    'attribute_set'     =>  'Default',
    'group'             => 'ShipHawk Attributes',
    'label'             => 'Carrier Type',
    'visible'           => true,
    'type'              => 'varchar',
    'apply_to'          => 'simple',
    'option'            => array ('values' => array(
        '' => 'All',
        'ltl' => 'ltl',
        'blanket wrap' => 'blanket wrap',
        'small parcel' => 'small parcel',
        'vehicle' => 'vehicle',
        'intermodal' => 'intermodal',
        'local delivery' => 'local delivery',
    )),
    'input'             => 'multiselect',
    'backend'           => 'eav/entity_attribute_backend_array',
    'system'            => false,
    'required'          => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product', 'shiphawk_carrier_type', $data);

/* for sortOrder */
//$installer->updateAttribute('catalog_product', 'shiphawk_height', 'frontend_label', 'Height', 5);
$installer->updateAttribute('catalog_product', 'shiphawk_carrier_type', 'frontend_label', 'Carrier Type', 6);
$installer->updateAttribute('catalog_product', 'shiphawk_freight_class', 'frontend_label', 'Freight Class', 7);
$installer->updateAttribute('catalog_product', 'shiphawk_discount_percentage', 'frontend_label', 'Markup or Discount Percentage', 8);
$installer->updateAttribute('catalog_product', 'shiphawk_discount_fixed', 'frontend_label', 'Markup or Discount Flat Amount', 8);
$installer->updateAttribute('catalog_product', 'shiphawk_type_of_product_value', 'frontend_label', 'Origin Contact:', 9);

$installer->endSetup();