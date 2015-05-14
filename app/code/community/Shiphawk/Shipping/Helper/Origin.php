<?php

class Shiphawk_Shipping_Helper_Origin extends
    Mage_Core_Helper_Abstract
{
    /**
     * Contains current collection
     * @var string
     */
    protected $_list = null;

    public function __construct()
    {
        $collection = Mage::getModel('shiphawk_shipping/origins')->getCollection()
        ;

        $this->setList($collection);
    }

    /**
     * Sets current collection
     * @param $query
     */
    public function setList($collection)
    {
        $this->_list = $collection;
    }

    /**
     * Returns indexes of the fetched array as headers for CSV
     * @param array $products
     * @return array
     */
    protected function _getCsvHeaders($questions)
    {
        $question = current($questions);
        $headers = array_keys($question->getData());

        return $headers;
    }

    /**
     * Generates CSV file with items's list according to the collection in the $this->_list
     * @return array
     */
    public function generateQuestList()
    {
        if (!is_null($this->_list)) {
            $items = $this->_list->getItems();
            if (count($items) > 0) {

                $io = new Varien_Io_File();
                $path = Mage::getBaseDir('var') . DS . 'export' . DS;
                $name = md5(microtime());
                $file = $path . DS . $name . '.csv';
                $io->setAllowCreateFolders(true);
                $io->open(array('path' => $path));
                $io->streamOpen($file, 'w+');
                $io->streamLock(true);

                $io->streamWriteCsv($this->_getCsvHeaders($items));
                foreach ($items as $question) {
                    $io->streamWriteCsv($question->getData());
                }

                return array(
                    'type'  => 'filename',
                    'value' => $file,
                    'rm'    => true // can delete file after use
                );
            }
        }
    }


}