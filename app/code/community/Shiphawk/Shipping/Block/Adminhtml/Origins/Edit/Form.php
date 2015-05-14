<?php
class Shiphawk_Shipping_Block_Adminhtml_Origins_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $faq = Mage::registry('current_origins');
        $form = new Varien_Data_Form();
        $fieldset = $form->addFieldset('edit_origins', array(
            'legend' => Mage::helper('shiphawk_shipping')->__('Origins Details')
        ));

        if ($faq->getId()) {
            $fieldset->addField('id', 'hidden', array(
                'name'      => 'id',
                'required'  => true
            ));
        }


        $fieldset->addField('shiphawk_origin_title', 'text', array(
            'name'      => 'shiphawk_origin_title',
            'title'     => Mage::helper('shiphawk_shipping')->__('Title'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Title'),
            'required'  => true,
            'width'         => '400px'
        ));

        $fieldset->addField('shiphawk_origin_firstname', 'text', array(
            'name'      => 'shiphawk_origin_firstname',
            'title'     => Mage::helper('shiphawk_shipping')->__('First Name'),
            'label'     => Mage::helper('shiphawk_shipping')->__('First Name'),
            'required'  => true,
            'width'         => '400px'
        ));

        $fieldset->addField('shiphawk_origin_lastname', 'text', array(
            'name'      => 'shiphawk_origin_lastname',
            'title'     => Mage::helper('shiphawk_shipping')->__('Last Name'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Last Name'),
            'required'  => true,
            'width'         => '400px'
        ));

        $fieldset->addField('shiphawk_origin_addressline1', 'text', array(
            'name'      => 'shiphawk_origin_addressline1',
            'title'     => Mage::helper('shiphawk_shipping')->__('Address'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Address'),
            'required'  => true,
            'width'         => '400px'
        ));

        $fieldset->addField('shiphawk_origin_addressline2', 'text', array(
            'name'      => 'shiphawk_origin_addressline2',
            'title'     => Mage::helper('shiphawk_shipping')->__('Address'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Address Line 2'),
            'required'  => false,
            'width'         => '400px'
        ));

        $fieldset->addField('shiphawk_origin_city', 'text', array(
            'name'      => 'shiphawk_origin_city',
            'title'     => Mage::helper('shiphawk_shipping')->__('City'),
            'label'     => Mage::helper('shiphawk_shipping')->__('City'),
            'required'  => true,
            'width'         => '400px'
        ));

        $regions = $this->getRegions();

        $fieldset->addField('shiphawk_origin_state', 'select', array(
            'name'      => 'shiphawk_origin_state',
            'title'     => Mage::helper('shiphawk_shipping')->__('State'),
            'label'     => Mage::helper('shiphawk_shipping')->__('State'),
            'required'  => true,
            'values' => $regions,
            'width'         => '100%'
        ));

        $fieldset->addField('shiphawk_origin_zipcode', 'text', array(
            'name'      => 'shiphawk_origin_zipcode',
            'title'     => Mage::helper('shiphawk_shipping')->__('Zip Code'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Zip Code'),
            'required'  => true,
            'width'         => '400px'
        ));


        $fieldset->addField('shiphawk_origin_phonenum', 'text', array(
            'name'      => 'shiphawk_origin_phonenum',
            'title'     => Mage::helper('shiphawk_shipping')->__('Phone number'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Phone number'),
            'required'  => true,
            'width'         => '400px'
        ));

        $fieldset->addField('shiphawk_origin_location', 'select', array(
            'name'      => 'shiphawk_origin_location',
            'title'     => Mage::helper('shiphawk_shipping')->__('Location'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Location'),
            'required'  => true,
            'values' => array(
                    array( 'value'=>'commercial', 'label'=>'commercial'),
                    array( 'value'=>'residential', 'label'=>'residential'),
            ),
            'width'     => '400px'
        ));

        $fieldset->addField('shiphawk_origin_email', 'text', array(
            'name'      => 'shiphawk_origin_email',
            'title'     => Mage::helper('shiphawk_shipping')->__('Email'),
            'label'     => Mage::helper('shiphawk_shipping')->__('Email'),
            'required'  => false,
            'width'         => '400px'
        ));

        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('edit_form');
        $form->setAction($this->getUrl('*/*/save'));
        $form->setValues($faq->getData());

        $this->setForm($form);
    }

    public function getRegions($county_code = 'US') {
        $regions = array();
        $regionCollection = Mage::getModel('directory/region_api')->items($county_code);
        foreach($regionCollection as $region) {
            $regions[]= array(
                'value' => $region['code'],
                'label' => $region['name']
            );
        }
        return $regions;
    }




}
