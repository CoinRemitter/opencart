var time_interval = null;
$(document).ready(function(){

	$(".copyToClipboard").click(function() {
		$(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
	    var value = $(this).attr('data-copy-detail');
	    var $temp = $("<input>");
	    $("body").append($temp);
	    $temp.val(value).select();
	    document.execCommand("copy");
	    $temp.remove();
	});
	time_interval = setInterval(dateTimer,1000);
	check_payment_history();
	check_payment_interval = setInterval(check_payment_history,5000);
	
});

function dateDiff(date,now_time) {
 	var d2 = new Date(Date.parse(now_time)).getTime();
 	var d1 = new Date(Date.parse(date)).getTime();
 	var date_diff = d2 - d1;

 	var years = Math.floor( ( date_diff) / 1000 / 60 / 60 / 24 / 30 / 12);
 	if(years > 0)
    	return years+" year(s) ago";
 	var months = Math.floor( ( date_diff) / 1000 / 60 / 60 / 24 / 30);
 	if(months > 0)
    	return months+" month(s) ago";
 	var days = Math.floor( ( date_diff) / 1000 / 60 / 60 / 24);
 	if(days > 0)
    	return days+" day(s) ago";
 	var hours = Math.floor( ( date_diff) / 1000 / 60 / 60 );
 	if(hours > 0)
	    return hours+" hour(s) ago";
 	var minutes = Math.floor( ( date_diff) / 1000 / 60 );
 	if(minutes > 0)
    	return minutes+" minute(s) ago";
 	var seconds = Math.floor( ( date_diff) / 1000 );
 	if(seconds > 0)
    	return seconds+" second(s) ago";

    return 'A moment ago';
}
function dateTimer() {
 	if($("#expire_on").val() != ''){
    	var current = getUTCTime();
    	var expire = new Date($("#expire_on").val()).getTime();
    	var date_diff = expire - current;
    	var hours = Math.floor(date_diff / (1000 * 60 * 60));
    	var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
    	var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);
    	if(hours < 0 && minutes < 0 && seconds < 0){
       		var enc_order_id = $("#enc_order_id").val();
       		funExpire(enc_order_id);
       		clearInterval(time_interval);
       		return;
    	}else{
       		$("#ehours").html(('0' + hours).slice(-3));
       		$("#eminutes").html(('0' + minutes).slice(-2));
       		$("#eseconds").html(('0' + seconds).slice(-2));
    	}
 	}
}


function check_payment_history(){
	var address = $('#address').val();
	$.ajax({
		url:'index.php?route=extension/coinremitter/module/coinremitter_invoice|payment_history',
		type:'post',
		data:{address},
		success:function(res){
			if(res.flag == 1){
				var enc_order_id = res.enc_order_id;
				if(res.is_success == 1){
					clearInterval(check_payment_interval);
					window.location.replace("index.php?route=extension/coinremitter/module/coinremitter_invoice|success&order_id=" + enc_order_id);
				}else{
					//update invoice
					var res_data = res.data;
					if(res_data.is_simply_display_detail == 1){
						clearInterval(check_payment_interval);
						clearInterval(time_interval);
						$("#timerStatus").empty();
						$("#paymentStatus").empty();
						$("#timerStatus").append("<span>Payment Status : " + res_data.order_status + "</span>");
					}
					if(res_data.nopayment == 1){
						if($('#paymentStatus').is(':empty')){
							$("#timerStatus").empty();
		                  	clearInterval(time_interval);
		                  	$("#timerStatus").append("<span>Payment Status : " + res_data.order_status + "</span>");
		                  	$("#paymentStatus").append("<div></div>");	
						}
	               	}else if(res_data.nopayment == 0){
	                  	if ($('#timerStatus').is(':empty')){
	                     	$("#timerStatus").append("<span>Your order expired in</span>");
	                     	$("#timerStatus").append('<ul><li><span id="ehours">00</span></li><li><span id="eminutes">00</span></li><li><span id="eseconds">00</span></li></ul>');
	                  	}
	               	}
					$("#expire_on").val(res_data.expire_on);
					$("#paid-amt").text(res_data.total_paid + " " + res_data.coin);
               		$("#pending-amt").text(res_data.pending + " " + res.data.coin);

               		var paymenthistory = "";
               		var payment_data = res_data.payment_data;
	               	if(payment_data.length > 0){
	               		
	                  	$.each(payment_data, function( index, payment ) {
	                     	var confirmations = '';
	                     	if(payment.confirmations >= 3){
	                        	confirmations = '<div class="cr-plugin-history-ico" title="Payment Confirmed">' +
	                        						'<i class="fa fa-check"></i>' +
	                      						'</div>';
	                     	}else{
	                        	confirmations = '<div class="cr-plugin-history-ico" style="background-color: #f3a638;" title="'+payment.confirmations+' confirmation(s)">' +
	                        						'<i class="fa fa-exclamation"></i>' +
	                        					'</div>';
	                     	}
	                     	paymenthistory += '<div class="cr-plugin-history-box">' +
	                     						 '<div class="cr-plugin-history">' + confirmations +
	                     							 '<div class="cr-plugin-history-des">' +
	                     								 '<span>' +
	                     									 '<a href="'+payment.explorer_url+'" target="_blank">'+payment.txId+'</a>' +
	                     								 '</span>' +
	                     								 '<p>'+payment.paid_amount+' '+payment.coin+'</p>' +
	                     							 '</div>' +
	                     							 '<div class="cr-plugin-history-date">' +
	                     								 '<span title="'+payment.paid_date+' (UTC)">'+dateDiff(payment.paid_date,payment.now_time)+'</span>' +
	                     							 '</div>' +
	                     						 '</div>' +
	                     					 '</div>';

	                  	});
	               	} else {
	                  	paymenthistory='<div class="cr-plugin-history-box">' +
	                  						'<div class="cr-plugin-history">' +
	                  							'<div class="cr-plugin-history-des" style="text-align: center;">' +
	                  								'<p>'+res.msg+'</p>' +
	                  							'</div>' +
	                  						'</div>' +
	                  					'</div>';
	               	}
	               	$("#cr-plugin-history-list").html(paymenthistory);
				}
			}else{
				clearInterval(check_payment_interval);
				clearInterval(time_interval);
			}
		}
	});	
}

function funExpire(enc_order_id) {
 	window.location.replace("index.php?route=extension/coinremitter/module/coinremitter_invoice|expired&order_id=" + enc_order_id);
}


function getUTCTime(){
    var tmLoc = new Date();
    return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
}

