<?php

require_once '../functions.php';

header( 'Content-Type: application/json' );

ob_start();

try {
	$from    = intval( getSetting( 'update_from', 0 ) );
	$limit   = 50;
	$prices  = getPrices( $from, $limit );
	$updates = [];

	if ( empty( $prices ) ) {
		updateSetting( 'update_from', 0 );
	} else {

		foreach ( $prices as $p ) {
			//$original_product = getProduct( $p['product_id'] );

			if ( ! empty( $p['price'] ) ) {
				$price             = $p['price'];
				$compare_at_price  = floatval( $p['compare_at_price'] );
				$facade_variant_id = floatval( $p['facade_variant_id'] );
				$facade_product_id = floatval( $p['facade_id'] );

				if ( empty( $price ) ) {
					$price = null;
				} else {
					$price = convert_amount( $price, $p['country_code'] );
				}

				if ( empty( $compare_at_price ) ) {
					$compare_at_price = null;
				} else {
					$compare_at_price = convert_amount( $compare_at_price, $p['country_code'] );
				}

				$is_updated                                          = updatePriceOnShopify( $facade_variant_id, $price, $compare_at_price );
				$updates[ $facade_product_id ][ $facade_variant_id ] = $p;
			}
		}

		updateSetting( 'update_from', $from + $limit );
	}

	echo json_encode( [
		                  'status'  => true,
		                  'done'    => $from + $limit,
		                  'updates' => $updates
	                  ] );
} catch ( Exception $e ) {
	echo json_encode( [
		                  'status'  => false,
		                  'done'    => $from,
		                  'message' => $e->getMessage()
	                  ] );
}

$echo = ob_get_clean();
echo $echo;

file_put_contents( dirname( __FILE__ ) . '/../logs/price_update.log', $echo . PHP_EOL, FILE_APPEND );

die;
