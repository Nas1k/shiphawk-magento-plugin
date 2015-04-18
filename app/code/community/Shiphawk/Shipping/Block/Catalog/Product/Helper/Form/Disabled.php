<?php
class Shiphawk_Shipping_Block_Catalog_Product_Helper_Form_Disabled extends Varien_Data_Form_Element_Text
{
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        return $html."  <script>
        				$('".$this->getHtmlId()."').hide();
        				</script>";


    }

}