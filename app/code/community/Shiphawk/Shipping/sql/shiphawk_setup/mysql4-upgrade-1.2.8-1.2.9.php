<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$shiphawk_disable_shipping = array (
    'attribute_set' =>  'Default',
    'group' => 'ShipHawk Attributes',
    'label'    => 'Disable ShipHawk shipping?',
    'source'        => 'eav/entity_attribute_source_boolean',
    'visible'     => true,
    'type'     => 'varchar',
    'apply_to'          => 'simple',
    'default'  => 0,
    'input'    => 'select',
    'system'   => false,
    'required' => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product','shiphawk_disable_shipping',$shiphawk_disable_shipping);

$installer->updateAttribute('catalog_product', 'shiphawk_disable_shipping', 'frontend_label', 'Disable ShipHawk shipping?', 11);

$installer->endSetup();