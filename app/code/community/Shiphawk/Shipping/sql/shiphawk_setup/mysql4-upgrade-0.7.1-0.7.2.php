<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$location_origin_data = array (
    'attribute_set' => 'Default',
    'group' => 'ShipHawk Attributes',
    'label'    => 'Origin Location Type',
    'visible'     => true,
    'option' => array ('value' => array(
        'commercial' => array('commercial'),
        'residential' => array('residential'),
    )
    ),
    'type'     => 'varchar',
    'input'    => 'select',
    'system'   => false,
    'required' => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product', 'shiphawk_origin_location', $location_origin_data);

$installer->endSetup();