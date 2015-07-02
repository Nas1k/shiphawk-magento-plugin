Event.observe(window, "load", function(){
    if ($('order-shipping-method-choose') !== null) {
        $('order-shipping-method-choose').hide();
        $('order-shipping-method-summary-hidden').show();
    }
});