<?php
class Shiphawk_Shipping_Adminhtml_OriginsController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_title($this->__('Shipping Origins'));

        $this->loadLayout();
        $this->_setActiveMenu('shiphawk_shipping');
        $this->_addBreadcrumb(Mage::helper('shiphawk_shipping')->__('Shipping Origins'), Mage::helper('shiphawk_shipping')->__('Shipping Origins'));
        $this->renderLayout();
    }

    public function newAction()
    {
        $this->_title($this->__('Add new Origins'));
        $this->loadLayout();
        $this->_setActiveMenu('shiphawk_shipping');
        $this->_addBreadcrumb(Mage::helper('shiphawk_shipping')->__('Add new Origins'), Mage::helper('shiphawk_shipping')->__('Add new Origins'));
        $this->renderLayout();
    }

    public function editAction()
    {
        $this->_title($this->__('Edit Origins'));

        $this->loadLayout();
        $this->_setActiveMenu('shiphawk_shipping');
        $this->_addBreadcrumb(Mage::helper('shiphawk_shipping')->__('Edit Origins'), Mage::helper('shiphawk_shipping')->__('Edit Origins'));
        $this->renderLayout();
    }

    public function deleteAction()
    {
        $tipId = $this->getRequest()->getParam('id', false);

        try {
            Mage::getModel('shiphawk_shipping/origins')->setId($tipId)->delete();


            Mage::dispatchEvent('origins_delete_after', array('id'=>$tipId));

            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('shiphawk_shipping')->__('origins successfully deleted'));

            return $this->_redirect('*/*/');
        } catch (Mage_Core_Exception $e){
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($this->__('Somethings went wrong'));
        }

        $this->_redirectReferer();
    }

    public function saveAction()
    {
        $data = $this->getRequest()->getPost();

        foreach ($data as $key => $value)
        {
            if (is_array($value))
            {
                $data[$key] = implode(',',$this->getRequest()->getParam($key));
            }
        }

        if (!empty($data)) {
            try {
                $origins = Mage::getModel('shiphawk_shipping/origins')
                    ->setData($data)
                    ->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('shiphawk_shipping')->__('origins successfully saved'));
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::getSingleton('adminhtml/session')->addError($this->__('Somethings went wrong'));
            }
        }
        return $this->_redirect('*/*/');
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('shiphawk_shipping/adminhtml_origins_grid')->toHtml()
        );
    }

    public function massDeleteAction()
    {
        $answersIds = $this->getRequest()->getParam('origins');
        if(!is_array($answersIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select origin(s).'));
        } else {
            try {
                $answer = Mage::getModel('shiphawk_shipping/origins');
                foreach ($answersIds as $answerId) {
                    $answer
                        ->load($answerId)
                        ->delete();

                    Mage::dispatchEvent('origins_delete_after', array('id'=>$answerId));
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', count($answersIds))
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    public function exportAction() {
        /**
         * Returns generated CSV file
         */

        $filename = 'origins.csv';
        $content = Mage::helper('shiphawk_shipping/origin')->generateQuestList();

        $this->_prepareDownloadResponse($filename, $content);

    }



}
