<?php
$installer = $this;
$installer->startSetup();

$installer->run("

-- DROP TABLE IF EXISTS {$this->getTable('shiphawk_shipping/origins')};
CREATE TABLE {$this->getTable('shiphawk_shipping/origins')} (
  `id` int(11) unsigned NOT NULL auto_increment,
  `shiphawk_origin_firstname` varchar(250) NULL default '',
  `shiphawk_origin_lastname` varchar(250) NULL default '',
  `shiphawk_origin_addressline1` varchar(250) NULL default '',
  `shiphawk_origin_addressline2` varchar(250) NULL default '',
  `shiphawk_origin_city` varchar(250) NULL default '',
  `shiphawk_origin_state` varchar(250) NULL default '',
  `shiphawk_origin_zipcode` varchar(250) NULL default '',
  `shiphawk_origin_phonenum` varchar(250) NULL default '',
  `shiphawk_origin_location` varchar(250) NULL default '',
  `shiphawk_origin_email` varchar(250) NULL default '',
  `shiphawk_origin_title` varchar(250) NULL default '',

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup();