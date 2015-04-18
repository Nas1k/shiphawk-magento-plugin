<?php
class Shiphawk_Shipping_Model_Product_Attribute_Source_Location
    extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Retrieve all attribute options
     *
     * @return array
     */
    public function getAllOptions()
    {
            $this->_options = array(
                array(
                    'label' => Mage::helper('catalog')->__('commercial'),
                    'value' => Mage::helper('catalog')->__('commercial')
                ),
                array(
                    'label' => Mage::helper('catalog')->__('residential'),
                    'value' => Mage::helper('catalog')->__('residential')
                )
            );

        return $this->_options;
    }
}
