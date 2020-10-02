<?php

header( "Access-Control-Allow-Origin: *" );
header( 'Content-Type: application/json' );

require_once '../functions.php';

if ( isset( $_GET['test'] ) && ! empty( $_GET['amount'] ) && ! empty( $_GET['currency'] ) ) {
	$amount = convert_amount_old( $_GET['amount'], $_GET['currency'] );
	echo json_encode( [
		                  'status'    => true,
		                  'converted' => $amount
	                  ] );
} else if ( ! empty( $_GET['amounts'] ) && ! empty( $_GET['currency'] ) ) {

	$amounts = [];

	foreach ( $_GET['amounts'] as $amount ) {
		$amounts[ $amount ] = convert_amount_back( $amount, $_GET['currency'] );
	}

	echo json_encode( [
		                  'status'    => true,
		                  'converted' => $amounts
	                  ] );

} else if ( ! empty( $_GET['amount'] ) && ! empty( $_GET['currency'] ) ) {
	$default_currency = getSetting( 'default_currency' );
	$from_currency    = getCurrency( $_GET['currency'] )['currency'];
	echo json_encode( [
		                  'status'    => true,
		                  'amount'    => $_GET['amount'],
		                  'from'      => $default_currency,
		                  'to'        => $from_currency,
		                  'converted' => convert_amount_back( $_GET['amount'], $_GET['currency'] )
	                  ] );
} else {
	echo json_encode( [
		                  'status' => true,
	                  ] );
}
die;
