define([
    'Magento_Ui/js/grid/columns/column',
    'jquery',
    'mage/template',
    'text!TcsCourier_Shipping/templates/grid/cells/order/cnnumber.html',
    'Magento_Ui/js/modal/modal'
], function (Column, $, mageTemplate, template) {
    'use strict';



	$(document).on("click","#TcsCouriershippinggencn #tracking",function(e){
		e.preventDefault();
		$(".modal-popup,.modals-overlay").remove();
	    $("body").removeClass("_has-modal");
		var cn = $(this).data('cn');
		var actionurl = $(this).data('actionurl');
		var order = $(this).data('order');

		var popup = '<div class="tracking-popup" style="background-color: #f8f8f869;height: 100vh;width: 100%;position: absolute;top: 0;display: flex;z-index: 999;"><div><div class="header"></div><div class="content"></div><div class="footer"></div></div></div>';
		$('body').append(popup);
	});


	$(document).on("click","#TcsCouriershippinggencn #cancel",function(e){
		e.preventDefault();
		$(".modal-popup,.modals-overlay").remove();
	    $("body").removeClass("_has-modal");
		var cn = $(this).data('cn');
		var actionurl = $(this).data('actionurl');
		var order = $(this).data('order');
		
		

		if( confirm('Are you sure to Void this CN# '+cn+' ?') ){
			
			var actionUrl = actionurl+'?order_id='+order+'&cn_number='+cn;
			$(this).html('Processing..').prop('disabled',true);
			$.ajax({
				url: actionUrl, 
				method: 'GET',
				success:function(res){
				  
					
						if(res.status == "1"){
							  console.log(res);
							$('.TcsCouriershippinggencn_'+order).remove();
						}else{
							$(this).html('Cancel').prop('disabled',false);
							alert("Error: "+cn+" not canceling...");
						}

					
					if( res.error ){
						alert('Error: '+res.error);
						$(this).html('Cancel').prop('disabled',false);
					}
				}
			});	
		}

		$("body").removeClass("_has-modal");
		return false;
	});


    $(document).on("click",".data-grid-html-cell",function(){
        $(".modal-popup,.modals-overlay").remove();
		$("body").removeClass("_has-modal");
    });
    
	$(document).on("click",".data-grid-html-cell div a",function(){
		$(".modal-popup._image-box,.modals-overlay").remove();
		$(".modal-popup,.modals-overlay").remove();
		$("body").removeClass("_has-modal");
		var width = $(window).width() * 0.66 ;
        var height = width * $(window).height() / $(window).width() ;
        window.open($(this).attr('href') , 'newwindow', 'width=' + width + ', height=' + height + ', top=' + (($(window).height() - height) / 2) + ', left=' + (($(window).width() - width) / 2));
		return false;	
	});


	
	$(document).on("click",".btn-bevoid",function(e){
		e.preventDefault();
	    $(".modal-popup,.modals-overlay").remove();
	    $("body").removeClass("_has-modal");
		var ths = $(this);
		var oid = ths.data('id');
		var cn = ths.attr('data-cnnumber');
		if( confirm('Are you sure to Void this CN# '+cn+' ?') ){
			
			var actionUrl = ths.data('actionurl')+'?order_id='+oid+'&cn_number='+ths.attr('data-cnnumber');
			ths.html('<span>Processing...</span>').prop('disabled',true);
			$.ajax({
				url:actionUrl, 
				method: 'GET',
				success:function(res){
				    console.log(res);
					if( res.result ){
    					var full = window.location.host;
    				    var protocol = window.location.protocol;
    				    var url = protocol+'//'+full+'/TcsCouriershipping/api/cnnumber';
					    $("#TcsCouriershippinggencn_"+oid).html('');
					    var btn = '<button class="button btn-TcsCourier" id="btn-submit-TcsCouriershipping" data-id="'+oid+'" data-actionurl="'+url+'"><span>TcsCourier</span></button>';
					    $("#TcsCouriershippinggencn_"+oid).html(btn);
					   // $("#TcsCouriershippingCN_"+oid).html('<button class="button btn-TcsCourier" id="TcsCouriershippingBtn_'+oid+'"><span>TcsCourier</span></button>');
					}
					if( res.error ){
						alert('Error: '+res.error);
						ths.html('<span>VOID</span>').prop('disabled',false);
					}
				}
			});	
		}
		$("body").removeClass("_has-modal");
		return false;
	});
	
	$(document).on("click","#btn-submit-TcsCouriershipping",function(){
	    $(".modal-popup,.modals-overlay").remove();
	    $("body").removeClass("_has-modal");
	    var ths = $(this);
	    var order_id = ths.attr('data-id');
	    var data_actionurl = ths.attr('data-actionurl');
	    console.log(order_id);
	    console.log(data_actionurl);
	    ths.html('Processing ...').prop('disabled',true);
	    	$.ajax({
				url:data_actionurl, 
				method: 'GET',
				data: { 
                    order_id: order_id
                  },
				success:function(res){
				    console.log(res);
					if( res.success ){
					    var print = "";
					    var tracking = "";
					    var logistic = res.response.result.logistic_type;
					    if(logistic == 'TcsCourier'){
					        print = "http://benefit.blue-ex.com/customerportal/inc/cnprnb.php?"+res.response.result.cn_id;
					        tracking = "http://bigazure.com/api/extensions/magento_tracking.php?trackno="+res.response.result.cn_id;
					    }
					    
					    if(logistic == 'mnp'){
					        print = "http://bigazure.com/api/extensions/magento_printmnp.php?trackno="+res.response.result.cn_id;
					        tracking = "http://bigazure.com/api/extensions/magento_trackingmnp.php?trackno="+res.response.result.cn_id;
					    }
					    
					    if(logistic == 'cc'){
					        print = "http://cod.callcourier.com.pk/Booking/AfterSavePublic/"+res.response.result.cn_id;
					        tracking = "http://bigazure.com/api/extensions/magento_trackingcc.php?trackno="+res.response.result.cn_id;
					    }
					    
					    var btnvoid = '<span class="button btn-bevoid" id="TcsCouriershippingBtn_'+order_id+'" data-actionurl="'+res.void_url+'" data-id="'+order_id+'" data-cnnumber="'+res.response.result.cn_id+'"><span style="color: #007bdb;cursor: pointer;">VOID</span></span>';
    					var text_cn = res.response.result.logistic_type+' | <a href="'+print+'">Print CN</a> </br> <a href="'+tracking+'">Tracking</a> | '.btnvoid;
    					
    					if(res.cn_text != ""){
    					    $("#TcsCouriershippinggencn_"+order_id).html('');
					        $("#TcsCouriershippinggencn_"+order_id).html(res.cn_text);
    					}
					}
					if( res.error ){
					//	errorObj.html(res.error).hide().fadeIn();
					}
				}
			});
	    
	});
	
	$(document).on("click",".btn-submit_TcsCouriershipping",function(){
		var ths = $(this), oId = $(this).data('id');
		var form = ths.closest('form');
		var error = false;
		
		var errorObj = form.find('span.error'); errorObj.hide(); 
		form.find('.required-entry').css({'border-color':'#ccc'});
		form.find('.required-entry').each(function(){
			var fld = $(this); var fldval = fld.val().trim();
			if( fldval == '' ){
				fld.css({'border-color':'#d00'}).hide().fadeIn();
				error = 'All fields are requied !';
			} else {
				if( fld.hasClass('check-number') ){
					fldval = fldval*1;
					if( isNaN(fldval) ){
						fld.css({'border-color':'#d00'}).hide().fadeIn();
						error = fld.parent().parent().find('label').text().replace(':','')+' field is invalid';
					}
				}
			}
		});
		
		if( error ){ 
			errorObj.html(error).hide().fadeIn();
		} else {
			ths.html('Processing ...').prop('disabled',true);
			var actionUrl = form.attr('action')+'?'+form.serialize();
			$.ajax({
				url:actionUrl, 
				method: 'GET',
				success:function(res){
				    console.log(res);
				    console.log(actionUrl);
					ths.html('Submit').prop('disabled',false);
					if( res.success ){
					    var print = "";
					    var tracking = "";
					    var logistic = res.response.result.logistic_type;
					    if(logistic == 'TcsCourier'){
					        print = "http://benefit.blue-ex.com/customerportal/inc/cnprnb.php?"+res.response.result.cn_id;
					        tracking = "http://bigazure.com/api/extensions/magento_tracking.php?trackno="+res.response.result.cn_id;
					    }
					    
					    if(logistic == 'mnp'){
					        print = "http://bigazure.com/api/extensions/magento_printmnp.php?trackno="+res.response.result.cn_id;
					        tracking = "http://bigazure.com/api/extensions/magento_trackingmnp.php?trackno="+res.response.result.cn_id;
					    }
					    
					    if(logistic == 'cc'){
					        print = "http://cod.callcourier.com.pk/Booking/AfterSavePublic/"+res.response.result.cn_id;
					        tracking = "http://bigazure.com/api/extensions/magento_trackingcc.php?trackno="+res.response.result.cn_id;
					    }
					    
					    var btnvoid = '<span class="button btn-bevoid" id="TcsCouriershippingBtn_'+oId+'" data-actionurl="'+res.void_url+'" data-id="'+oId+'" data-cnnumber="'+res.response.result.cn_id+'"><span style="color: #007bdb;cursor: pointer;">VOID</span></span>';
    					var text_cn = res.response.result.logistic_type+' | <a href="'+print+'">Print CN</a> </br> <a href="'+tracking+'">Tracking</a> | '.btnvoid;
    					
    					if(res.cn_text != ""){
    					    $("#TcsCouriershippinggencn_"+oId).html('');
					        $("#TcsCouriershippinggencn_"+oId).html(res.cn_text);
    					}
				//	$("#TcsCouriershippingBtn_"+oId).replaceWith('<button class="button btn-bevoid" id="TcsCouriershippingBtn_'+oId+'" data-actionurl="'+res.void_url+'" data-id="'+oId+'" data-cnnumber="'+res.cn_id+'"><span>VOID</span></button>');
								
						if( res.oms != 1 ){
						    if(res.cn_text != ""){
						        $("#TcsCouriershippinggencn_"+oId).html('');
					            $("#TcsCouriershippinggencn_"+oId).html(res.cn_text);
						    }
					//	$("#TcsCouriershippingBtn_"+oId).replaceWith('<button class="button btn-bevoid" id="TcsCouriershippingBtn_'+oId+'" data-actionurl="'+res.void_url+'" data-id="'+oId+'" data-cnnumber="'+res.cn_id+'"><span>VOID</span></button>');
						}else 
						//	$("#TcsCouriershippingBtn_"+oId).hide();
						
						ths.closest('.modal-inner-wrap').find('.action-close').trigger('click');						
					}
					if( res.error ){
						errorObj.html(res.error).hide().fadeIn();
					}
				}
			});
		}
	});
	 	
    return Column.extend({
        defaults: {
            bodyTmpl: 'ui/grid/cells/html',
            fieldClass: { 'data-grid-html-cell': true }
        },
        gethtml: function (row) {
            return row[this.index + '_html'];
        },
        getFormaction: function (row) {
            return row[this.index + '_formaction'];
        },
        getOrderid: function (row) {
            return row[this.index + '_orderid'];
        },
        getLabel: function (row) {
            return row[this.index + '_html']
        },
        getTitle: function (row) {
            return row[this.index + '_title']
        },
		getCities: function (row) {
            return row[this.index + '_cities']
        },
		getSubmitlabel: function (row) {
            return row[this.index + '_submitlabel']
        },
		getTotalamount: function (row) {
            return row[this.index + '_totalamount']
        },
        preview: function (row) {
			var cityList = this.getCities(row);
			var modalHtml = mageTemplate(template, {
				html: this.gethtml(row), 
				title: this.getTitle(row), 
				label: this.getLabel(row), 
				formaction: this.getFormaction(row),
				orderid: this.getOrderid(row),
				cities: cityList,
				totalamount: this.getTotalamount(row),
				submitlabel: this.getSubmitlabel(row), 
			 	linkText: $.mage.__('Go to Details Page')
           });
            var previewPopup = $('<div/>').html(modalHtml);
            previewPopup.modal({
                title: this.getTitle(row),
                innerScroll: true,
                modalClass: '_image-box',
                buttons: []}).trigger('openModal');
			
			setTimeout(function(){ $(".boxCityList").replaceWith(cityList);	},100);
			setTimeout(function(){ $(".boxCityList").replaceWith(cityList);	},700);
			 
	    },
        getFieldHandler: function (row) {
            return this.preview.bind(this, row);
        }
    });
});