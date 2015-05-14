<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$origin_email = array (
    'attribute_set' =>  'Default',
    'group' => 'ShipHawk Attributes',
    'label'    => 'Origin Email',
    'visible'     => true,
    'type'     => 'varchar',
    'apply_to'          => 'simple',
    'input'    => 'text',
    'system'   => false,
    'required' => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product','shiphawk_origin_email', $origin_email);


$installer->endSetup();