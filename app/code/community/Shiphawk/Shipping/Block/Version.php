<?php

class Shiphawk_Shipping_Block_Version extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $ver = (Mage::getConfig()->getModuleConfig('Shiphawk_Shipping')->version);
        $html = 'You current config version is ' . $ver;

        return $html;
    }

}
