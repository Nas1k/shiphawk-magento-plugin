<?php
class Shiphawk_Shipping_Adminhtml_ImportController extends Mage_Adminhtml_Controller_Action
{

    public function importAction() {

        $fileimport = Mage::getStoreConfig('carriers/shiphawk_shipping/file_import');

        $urlpath = Mage::getBaseDir('var') . DS . 'shiphawk' . DS.'import' . DS . $fileimport;

        $row = 1;
        if (($handle = fopen($urlpath, "r")) !== FALSE) {

            while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
                if($row == 1){ $row++; continue; }

                $row++;
                try {
                    list($id, $shiphawk_origin_firstname, $shiphawk_origin_lastname, $shiphawk_origin_addressline1, $shiphawk_origin_addressline2, $shiphawk_origin_city, $shiphawk_origin_state,
                        $shiphawk_origin_zipcode, $shiphawk_origin_phonenum, $shiphawk_origin_location, $shiphawk_origin_email, $shiphawk_origin_title) = $data;
                    $answer = Mage::getModel('shiphawk_shipping/origins');
                    $answer->setShiphawkOriginFirstname($shiphawk_origin_firstname)
                        ->setShiphawkOriginLastname($shiphawk_origin_lastname)
                        ->setShiphawkOriginAddressline1($shiphawk_origin_addressline1)
                        ->setShiphawkOriginAddressline2($shiphawk_origin_addressline2)
                        ->setShiphawkOriginCity($shiphawk_origin_city)
                        ->setShiphawkOriginState($shiphawk_origin_state)
                        ->setShiphawkOriginZipcode($shiphawk_origin_zipcode)
                        ->setShiphawkOriginPhonenum($shiphawk_origin_phonenum)
                        ->setShiphawkOriginLocation($shiphawk_origin_location)
                        ->setShiphawkOriginEmail($shiphawk_origin_email)
                        ->setShiphawkOriginTitle($shiphawk_origin_title)
                        ->save();


                    Mage::app()->getResponse()->setBody('Import successful');

                } catch (Mage_Core_Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                    Mage::app()->getResponse()->setBody($e->getMessage());
                } catch (Exception $e) {
                    Mage::logException($e);

                    Mage::app()->getResponse()->setBody($e->getMessage());
                }
            }
            fclose($handle);

        }else{
            Mage::app()->getResponse()->setBody('Please upload file');
        }


    }

    protected function _uploadImportFile() {
        if ($data = $this->getRequest()->getPost()) {

            if(isset($_FILES['filename']['name']) && $_FILES['filename']['name'] != '') {
                try {
                    /* Starting upload */
                    $uploader = new Varien_File_Uploader('filename');

                    // Any extention would work
                    $uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
                    $uploader->setAllowRenameFiles(false);

                    // Set the file upload mode
                    // false -> get the file directly in the specified folder
                    // true -> get the file in the product like folders
                    // (file.jpg will go in something like /media/f/i/file.jpg)
                    $uploader->setFilesDispersion(false);

                    // We set media as the upload dir
                    $path = Mage::getBaseDir('media') . DS ;
                    $uploader->save($path, $_FILES['filename']['name'] );

                } catch (Exception $e) {

                }

                //this way the name is saved in DB
                $data['filename'] = $_FILES['filename']['name'];
            }
        }
    }
}