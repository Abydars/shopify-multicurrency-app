<?php

require_once '../functions.php';

header( 'Content-Type: application/json' );

ob_start();

$webhook = isset( $_GET['action'] ) ? $_GET['action'] : "";
$is_test = isset( $_GET['test'] );
if ( ! $is_test ) {
	$data        = file_get_contents( 'php://input' );
	$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
	$verified    = verify_webhook( $data, $hmac_header );
} else {
	$data     = file_get_contents( dirname( __FILE__ ) . '/data.json' );
	$verified = true;
}

$data   = json_decode( $data, true );
$result = false;

if ( ! $verified ) {
	echo 'Verification failed!';
} else {
	try {

		switch ( $webhook ) {
			case "order_changes":

				$line_items = $data['line_items'];
				$result     = [];

				foreach ( $line_items as $item ) {
					$product_id = $item['product_id'];
					$update     = syncProductsQty( $product_id );
					$result[]   = ! empty( $update ) ? $update : false;
				}

				break;
		}

		echo json_encode( [
			                  'status'  => true,
			                  'data'    => $data,
			                  'webhook' => $webhook,
			                  'result'  => $result
		                  ] );
	} catch ( Exception $e ) {
		echo json_encode( [
			                  'status'  => false,
			                  'data'    => $data,
			                  'webhook' => $webhook,
			                  'result'  => $result,
			                  'message' => $e->getTraceAsString()
		                  ] );
	}
}

$echo = ob_get_clean();
echo $echo;

file_put_contents( dirname( __FILE__ ) . '/../logs/webhooks.log', $echo . PHP_EOL, FILE_APPEND );

die;
