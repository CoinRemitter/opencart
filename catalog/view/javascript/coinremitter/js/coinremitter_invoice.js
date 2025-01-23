var time_interval = null;
const ORDER_STATUS = {
	'pending': 0,
	'paid': 1,
	'under_paid': 2,
	'over_paid': 3,
	'expired': 4,
	'cancelled': 5,
}
$(document).ready(function () {

	$(".copyToClipboard").click(function () {
		$(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
		var value = $(this).attr('data-copy-detail');
		var $temp = $("<input>");
		$("body").append($temp);
		$temp.val(value).select();
		document.execCommand("copy");
		$temp.remove();
	});
	time_interval = setInterval(dateTimer, 1000);
	check_payment_history();
	check_payment_interval = setInterval(check_payment_history, 10000);

});

function dateDiff(date, now_time) {
	var d2 = new Date(Date.parse(now_time)).getTime()
	var d1 = new Date(Date.parse(date)).getTime();
	var date_diff = d2 - d1;
	var years = Math.floor((date_diff) / 1000 / 60 / 60 / 24 / 30 / 12);
	if (years > 0)
		return years + " year(s) ago";
	var months = Math.floor((date_diff) / 1000 / 60 / 60 / 24 / 30);
	if (months > 0)
		return months + " month(s) ago";
	var days = Math.floor((date_diff) / 1000 / 60 / 60 / 24);
	if (days > 0)
		return days + " day(s) ago";
	var hours = Math.floor((date_diff) / 1000 / 60 / 60);
	if (hours > 0)
		return hours + " hour(s) ago";
	var minutes = Math.floor((date_diff) / 1000 / 60);
	if (minutes > 0)
		return minutes + " minute(s) ago";
	var seconds = Math.floor((date_diff) / 1000);
	if (seconds > 0)
		return seconds + " second(s) ago";

	return 'A moment ago';
}
function dateTimer() {
	if ($("#expire_on").val() != '') {
		var current = getUTCTime();
		var expire = new Date($("#expire_on").val()).getTime();
		var date_diff = expire - current;
		var hours = Math.floor(date_diff / (1000 * 60 * 60));
		var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
		var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);
		if (hours < 0 && minutes < 0 && seconds < 0) {
			var enc_order_id = $("#enc_order_id").val();
			funExpire(enc_order_id);
			clearInterval(time_interval);
			return;
		} else {
			$("#ehours").html(('0' + hours).slice(-3));
			$("#eminutes").html(('0' + minutes).slice(-2));
			$("#eseconds").html(('0' + seconds).slice(-2));
		}
	}
}


function check_payment_history() {
	var address = $('#address').val();
	$.ajax({
		url: 'index.php?route=extension/coinremitter/module/coinremitter_invoice|check_payment',
		type: 'post',
		data: { address },
		success: function (res) {
			if (res.flag == 1) {
				var res_data = res.data;
				var enc_order_id = res_data.enc_order_id;
				$("#paid-amt").text(res_data.paid_amount + " " + res_data.coin_symbol);
				$("#pending-amt").text(res_data.pending_amount + " " + res_data.coin_symbol);
				if (res_data.status_code == ORDER_STATUS.paid || res_data.status_code == ORDER_STATUS.over_paid) {
					clearInterval(check_payment_interval);
					window.location.replace("index.php?route=extension/coinremitter/module/coinremitter_invoice|success&order_id=" + enc_order_id);
				} else {
					if (res_data.status_code == ORDER_STATUS.expired) {
						clearInterval(check_payment_interval);
						funExpire(enc_order_id);
					} else { // for pending and underpaid
						if (res_data.status_code == ORDER_STATUS.pending) {
							$("#expire_on").val(res_data.expire_on);
							if($("#timerStatus").html() == ''){
								$("#timerStatus").html('<ul><li><span id="ehours">00</span></li><li><span id="eminutes">00</span></li><li><span id="eseconds">00</span></li></ul>');
							}
						} else {
							$("#timerStatus").html("<span>Payment Status : " + res_data.status + "</span>");
						}
					}

				}
				var paymenthistory = '<div class="cr-plugin-history-box">' +
					'<div class="cr-plugin-history">' +
					'<div class="cr-plugin-history-des" style="text-align: center;">' +
					'<p>No Payment Found</p>' +
					'</div>' +
					'</div>' +
					'</div>';
				var payment_data = res_data.transactions;

				if (Object.keys(payment_data).length > 0) {
					paymenthistory = '';
					for (const key in payment_data) {
						if (Object.prototype.hasOwnProperty.call(payment_data, key)) {
							const payment = payment_data[key];
							var confirmations = '<div class="cr-plugin-history-ico" style="background-color: #f3a638;" title="' + payment.confirmations + ' confirmation(s)">' +
								'<i class="fa fa-exclamation"></i>' +
								'</div>';
							if (payment.status_code == 1) {
								confirmations = '<div class="cr-plugin-history-ico" title="Payment Confirmed">' +
									'<i class="fa fa-check"></i>' +
									'</div>';
							}

							paymenthistory += '<div class="cr-plugin-history-box">' +
								'<div class="cr-plugin-history">' + confirmations +
								'<div class="cr-plugin-history-des">' +
								'<span>' +
								'<a href="' + payment.explorer_url + '" target="_blank">' + payment.txid.slice(0,16) + '...</a>' +
								'</span>' +
								'<p>' + payment.amount + ' ' + res_data.coin_symbol + '</p>' +
								'</div>' +
								'<div class="cr-plugin-history-date">' +
								'<span title="' + payment.date + ' (UTC)">' + dateDiff(payment.date, res_data.now_time) + '</span>' +
								'</div>' +
								'</div>' +
								'</div>';
						}
					}
				}
				$("#cr-plugin-history-list").html(paymenthistory);
			} else {
				clearInterval(check_payment_interval);
				clearInterval(time_interval);
			}
		}
	});
}

function funExpire(enc_order_id) {
	window.location.replace("index.php?route=extension/coinremitter/module/coinremitter_invoice|expired&order_id=" + enc_order_id);
}


function getUTCTime() {
	var tmLoc = new Date();
	return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
}

