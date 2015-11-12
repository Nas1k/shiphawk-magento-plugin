<?php

class Shiphawk_Checkout_Model_Cart extends Mage_Checkout_Model_Cart
{
    /**
     * Save cart
     *
     * @return Mage_Checkout_Model_Cart
     */
    public function save($includeRates = false)
    {
        Mage::dispatchEvent('checkout_cart_save_before', array('cart'=>$this));

        $calcRateOnCartChange = Mage::helper('shiphawk_shipping')->getCalcRateOnCartChange();

        if($calcRateOnCartChange) {
            parent::save();
        }
        else {

            //retrieving previous subtotal form cookie
            $previousCartSubTotal = Mage::getSingleton('core/session')->getData('previousCartSubTotal', true);

            $this->getQuote()->getBillingAddress();
            $this->getQuote()->getShippingAddress();
            $this->getQuote()->collectTotals();

            if ($includeRates) {

                $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
            } else if ($previousCartSubTotal != $this->getQuote()->getSubtotal()) {

                $this->getQuote()->getShippingAddress()->removeAllShippingRates();
                $this->getQuote()->getShippingAddress()->setShippingMethod(false)->setShippingDescription('')->setCollectShippingRates(false);
                //$this->getQuote()->getShippingAddress()->setShippingMethod('')->setShippingDescription('');
            }

            $this->getQuote()->collectTotals();
            $this->getQuote()->save();
            $this->getCheckoutSession()->setQuoteId($this->getQuote()->getId());
            /**
             * Cart save usually called after changes with cart items.
             */

            Mage::getSingleton('core/session')->setData('previousCartSubTotal', $this->getQuote()->getSubtotal());

            Mage::dispatchEvent('checkout_cart_save_after', array('cart' => $this));
            return $this;
        }
    }
}