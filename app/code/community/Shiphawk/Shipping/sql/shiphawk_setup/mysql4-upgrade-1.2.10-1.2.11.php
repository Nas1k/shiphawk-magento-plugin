<?php
$installer = Mage::getResourceModel('customer/setup','default_setup');

$installer->addAttribute('customer_address', 'shiphawk_location_type', array(
    'type' => 'varchar',
    'input' => 'select',
    'label' => 'Location Type',
    'option' => array ('value' => array(
        'commercial' => array('commercial'),
        'residential' => array('residential'))),
    'source'        => 'eav/entity_attribute_source_table',
    'global' => 1,
    'visible' => 1,
    'required' => 0,
    'user_defined' => 1,
    'visible_on_front' => 1
));

Mage::getSingleton('eav/config')
    ->getAttribute('customer_address', 'shiphawk_location_type')
    ->setData('used_in_forms', array('customer_register_address','customer_address_edit','adminhtml_customer_address'))
    ->save();

$tablequote = $this->getTable('sales/quote_address');
$installer->run("
ALTER TABLE  $tablequote ADD  shiphawk_location_type varchar(255) NOT NULL
");

$tablequote = $this->getTable('sales/order_address');
$installer->run("
ALTER TABLE  $tablequote ADD  shiphawk_location_type varchar(255) NOT NULL
");

$installer->endSetup();