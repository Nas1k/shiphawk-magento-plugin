document.observe("dom:loaded", function() {
    function insertAfter(referenceNode, newNode) {
        referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
    }

    function updateInput() {
        var shiphawk_shipping_origins = document.getElementById("shiphawk_shipping_origins");
        var is_mass_action = 0;

        // check is it mass edit attribute action
        if (document.URL.indexOf('catalog_product_action_attribute') > 0) {
            is_mass_action = 1;
        }
        var url = 'shiphawk/index/origins';

        url = baseMagentoUrl + url;
        var parameters = {
            origin_id: shiphawk_shipping_origins.value,
            is_mass_action : is_mass_action
        };

        new Ajax.Request(url, {
            method: 'post',
            parameters: parameters,
            onSuccess: function(transport)  {

                responce_html  = JSON.parse(transport.responseText);

                var el = document.createElement("div");

                el.id = "origins_select";

                el.update(responce_html);

                shiphawk_shipping_origins.parentNode.replaceChild(el, shiphawk_shipping_origins);

            },
            onLoading:function(transport)
            {

            }
        });
    }

    updateInput();

    var origin_fields = new Array($('shiphawk_origin_firstname'), $('shiphawk_origin_lastname'), $('shiphawk_origin_addressline1'),
        $('shiphawk_origin_city'), $('shiphawk_origin_state'), $('shiphawk_origin_zipcode'), $('shiphawk_origin_phonenum'),
        $('shiphawk_origin_email'), $('shiphawk_origin_location'));

    function setOriginRequiredFields() {
            origin_fields.forEach(function(item, i, arr) {
            //item.addClassName('required-entry');
                item.observe('keyup', function(event){
                    if($('shiphawk_shipping_origins').value == '') {

                        _addRequiredClass( origin_fields );
                    }
                });

            });

    }

    function _addRequiredClass( origin_fields ) {

        origin_fields.forEach(function(item, i, arr) {
            if(!item.hasClassName('required-entry')){
                item.addClassName('required-entry');
            }
        });
    }

    function _removeRequiredClass( origin_fields ) {

        origin_fields.forEach(function(item, i, arr) {
            if(item.hasClassName('required-entry')){
                item.removeClassName('required-entry');
            }
        });
    }

    function _checkIfAllRequeredEmpty( origin_fields ) {
        var k = 0;
        origin_fields.forEach(function(item, i, arr) {
            if(item.value){
                k = k + 1;
            }
        });

        if ((k != 9)&&(k>0)) {
            _addRequiredClass( origin_fields );
        }else{
            _removeRequiredClass( origin_fields );
        }
    }

    origin_fields.forEach(function(item) {
        item.onchange = function(){
            _checkIfAllRequeredEmpty( origin_fields );
        };
    });

    //setOriginRequiredFields();
    _checkIfAllRequeredEmpty( origin_fields );



    var el = document.createElement("div");

    el.id = "type_product";
    var shiphawk_type_of_product = document.getElementById("shiphawk_type_of_product");

    insertAfter(shiphawk_type_of_product, el);

    var typeloader;
    $('shiphawk_type_of_product').observe('keyup', function(event){
        clearTimeout(typeloader);
        typeloader = setTimeout(function(){ respondToClick(event); }, 750);
    });

    function respondToClick(event) {

        var element = event.element();

        var minlength = 3;

        var url = 'shiphawk/index/search';

        url = baseMagentoUrl + url;
        var parameters = {
            search_tag: element.value
        };

        if(element.value.length >= minlength  ) {
            new Ajax.Request(url, {
                method: 'post',
                parameters: parameters,
                onSuccess: function(transport)  {

                    responce_html  = JSON.parse(transport.responseText);

                    if(responce_html.shiphawk_error) {
                        alert(responce_html.shiphawk_error);
                    }else{
                        if(responce_html.responce_html) {
                            $('type_product').update(responce_html.responce_html);
                            $('type_product').show();
                        }
                    }

                },
                onLoading:function(transport)
                {
                }
            });
        }
    }

});
    function setItemid(el) {
        $('shiphawk_type_of_product').value = el.innerHTML;

        if ($('shiphawk_type_of_product_value').disabled == true) {
            $('shiphawk_type_of_product_value').disabled = false;
        }

        $('shiphawk_type_of_product_value').value = el.id;

        $('type_product').hide();
    }