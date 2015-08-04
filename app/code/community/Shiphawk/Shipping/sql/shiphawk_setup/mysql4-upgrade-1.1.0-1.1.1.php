<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

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
    'input'             => 'select',
    'system'            => false,
    'required'          => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product', 'shiphawk_carrier_type', $data);

/* for sortOrder */
$installer->updateAttribute('catalog_product', 'shiphawk_carrier_type', 'frontend_label', 'Carrier Type', 7);

$installer->endSetup();