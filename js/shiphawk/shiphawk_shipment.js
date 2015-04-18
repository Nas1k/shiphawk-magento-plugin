function showPopup(sUrl) {
    oPopup = new Window({
        id:'popup_window',
        className: 'magento',
        url: sUrl,
        width: 450,
        height: 300,
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
    oPopup.setZIndex(100);
    oPopup.showCenter(true);
}

function closePopup() {
    Windows.close('popup_window');
}