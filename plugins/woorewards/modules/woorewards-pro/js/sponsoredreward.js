jQuery(function($){$('.lws_editlist.sponsoredreward').on('updated deleted',function(){if($(this).find('.lws_editlist_row_editable').length>0)
$(this).find('.lws_editlist_item_add').hide();else $(this).find('.lws_editlist_item_add').show()});if($('.lws_editlist.sponsoredreward .lws_editlist_row.editable').length>0)
$('.lws_editlist.sponsoredreward .lws_editlist_item_add').hide();})