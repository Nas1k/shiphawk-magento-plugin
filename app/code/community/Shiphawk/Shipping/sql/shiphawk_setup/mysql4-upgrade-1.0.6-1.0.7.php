<?php
$installer = $this;

$io = new Varien_Io_File();
$io->checkAndCreateFolder(Mage::getBaseDir('var').DS.'shiphawk'.DS.'import');

$installer->startSetup();