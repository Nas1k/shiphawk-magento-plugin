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

$installer->endSetup();