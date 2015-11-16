<?php
class Shiphawk_Shipping_Block_Adminhtml_Separator extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {

        return $this->_toHtml();
    }

    public function render(Varien_Data_Form_Element_Abstract $element){

        return '<tr><td colspan="2"><hr style="margin: 25px 0px;"/></td></tr>';
    }
}