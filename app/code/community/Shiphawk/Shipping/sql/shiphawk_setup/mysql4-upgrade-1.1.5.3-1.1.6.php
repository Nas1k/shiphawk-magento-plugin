<?php
$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$installer->startSetup();

$installer->updateAttribute('catalog_product', 'shiphawk_item_is_packed', 'frontend_label', 'Is Product Already Packed?');


$installer->endSetup();