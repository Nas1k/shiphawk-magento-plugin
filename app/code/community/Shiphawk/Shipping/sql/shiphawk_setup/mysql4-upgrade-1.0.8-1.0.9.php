<?php
/** @var Mage_Eav_Model_Entity_Setup $installer */
$installer = Mage::getResourceModel('sales/setup','sales_setup');
$installer->startSetup();

$installer->addAttribute('order', 'shiphawk_shipping_package_info', array('type' => 'text', 'input' => 'text'));
$installer->getConnection()->addColumn($installer->getTable('sales_flat_order'), 'shiphawk_shipping_package_info', 'text');

$installer->endSetup();