<?php
$installer = $this;

$io = new Varien_Io_File();
$io->checkAndCreateFolder(Mage::getBaseDir('media').DS.'shiphawk'.DS.'bol');

$installer->startSetup();