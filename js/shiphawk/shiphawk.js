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
        var myScript = document.getElementById('shiphawkjsfile');
        var myScriptSrc = myScript.getAttribute('src');
        myScriptSrc = myScriptSrc.substring(0,myScriptSrc.length - 23);

        url = myScriptSrc + url;

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

                //if mass edit disable input
            },
            onLoading:function(transport)
            {

            }
        });
    }

    updateInput();

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
        var myScript = document.getElementById('shiphawkjsfile');
        var myScriptSrc = myScript.getAttribute('src');
        myScriptSrc = myScriptSrc.substring(0,myScriptSrc.length - 23);

        var url = 'shiphawk/index/search';
        url = myScriptSrc + url;
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