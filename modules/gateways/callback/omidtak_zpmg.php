<?php
/**
 * Plugin Name: Zarinpal Mobile Gate Module For Whmcs
 * Plugin URI: http://omidtak.ir
 * Version: 1.0 
 * Release Date : 2014 20 December
 * Author: Omid Aran
 * Author Email: info[at]omidtak[dot]ir
 */

if(file_exists('../../../init.php'))
{
require( '../../../init.php' );

}else{

require("../../../dbconnect.php");
}
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
	
$gatewaymodule = 'omidtak_zpmg'; 
$GATEWAY = getGatewayVariables($gatewaymodule);

if(!$GATEWAY['type']) 
	die('Module Not Activated'); 	

$invoiceid = (int) $_GET['invoiceid'];
$invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY['name']);
$amount = (int) $_GET['amount'];

if(empty($invoiceid) AND empty($amount))
	die('Error !'); 
	
if($_GET['Status'] == 'OK')
{
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8')); 		
	$result = $client->PaymentVerification(
						array(
								'MerchantID' => $GATEWAY['MerchantID'],
								'Authority'  => $_GET['Authority'],
								'Amount'	 => $_GET['amount2']
						)
	);
			
	if($result->Status == 100)
	{
		addInvoicePayment($invoiceid, $result->RefID, $amount, '0', $gatewaymodule); 
		logTransaction($GATEWAY['name'],$_POST,'Successful');		
	} 
	else 
		logTransaction($GATEWAY['name'],$_POST,'Unsuccessful');	
} 

if($_GET['do'] == 'check')
{
	$q = select_query('tblinvoices', '', array('id' => $id));
	$payment = mysql_fetch_array($q);
	if($payment['status'] == 'Paid')
		logTransaction($GATEWAY['name'],$_POST,'Successful'); 
	else
		logTransaction($GATEWAY['name'],$_POST,'Unsuccessful');
}

header('Location: '.$CONFIG['SystemURL'].'/viewinvoice.php?id='.$invoiceid);
die;
?>
