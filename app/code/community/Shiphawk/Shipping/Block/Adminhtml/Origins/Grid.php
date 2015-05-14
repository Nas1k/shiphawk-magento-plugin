<?php
class Shiphawk_Shipping_Block_Adminhtml_Origins_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    protected function _construct()
    {
        $this->setId('originsGrid');
        $this->_controller = 'adminhtml_origins';
        $this->setUseAjax(true);

        $this->setDefaultSort('id');
        $this->setDefaultDir('desc');
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('shiphawk_shipping/origins')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header'        => Mage::helper('shiphawk_shipping')->__('ID'),
            'align'         => 'right',
            'width'         => '20px',
            'filter_index'  => 'id',
            'index'         => 'id'
        ));

        $this->addColumn('shiphawk_origin_title', array(
            'header'        => Mage::helper('shiphawk_shipping')->__('Title'),
            'align'         => 'right',

            'filter_index'  => 'shiphawk_origin_title',
            'index'         => 'shiphawk_origin_title'
        ));

        $this->addColumn('shiphawk_origin_firstname', array(
            'header'        => Mage::helper('shiphawk_shipping')->__('First name'),
            'align'         => 'left',
            'filter_index'  => 'shiphawk_origin_firstname',
            'index'         => 'shiphawk_origin_firstname',

        ));

        $this->addColumn('shiphawk_origin_lastname', array(
            'header'        => Mage::helper('shiphawk_shipping')->__('Last name'),
            'align'         => 'left',
            'filter_index'  => 'shiphawk_origin_lastname',
            'index'         => 'shiphawk_origin_lastname',

        ));

        $this->addColumn('shiphawk_origin_zipcode', array(
            'header'        => Mage::helper('shiphawk_shipping')->__('Origin Zip Code'),
            'align'         => 'left',
            'filter_index'  => 'shiphawk_origin_zipcode',
            'index'         => 'shiphawk_origin_zipcode',

        ));

        $this->addExportType('*/*/export', Mage::helper('shiphawk_shipping')->__('CSV'));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('origins');

        $this->getMassactionBlock()->addItem('delete', array(
            'label'    => Mage::helper('shiphawk_shipping')->__('Delete'),
            'url'      => $this->getUrl('*/*/massDelete'),
            'confirm'  => Mage::helper('shiphawk_shipping')->__('Are you sure?')
        ));


        return $this;
    }


    public function getRowUrl($faqs)
    {
        return $this->getUrl('*/*/edit', array(
            'id' => $faqs->getId(),
        ));
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }
}
