function showPopup(sUrl) {
    oPopup = new Window({
        id:'popup_window',
        className: 'magento',
        url: sUrl,
        width: 480,
        height: 390,
        minimizable: false,
        maximizable: false,
        showEffectOptions: {
            duration: 0.4
        },
        hideEffectOptions:{
            duration: 0.4
        },
        destroyOnClose: true
    });
    oPopup.setZIndex(999);
    oPopup.showCenter(true);
}

function closePopup() {
    Windows.close('popup_window');
}

function reBook(postUrl, order_id, sUrl) {
    var parameters = {
        order_id: order_id,
        sUrl: sUrl
    };

    new Ajax.Request(postUrl, {
        method: 'post',
        parameters: parameters,
        onSuccess: function(transport)  {
            responce_html  = JSON.parse(transport.responseText);
            if(responce_html.sUrl) {
                $('loading-mask').hide();
                showPopup(responce_html.sUrl);
            }else{
                $('loading-mask').hide();
                location.reload();
            }
        },
        onComplete:function(request, json) {
            responce_html  = JSON.parse(transport.responseText);
            $('loading-mask').hide();
        }
    });
}