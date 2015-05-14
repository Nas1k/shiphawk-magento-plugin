<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$shipping_origins = array (
    'attribute_set' =>  'Default',
    'group' => 'ShipHawk Attributes',
    'label'    => 'Shipping  Origins',
    'visible'     => true,
    'type'     => 'varchar',
    'apply_to'          => 'simple',
    'input'    => 'text',
    'system'   => false,
    'required' => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product','shiphawk_shipping_origins', $shipping_origins);


$installer->endSetup();