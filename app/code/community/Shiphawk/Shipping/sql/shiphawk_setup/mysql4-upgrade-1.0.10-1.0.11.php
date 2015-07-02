<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$data = array (
    'attribute_set'     =>  'Default',
    'group'             => 'ShipHawk Attributes',
    'label'             => 'Freight Class',
    'visible'           => true,
    'type'              => 'varchar',
    'apply_to'          => 'simple',
    'option'            => array ('values' => array(
        0 => '50',
        1 => '55',
        2 => '60',
        3 => '65',
        4 => '70',
        5 => '77.5',
        6 => '85',
        7 => '92.5',
        8 => '100',
        9 => '110',
        10 => '125',
        11 => '150',
        12 => '175',
        13 => '200',
        14 => '250',
        15 => '300',
        16 => '400',
        17 => '500'
        )),
    'input'             => 'select',
    'system'            => false,
    'required'          => false,
    'user_defined' => 1,
);

$installer->addAttribute('catalog_product', 'shiphawk_freight_class', $data);

/* for sortOrder */
$installer->updateAttribute('catalog_product', 'shiphawk_freight_class', 'frontend_label', 'Freight Class', 7);

$installer->endSetup();