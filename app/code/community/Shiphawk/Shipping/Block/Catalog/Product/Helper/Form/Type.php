<?php
class Shiphawk_Shipping_Block_Catalog_Product_Helper_Form_Type extends Varien_Data_Form_Element_Text
{
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();

        $html .= '<style>
                  .type_link:hover {
                    text-decoration: underline;
                    cursor: pointer;
                  }
                  </style>';

        $html .= '<div id="type_product" style="position: absolute; z-index: 99; background-color: #ffffff" ></div>';
        return $html."  <script>

                            var typeloader;
                            $('".$this->getHtmlId()."').observe('keyup', function(event){
                            clearTimeout(typeloader);
                            typeloader = setTimeout(function(){ respondToClick(event); }, 750);
                            });

                            function setIdValue(el) {

                                $('shiphawk_type_of_product').value = el.innerHTML;
                                $('shiphawk_type_of_product_value').value = el.id;
                                $('type_product').hide();
                            }

                            function respondToClick(event) {

                                var element = event.element();

                                var minlength = 3;

                               var url = '".$this->getTypeUrl()."';
                               var parameters = {
                                   search_tag: element.value
                               };

                               if(element.value.length >= minlength  ) {
                                   new Ajax.Request(url, {
                                       method: 'post',
                                       parameters: parameters,
                                       onSuccess: function(transport)  {
                                           $('type_product').update(transport.responseText);
                                           $('type_product').show();
                                       },
                                       onLoading:function(transport)
                                       {
                                       }
                                   });
                               }
                            }
        				</script>";

    }

    public function getTypeUrl() {
        return Mage::getUrl('shiphawk/index/search');
    }

}