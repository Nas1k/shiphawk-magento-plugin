<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$percentage = array (
    'attribute_set'     =>  'Default',
    'group'             => 'ShipHawk Attributes',
    'label'             => 'Markup or Discount Percentage',
    'visible'           => true,
    'type'              => 'varchar',
    'apply_to'          => 'simple',
    'input'             => 'text',
    'system'            => false,
    'required'          => false,
    'user_defined'      => 1,
    'note'              => 'possible values from -100 to 100'
);

$installer->addAttribute('catalog_product','shiphawk_discount_percentage', $percentage);
/* for sortOrder */
$installer->updateAttribute('catalog_product', 'shiphawk_discount_percentage', 'frontend_label', 'Markup or Discount Percentage', 7);

$fixed = array (
    'attribute_set'     =>  'Default',
    'group'             => 'ShipHawk Attributes',
    'label'             => 'Markup or Discount Flat Amount',
    'visible'           => true,
    'type'              => 'varchar',
    'apply_to'          => 'simple',
    'input'             => 'text',
    'system'            => false,
    'required'          => false,
    'user_defined'      => 1,
    'note'              => 'possible values from -∞ to ∞'
);

$installer->addAttribute('catalog_product','shiphawk_discount_fixed', $fixed);
/* for sortOrder */
$installer->updateAttribute('catalog_product', 'shiphawk_discount_fixed', 'frontend_label', 'Markup or Discount Flat Amount', 7);

$installer->endSetup();