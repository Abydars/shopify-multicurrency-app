<?php
require_once '../functions.php';

header( "Access-Control-Allow-Origin: *" );
header( 'Content-Type: application/json' );

$currencies = getCurrencies( 1 );
echo json_encode( $currencies );
die;
