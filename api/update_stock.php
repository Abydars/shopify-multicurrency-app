<?php

require_once '../functions.php';

header( 'Content-Type: application/json' );

ob_start();

try {

	$from        = intval( getSetting( 'stock_from', 0 ) );
	$limit       = 10;
	$product_ids = getLocalProductIds( $from, $limit );
	$updates     = [];

	if ( empty( $product_ids ) ) {
		updateSetting( 'stock_from', 0 );
	} else {

		foreach ( $product_ids as $product_id ) {
			$pid     = $product_id['product_id'];
			$updates = array_merge( $updates, syncProductsQty( $pid ) );
			sleep( 1 );
		}

		updateSetting( 'stock_from', $from + $limit );

		echo json_encode( [
			                  'status'  => true,
			                  'done'    => $from,
			                  'updates' => $updates
		                  ] );
	}

} catch ( Exception $e ) {
	echo json_encode( [
		                  'status'  => false,
		                  'message' => $e->getMessage()
	                  ] );
}

$echo = ob_get_clean();
echo $echo;

file_put_contents( dirname( __FILE__ ) . '/../logs/stock_update.log', $echo . PHP_EOL, FILE_APPEND );

die;
