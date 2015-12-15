<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright  Copyright (c) 2006-2014 X.commerce, Inc. (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Nominal items total
 * Collects only items segregated by isNominal property
 * Aggregates row totals per item
 */
class Shiphawk_Shipping_Model_Quote_Address_Total_Accessorials extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
        protected $_code = 'accessorials';


    protected $_calculator = null;

    public function __construct()
    {

        $this->_calculator  = Mage::getSingleton('tax/calculation');
    }

    /**
     * Invoke collector for nominal items
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Mage_Sales_Model_Quote_Address_Total_Nominal
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $address->setAccessorials(0);
        $address->setBaseAccessorials(0);

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        //$paymentMethod = $address->getQuote()->getPayment()->getMethod();
        //if ($paymentMethod) {
            //$amount = Mage::helper('paymentcharge')->getPaymentCharge($paymentMethod, $address->getQuote());

        $amount = 0.0;
        $helper         = Mage::helper('shiphawk_shipping');
        // we have no accessories on cart page
        $is_it_cart_page = $helper->checkIsItCartPage();
        if(!$is_it_cart_page) {
            $override_shipping_cost = Mage::getSingleton('core/session')->getData('shiphawk_override_cost');
            if(isset($override_shipping_cost)) {
                Mage::getSingleton('core/session')->unsetData('shiphawk_override_cost');
            }else{
                if($helper->checkIsAdmin()) {
                    $amount = Mage::getSingleton('core/session')->getData('admin_accessories_price');
                  //  Mage::getSingleton('core/session')->unsetData('admin_accessories_price');
                }else{
                    $amount = Mage::getSingleton('checkout/session')->getAccessoriesprice();
                   // Mage::getSingleton('checkout/session')->unsetData('accessoriesprice');
                }
            }

        }

        $address->setAccessorials($amount);
        $address->setBaseAccessorials($amount);

        /* clime: tax calculation start */
        $calc               = $this->_calculator;
        $store              = $address->getQuote()->getStore();
        $addressTaxRequest  = $calc->getRateRequest(
            $address->getQuote()->getShippingAddress(),
            $address->getQuote()->getBillingAddress(),
            $address->getQuote()->getCustomerTaxClassId(),
            $store
        );

        //$paymentTaxClass = Mage::getStoreConfig('payment/'.$paymentMethod.'/payment_tax_class');
        //$addressTaxRequest->setProductClassId($paymentTaxClass);

        $rate          = $calc->getRate($addressTaxRequest);
        $taxAmount     = $calc->calcTaxAmount($amount, $rate, false, true);
        $baseTaxAmount = $calc->calcTaxAmount($amount, $rate, false, true);

        //$address->setPaymentTaxAmount($taxAmount);
        //$address->setBasePaymentTaxAmount($baseTaxAmount);

        $ship_amount = $address->getShippingAmount();
        $address->setShippingAmount($ship_amount + $address->getAccessorials(), true);
        $address->setBaseShippingAmount($ship_amount + $address->getBaseAccessorials(), true);

        $address->setTaxAmount($address->getTaxAmount() + $taxAmount);
        $address->setBaseTaxAmount($address->getBaseTaxAmount() + $baseTaxAmount);
        /* clime: tax calculation end */
       // }
        //clime: tax added
        //$address->setGrandTotal($address->getGrandTotal() + $address->getPaymentCharge() + $address->getPaymentTaxAmount() + $address->getShippingAmount());
        //clime: tax added
        //$address->setBaseGrandTotal($address->getBaseGrandTotal() + $address->getBasePaymentCharge() + $address->getBasePaymentTaxAmount() + $address->getBaseShippingAmount());

        return $this;
    }

    /**
     * Fetch collected nominal items
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return Mage_Sales_Model_Quote_Address_Total_Nominal
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
     /*           $amt = $address->getAccessorials();
        $address->addTotal(array(
            'code'=>$this->getCode(),
            'title'=>'Custom shipping accessorials',
            'value'=> $amt
        ));
        return $this;*/
    }
}