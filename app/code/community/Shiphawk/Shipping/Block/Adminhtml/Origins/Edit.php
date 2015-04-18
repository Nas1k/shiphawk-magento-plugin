<?php
class Shiphawk_Shipping_Block_Adminhtml_Origins_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    protected function _construct()
    {
        $this->_blockGroup = 'shiphawk_shipping';
        $this->_mode = 'edit';
        $this->_controller = 'adminhtml_origins';

        $faq_id = (int)$this->getRequest()->getParam($this->_objectId);

        $faq = Mage::getModel('shiphawk_shipping/origins')->load($faq_id);
        Mage::register('current_origins', $faq);

        $this->_removeButton('reset');
    }

    public function getHeaderText()
    {
        $faq = Mage::registry('current_origins');
        if ($faq->getId()) {
            return Mage::helper('shiphawk_shipping')->__("Edit origins '%s'", $this->escapeHtml($faq->getShiphawkOriginTitle()));
        } else {
            return Mage::helper('shiphawk_shipping')->__("Add new origins");
        }
    }
}
