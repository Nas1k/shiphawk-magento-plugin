<?php
/** @var Mage_Eav_Model_Entity_Setup $installer */
$installer = Mage::getResourceModel('sales/setup','sales_setup');
$installer->startSetup();

$installer->addAttribute('order', 'shiphawk_rate_filter', array('type' => 'text', 'input' => 'text'));
$installer->addAttribute('quote', 'shiphawk_rate_filter', array('type' => 'text', 'input' => 'text'));
$installer->getConnection()->addColumn($installer->getTable('sales_flat_order'), 'shiphawk_rate_filter', 'text');

$installer->endSetup();