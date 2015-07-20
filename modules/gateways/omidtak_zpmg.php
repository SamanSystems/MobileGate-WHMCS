<?php
/**
 * Plugin Name: Zarinpal Mobile Gate Module For Whmcs
 * Plugin URI: http://omidtak.ir
 * Version: 1.0 
 * Release Date : 2014 20 December
 * Author: Omid Aran
 * Author Email: info[at]omidtak[dot]ir
 */

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");
	
function omidtak_zpmg_config() 
{
    return array(
		 'FriendlyName' => array('Type' => 'System', 'Value' => 'پرداخت موبایلی | زرین پال'),
		 'MerchantID' => array('FriendlyName' => 'شناسه درگاه', 'Type' => 'text', 'Size' => '50'),
		 'Time' => array('FriendlyName' => 'چک کردن خودکار', 'Type' => 'text', 'Size' => '20', 'Description' => 'چک کردن پرداخت کاربر بصورت خودکار (زمان وارد شده باید بر حسب ثانیه باشد)'),		 
		 'Currencies' => array('FriendlyName' => 'واحد پول', 'Type' => 'dropdown', 'Options' => 'Rial,Toman')
	);	
}

function omidtak_zpmg_link($params) 
{
	$invoiceid = $params['invoiceid'];	
	if($_POST['do'] == 'pay')
	{
		$amount = strtok($params['amount'], '.');				
		$amount = ($params['Currencies'] == 'Toman') ? $amount : $amount / 10;
		$callback = $params['systemurl'].'/modules/gateways/callback/omidtak_zpmg.php?invoiceid='.$invoiceid.'&amount='.$params['amount'].'&amount2='.$amount;
		$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 
		
		$result = $client->PaymentRequest(
							array(
									'MerchantID'  => $params['MerchantID'],
									'Amount' 	  => $amount,
									'Description' => 'پرداخت فاکتور '.$invoiceid,
									'CallbackURL' => $callback
							)
		);
		
		if($result->Status == 100)
		{	
			$time = ($params['Time'] > 1) ? $params['Time'] : 10;	
			$order_id = ltrim($result->Authority,0);
			$ussd = '*720*97*2*'.$order_id.'#';
			$message = '<img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=tel:%2A770%2A97%2A2%2A'.$order_id.'%23&choe=UTF-8&chld=Q|0" title="'.$ussd.'" /></br>';
			$message .= 'کاربر گرامی برای پرداخت کافیست کد زیر را </br>';
			$message .= $order_id.'#*2*97*770*</br>';
			$message .= 'با تلفن همراه خود شماره گیری نمایید.</br>';
			$message .= 'سیستم بصورت خودکار تراکنش شما را چک خواهد کرد و درصورت پرداخت به صفحه تحویل محصول هدایت خواهید شد .</br><a href="'.$callback.'&do=check"> چک کردن پرداخت شما </a>';		
			$message .= '<div id=result></div>
                        <script type="text/javascript" src="http://jqueryjs.googlecode.com/files/jquery-1.2.6.min.js"></script>
			<script type=text/javascript>
				setInterval(function()
				{ 
					$.ajax({
					  type:"post",
					  url:"'.$params['systemurl'].'/viewinvoice.php?id='.$invoiceid.'&do=check",
					  data:{invoiceid:"'.$invoiceid.'"},
					  success:function(data)
					  {
							if(data == "err")
								$("#result").html("وضعیت فاکتور شما : <font color=red>منتظر پرداخت</font>");
							else
							{
								$("#result").html("وضعیت فاکتور شما : <font color=green>پرداخت شده</font>");		
								window.location = ("'.$callback.'&do=check");
							}							
					  }
					});
				}, '.$time.'000);			  
			</script>'; 
			echo '<div class=wrapper align=center>'.$message.'</div></br>';
		} 
		else 
		{
			$message = error_zp($result->Status);
			echo '<div class=wrapper align=center><font class=unpaid>'.$message.'</font></div></br>';
		}			
	}
	
	if($_GET['do'] == 'check')
	{
		$q = select_query('tblinvoices', '', array('id' => $invoiceid));
		$payment = mysql_fetch_array($q);
		
		if($payment['status'] != 'Paid')
			die('err');
	}
			
	return '<form method=post action="./viewinvoice.php?id='.$invoiceid.'"><input type=hidden name=do value=pay><input type=submit name=pay value=پرداخت /></form>';
}

function error_zp($err) 
{
	switch ($err) 
	{ 
			case '-1' : $msg = "اطلاعات ارسال شده ناقص است."; break; 
			case '-2' : $msg = "IP و يا مرچنت كد پذيرنده صحيح نيست."; break; 
			case '-3' : $msg = "با توجه به محدوديت هاي شاپرك امكان پرداخت با رقم درخواست شده ميسر نمي باشد."; break; 
			case '-4' : $msg = "سطح تاييد پذيرنده پايين تر از سطح نقره اي است."; break; 
			case '-11' : $msg = "درخواست مورد نظر يافت نشد."; break; 
			case '-21' : $msg = "هيچ نوع عمليات مالي براي اين تراكنش يافت نشد."; break; 
			case '-22' : $msg = "تراكنش نا موفق ميباشد."; break; 
			case '-33' : $msg = "رقم تراكنش با رقم پرداخت شده مطابقت ندارد."; break; 
			case '-34' : $msg = "سقف تقسيم تراكنش از لحاظ تعداد يا رقم عبور نموده است"; break; 
			case '-40' : $msg = "اجازه دسترسي به متد مربوطه وجود ندارد."; break; 
			case '-41' : $msg = "غيرمعتبر ميباشد AdditionalData اطلاعات ارسال شده مربوط به"; break;
			case '-54' : $msg = "درخواست مورد نظر آرشيو شده."; break; 
			case '-101' : $msg = "تراكنش انجام شده است. PaymentVerification عمليات پرداخت موفق بوده و قبلا"; break; 			
	}
	return 'خطا (' . $err . ') : ' . $msg;
}
?>
