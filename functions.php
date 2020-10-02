<?php

session_start();

define( 'URL', 'https://app.harriswharflondon.com' );
define( 'PRODUCT_DISPLAY_LIMIT', 5 );
define( 'ABSPATH', true );
define( 'DBHOST', 'localhost' );
define( 'DBUSER', 'shopify_app' );
define( 'DBPASS', '(_[#FZrNAp+b' );
define( 'DBNAME', 'shopify_app' );

global $mysqli, $rates;
global $original_products;

$original_products = [];
$mysqli            = new mysqli( DBHOST, DBUSER, DBPASS, DBNAME );

if ( $mysqli->connect_errno ) {
	echo "Failed to connect to MySQL: " . $mysqli->connect_error;
	exit();
}

function verify_webhook( $data, $hmac_header )
{
	$app_secret      = getSetting( 'app_secret' );
	$calculated_hmac = base64_encode( hash_hmac( 'sha256', $data, $app_secret, true ) );

	return hash_equals( $hmac_header, $calculated_hmac );
}

function getToken()
{
	$api_key  = getSetting( 'api_key' );
	$password = getSetting( 'password' );
	$token    = getSetting( 'token' );

	if ( ! empty( $token ) ) {
		return $token;
	}

	return base64_encode( "{$api_key}:{$password}" );
}

function getStore()
{
	return getSetting( 'store_name' );
}

function get_string_between( $string, $start, $end )
{
	$string = ' ' . $string;
	$ini    = strpos( $string, $start );
	if ( $ini == 0 ) {
		return '';
	}
	$ini += strlen( $start );
	$len = strpos( $string, $end, $ini ) - $ini;

	return substr( $string, $ini, $len );
}

function getRates()
{
	$base = getSetting( 'default_currency' );

	if ( empty( $base ) ) {
		$base = 'USD';
	}

	global $rates;

	if ( ! empty( $rates ) ) {
		return $rates;
	}

	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => "https://cdn.shopify.com/s/javascripts/currencies.js",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST  => "GET",
	) );

	$response = curl_exec( $curl );
	$response = get_string_between( $response, 'rates: ', 'convert:' );
	$response = "{" . get_string_between( $response, '{', '}' ) . "}";

	curl_close( $curl );

	$rates     = json_decode( $response, true );
	$base_rate = $rates[ $base ];

	foreach ( $rates as $k => $r ) {
		$rates[ $k ] = $rates[ $k ] / $base_rate;
	}

	return $rates;
}

function getRates1( $base = 'USD' )
{
	global $rates;

	if ( ! empty( $rates ) ) {
		return $rates;
	}

	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => "https://api.exchangeratesapi.io/latest?base={$base}",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST  => "GET",
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$rates = json_decode( $response, true );
	$rates = $rates['rates'];

	return $rates;
}

function getStoreUrl()
{
	$store = $_SESSION['store'];

	return "https://{$store}.myshopify.com";
}

function getProducts( $store = false, $token = false, $url = false, $search = false, $ids = false, $limit = false )
{
	if ( empty( $limit ) && $limit !== - 1 ) {
		$limit = PRODUCT_DISPLAY_LIMIT;
	}

	if ( empty( $store ) ) {
		$store = getStore();
	}

	if ( empty( $token ) ) {
		$token = getToken();
	}

	if ( empty( $url ) ) {
		$url = "https://{$store}.myshopify.com/admin/api/2020-07/products.json?1=1";
	}

	if ( ! empty( $limit ) && $limit !== - 1 ) {
		$url .= "&limit=" . $limit;
	}

	if ( ! empty( $search ) ) {
		$url .= "&title=" . urlencode( $search );
	}

	if ( ! empty( $ids ) ) {
		$url .= "&ids=" . urlencode( implode( ',', $ids ) );
	}

	$curl    = curl_init();
	$headers = [];

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
//		CURLOPT_ENCODING       => "",
//		CURLOPT_MAXREDIRS      => 10,
//		CURLOPT_TIMEOUT        => 0,
		CURLOPT_HEADER         => 0,
//		CURLOPT_FOLLOWLOCATION => true,
//		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
//		CURLOPT_CUSTOMREQUEST  => "GET",
		CURLOPT_HEADERFUNCTION => function ( $curl, $header ) use ( &$headers ) {
			$len    = strlen( $header );
			$header = explode( ':', $header, 2 );
			if ( count( $header ) < 2 ) // ignore invalid headers
			{
				return $len;
			}

			$headers[ strtolower( trim( $header[0] ) ) ][] = trim( $header[1] );

			return $len;
		},
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}"
		),
	) );

	sleep( 1 );
	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	if ( isset( $res['products'] ) ) {
		$products = $res['products'];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = implode( ', ', $errors );
		}

		//var_dump( 'Errors: ', $errors, $res );
		throw new Exception( $errors );
	}

	return [ $products, $headers ];
}

function getLocalProductIds( $from, $limit )
{
	global $mysqli;

	$result   = $mysqli->query( "SELECT DISTINCT product_id FROM prices LIMIT {$from}, {$limit};" );
	$products = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$products = $results;
	}

	$result->free_result();

	return $products;
}

function getFacadeProducts( $product_id )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT DISTINCT facade_id FROM prices WHERE product_id = ?;" );
	$q->bind_param( 's', $product_id );
	$q->execute();

	$result   = $q->get_result();
	$products = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$products = $results;
	}

	$result->free_result();

	return $products;
}

function getFacadeProduct( $product_id )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices WHERE facade_id = ?;" );
	$q->bind_param( 's', $product_id );
	$q->execute();

	$result   = $q->get_result();
	$products = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$products = $results;
	}

	$result->free_result();

	return $products;
}

function getProductByHandle( $handle )
{
	$store = getStore();
	$token = getToken();

	$url  = "https://{$store}.myshopify.com/admin/api/2020-07/products.json?handle={$handle}";
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_HEADER         => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST  => "GET",
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res     = json_decode( $response, true );
	$product = false;

	if ( isset( $res['products'] ) ) {
		$product = isset( $res['products'][0] ) ? $res['products'][0] : false;
	}

//	else {
//		$errors = $res['errors'];
//
//		if ( is_array( $errors ) ) {
//			$errors = implode( ', ', $errors );
//		}

//		var_dump( 'Errors: ', $errors, $response );
//		throw new Exception( $errors );
//	}

	return $product;
}

function getProduct( $product_id )
{
	global $original_products;

	if ( isset( $original_products[ $product_id ] ) ) {
		return $original_products[ $product_id ];
	}

	$store = getStore();
	$token = getToken();

	$url  = "https://{$store}.myshopify.com/admin/api/2020-07/products/{$product_id}.json";
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_HEADER         => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST  => "GET",
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}"
		),
	) );

	sleep( 1 );
	$response = curl_exec( $curl );

	curl_close( $curl );

	$res     = json_decode( $response, true );
	$product = false;

	if ( ! empty( $res['product'] ) ) {
		$product = $res['product'];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = implode( ', ', $errors );
		}

		sleep( 2 );

		throw new Exception( $errors );
	}

	return $product;
}

function createProduct( $product_data )
{
	$store = getStore();
	$token = getToken();

	$url  = "https://{$store}.myshopify.com/admin/api/2020-07/products.json";
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => "POST",
		CURLOPT_POSTFIELDS     => json_encode( [ 'product' => $product_data ] ),
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res     = json_decode( $response, true );
	$product = false;

	if ( ! empty( $res['product'] ) ) {
		$product = $res['product'];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = json_encode( $errors );
		}

		throw new Exception( $errors );
	}

	return $product;
}

function getProductVariants( $product_id, $store = false, $token = false, $url = false )
{
	$store = getStore();
	$token = getToken();

	if ( empty( $url ) ) {
		$url = "https://{$store}.myshopify.com/admin/api/2020-07/products/{$product_id}/variants.json";
	}

	$curl    = curl_init();
	$headers = [];

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_HEADER         => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST  => "GET",
		CURLOPT_HEADERFUNCTION => function ( $curl, $header ) use ( &$headers ) {
			$len    = strlen( $header );
			$header = explode( ':', $header, 2 );
			if ( count( $header ) < 2 ) // ignore invalid headers
			{
				return $len;
			}

			$headers[ strtolower( trim( $header[0] ) ) ][] = trim( $header[1] );

			return $len;
		},
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	if ( ! empty( $res['variants'] ) ) {
		$variants = $res['variants'];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = implode( ', ', $errors );
		}

		throw new Exception( $errors );
	}

	return [ $variants, $headers ];
}

function getRegionTags()
{
	$currencies = getCurrencies();

	return array_map( function ( $currency ) {
		return 'region-' . strtoupper( $currency['code'] );
	}, $currencies );
}

function hasRegionTag( $tags )
{
	$region_tags = getRegionTags();
	$tags        = explode( ', ', $tags );
	$intersect   = array_intersect( $region_tags, $tags );

	return count( $intersect ) > 0;
}

function getFacadePrice( $product_id, $variant_id, $type, $country_code )
{
	$facade = getFacade( $product_id, $variant_id, $type, $country_code );

	if ( $facade === false ) {
		return false;
	}

	return $facade['price'];
}

function getFacadeCompareAtPrice( $product_id, $variant_id, $type, $country_code )
{
	$facade = getFacade( $product_id, $variant_id, $type, $country_code );

	if ( $facade === false ) {
		return false;
	}

	return $facade['compare_at_price'];
}

function getFacadeId( $product_id, $variant_id, $type, $country_code )
{
	$facade = getFacade( $product_id, $variant_id, $type, $country_code );

	if ( $facade === false ) {
		return false;
	}

	return $facade['facade_id'];
}

function getPrices( $from, $limit )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices LIMIT {$from}, {$limit}" );
	$q->execute();

	$result = $q->get_result();
	$prices = [];

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$prices = $results;
	}

	return $prices;
}

function getFacade( $product_id, $variant_id, $type, $country_code )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices WHERE product_id = ? AND variant_id = ? AND `type` = ? AND country_code = ?;" );
	$q->bind_param( "ddss", $product_id, $variant_id, $type, $country_code );
	$q->execute();

	$result = $q->get_result();
	$price  = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$price = $results[0];
	}

	$result->free_result();

	return $price;
}

function updateFacadePrice( $product_id, $variant_id, $type, $country_code, $price, $compare_at_price, $sku, $handle, $facade_id = null, $facade_variant_id = null )
{
	global $mysqli;

	$facade = getFacade( $product_id, $variant_id, $type, $country_code );

	if ( empty( $price ) ) {
		$price = null;
	}

	if ( empty( $compare_at_price ) ) {
		$compare_at_price = null;
	}

	if ( $facade === false ) {
		$q = $mysqli->prepare( "INSERT INTO prices (product_id, variant_id, type, price, compare_at_price, country_code, sku, handle, facade_id, facade_variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);" );
		$q->bind_param( "ddssssssdd", $product_id, $variant_id, $type, $price, $compare_at_price, $country_code, $sku, $handle, $facade_id, $facade_variant_id );

		$executed = $q->execute();
		$q->close();
	} else {
		$q = $mysqli->prepare( "UPDATE prices SET price = ?, compare_at_price = ?, sku = ?, handle = ? WHERE product_id = ? AND variant_id = ? AND `type` = ? AND country_code = ?;" );
		$q->bind_param( "ssssddss", $price, $compare_at_price, $sku, $handle, $product_id, $variant_id, $type, $country_code );

		$executed = $q->execute();
		$q->close();
	}

	if ( $executed ) {
		$facade_product_id = getFacadeProductId( $product_id, $country_code );
		$product           = getProduct( $product_id );
		$created           = ! empty( $facade_product_id );

		if ( ! $created ) {
			$facade_product = duplicateProductOnShopify( $product, $country_code );

			updateProductTagsOnShopify( $product_id, $product['tags'], [ 'region-default' ] );
		} else {
			$facade_product = getProduct( $facade_product_id );
		}

		$facade_id = $facade_product['id'];

		foreach ( $product['variants'] as $i => $variant ) {
			$facade_variant_id        = $facade_product['variants'][ $i ]['id'];
			$variant_price            = $price;
			$variant_compare_at_price = $compare_at_price;

			if ( $variant['id'] != $variant_id ) {
				$variant_price            = null;
				$variant_compare_at_price = null;
			}

			if ( ! $created ) {
				updateFacadeIds( $product_id, $variant['id'], 'variant', $country_code, sku( $variant['sku'] ), $handle, $variant_price, $variant_compare_at_price, $facade_id, $facade_variant_id );
			}

			if ( $variant_id == $variant['id'] ) {
				if ( empty( $price ) ) {
					$converted = convert_amount( $variant['price'], $country_code );
				} else {
					$converted = convert_amount( $price, $country_code );
				}

				if ( empty( $compare_at_price ) ) {
					$converted_compare_at_price = convert_amount( $variant['compare_at_price'], $country_code );
				} else {
					$converted_compare_at_price = convert_amount( $compare_at_price, $country_code );
				}

				updatePriceOnShopify( $facade_variant_id, $converted, $converted_compare_at_price );
			}
		}
	}

	return $executed;
}

function updatePriceOnShopify( $variant_id, $price, $compare_at_price )
{
	$store = getStore();
	$token = getToken();

	$url  = "https://{$store}.myshopify.com/admin/api/2020-07/variants/{$variant_id}.json";
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => "PUT",
		CURLOPT_POSTFIELDS     => json_encode( [
			                                       'variant' => [
				                                       'id'               => $variant_id,
				                                       'price'            => $price,
				                                       'compare_at_price' => $compare_at_price
			                                       ]
		                                       ] ),
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	return ! empty( $res['variant'] );
}

function getInventoryLevels( $inventory_item_ids )
{
	$store              = getStore();
	$token              = getToken();
	$inventory_item_ids = implode( ',', $inventory_item_ids );
	$url                = "https://{$store}.myshopify.com/admin/api/2020-07/inventory_levels.json?inventory_item_ids={$inventory_item_ids}";
	$curl               = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	return ! empty( $res['inventory_levels'] ) ? $res['inventory_levels'] : false;
}

function updateQuantityOnShopify( $inventory_level )
{
	$store = getStore();
	$token = getToken();

	$url  = "https://{$store}.myshopify.com/admin/api/2020-07/inventory_levels/set.json";
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => "POST",
		CURLOPT_POSTFIELDS     => json_encode( $inventory_level ),
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	return ! empty( $res['inventory_level'] );
}

function updateFacadeIds( $product_id, $variant_id, $type, $country_code, $sku, $handle, $price, $compare_at_price, $facade_id = null, $facade_variant_id = null )
{
	global $mysqli;

	$facade = getFacade( $product_id, $variant_id, $type, $country_code );

	if ( $facade == false ) {
		$q = $mysqli->prepare( "INSERT INTO prices (product_id, variant_id, type, price, compare_at_price, country_code, sku, handle, facade_id, facade_variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);" );
		$q->bind_param( "ddssssssdd", $product_id, $variant_id, $type, $price, $compare_at_price, $country_code, $sku, $handle, $facade_id, $facade_variant_id );

		$executed = $q->execute();
		$q->close();
	} else {
		$q = $mysqli->prepare( "UPDATE prices SET facade_id = ?, facade_variant_id = ?  WHERE product_id = ? AND variant_id = ? AND `type` = ? AND country_code = ?;" );
		$q->bind_param( "ddddss", $facade_id, $facade_variant_id, $product_id, $variant_id, $type, $country_code );

		$executed = $q->execute();
		$q->close();
	}

	return $executed;
}

function getFacadeProductId( $product_id, $country_code )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices WHERE product_id = ? AND country_code = ? AND (facade_id IS NOT NULL || facade_id != '');" );
	$q->bind_param( "ds", $product_id, $country_code );
	$q->execute();

	$result    = $q->get_result();
	$facade_id = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$facade_id = $results[0]['facade_id'];
	}

	$result->free_result();

	return $facade_id;
}

function getFacadeVariantId( $variant_id )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices WHERE variant_id = ? AND (facade_variant_id IS NOT NULL || facade_variant_id != '');" );
	$q->bind_param( "d", $variant_id );
	$q->execute();

	$result    = $q->get_result();
	$facade_id = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$facade_id = $results[0]['facade_variant_id'];
	}

	$result->free_result();

	return $facade_id;
}

function deleteFacadePrice( $product_id, $variant_id, $type, $country_code )
{
	global $mysqli;

	$price = getFacadePrice( $product_id, $variant_id, $type, $country_code );

	if ( ! empty( $price ) ) {
		$q = $mysqli->prepare( "DELETE FROM prices WHERE variant_id = ? AND `type` = ? AND country_code = ?;" );
		$q->bind_param( "dss", $variant_id, $type, $country_code );

		return $q->execute();
	}

	return false;
}

function updateProductTagsOnShopify( $product_id, $tags, $new_tags )
{
	$tags  = explode( ', ', $tags );
	$tags  = array_merge( $tags, $new_tags );
	$tags  = array_unique( $tags );
	$store = getStore();
	$token = getToken();
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/products/{$product_id}.json";
	$curl  = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => "PUT",
		CURLOPT_POSTFIELDS     => json_encode( [
			                                       'product' => [
				                                       'id'   => $product_id,
				                                       'tags' => implode( ', ', $tags ),
			                                       ]
		                                       ] ),
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	return $res;
}

function duplicateProductOnShopify( $product, $country_code )
{
	//$product           = getProduct( $product_id );
	$product['handle'] .= "-{$country_code}";
	//$product['title']  .= " - {$country_code}";

	$images   = $product['images'];
	$variants = $product['variants'];
	$images   = $product['images'];

	unset( $product['id'] );
	unset( $product['images'] );
	unset( $product['admin_graphql_api_id'] );

	$product['tags']   = explode( ', ', $product['tags'] );
	$product['tags'][] = "region-{$country_code}";
	$product['tags']   = array_filter( $product['tags'], function ( $t ) {
		return $t !== 'region-default';
	} );
	$product['tags']   = implode( ', ', $product['tags'] );

	$product['options'] = array_map( function ( $option ) {
		return [
			'name' => $option['name'],
		];
	}, $product['options'] );

	$product['variants'] = array_map( function ( $variant ) {
		unset( $variant['product_id'] );
		unset( $variant['id'] );
		unset( $variant['title'] );
		unset( $variant['admin_graphql_api_id'] );
		unset( $variant['created_at'] );
		unset( $variant['updated_at'] );
		unset( $variant['image_id'] );
		unset( $variant['inventory_item_id'] );
		unset( $variant['compare_at_price'] );

		return $variant;
	}, $product['variants'] );

	$new_product      = createProduct( $product );
	$variants_mapping = [];

	foreach ( $variants as $i => $variant ) {
		$variants_mapping[ $variant['id'] ] = $new_product['variants'][ $i ]['id'];
	}

	foreach ( $images as $image ) {
		$new_variants = array_map( function ( $variant_id ) use ( &$variants_mapping ) {
			return $variants_mapping[ $variant_id ];
		}, $image['variant_ids'] );
		addImageToProduct( $image['src'], $new_product['id'], $new_variants );
	}

	return $new_product;
}

function getShopifyImage( $image_id, $product_id )
{
	$store = getStore();
	$token = getToken();
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/products/{$product_id}/images/{$image_id}.json";
	$curl  = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => "GET",
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	return $res;
}

function addImageToProduct( $url, $product_id, $variant_ids = [] )
{
	$image = file_get_contents( $url );
	$image = base64_encode( $image );
	$name  = basename( $image );

	$store = getStore();
	$token = getToken();
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/products/{$product_id}/images.json";
	$curl  = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST  => "POST",
		CURLOPT_POSTFIELDS     => json_encode( [
			                                       'image' => [
				                                       'attachment'  => $image,
				                                       'filename'    => $name,
				                                       'variant_ids' => $variant_ids
			                                       ]
		                                       ] ),
		CURLOPT_HTTPHEADER     => array(
			"Authorization: Basic {$token}",
			"Content-Type: application/json"
		),
	) );

	$response = curl_exec( $curl );

	curl_close( $curl );

	$res = json_decode( $response, true );

	return $res;
}

function getCurrencies( $only_enabled = false )
{
	global $mysqli;

	$query = "SELECT * FROM currencies;";

	if ( $only_enabled ) {
		$query = "SELECT * FROM currencies WHERE enabled = 1;";
	}

	$q = $mysqli->prepare( $query );
	$q->execute();

	$result = $q->get_result();

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	$result->free_result();

	return $results;
}

function getCurrencyName( $currency )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM currencies WHERE currency = ?;" );
	$q->bind_param( 's', $currency );
	$q->execute();

	$result = $q->get_result();

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	$result->free_result();

	return $results[0]['name'];
}

function getSetting( $name, $default = false )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM settings WHERE name = ?;" );
	$q->bind_param( 's', $name );
	$q->execute();

	$result = $q->get_result();

	if ( $result->num_rows < 1 ) {
		return $default;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	$result->free_result();

	return $results[0]['value'];
}

function getCurrency( $code )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM currencies WHERE code = ?;" );
	$q->bind_param( 's', $code );
	$q->execute();

	$result   = $q->get_result();
	$currency = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$currency = $results[0];
	}

	$result->free_result();

	return $currency;
}

function enableCurrency( $code, $enabled = 1 )
{
	global $mysqli;

	$q = $mysqli->prepare( "UPDATE currencies SET enabled = ? WHERE code = ?;" );
	$q->bind_param( "is", $enabled, $code );

	$q->execute();
	$q->close();
}

function updateSetting( $name, $value )
{
	global $mysqli;

	$setting = getSetting( $name );

	if ( $setting === false ) {
		$q = $mysqli->prepare( "INSERT INTO settings (name, value) VALUES (?, ?);" );
		$q->bind_param( "ss", $name, $value );

		$q->execute();
		$q->close();
	} else {
		$q = $mysqli->prepare( "UPDATE settings SET value = ? WHERE name = ?;" );
		$q->bind_param( "ss", $value, $name );

		$q->execute();
		$q->close();
	}
}

function convert_amount_old( $amount, $country_code )
{
	$rates          = getRates();
	$currency       = getCurrency( $country_code );
	$currency       = $currency['currency'];
	$to             = $rates[ $currency ];
	$amount         = floatval( $amount );
	$am             = $amount;
	$conversion_fee = ( 1 + 0.015 );
	$after_fee      = $amount + 1;
	$minus          = 0.001;
	$new_amount     = 0;
	$i              = 0;

	while ( true ) {
		$new_amount = $amount * $to;
		$after_fee  = ( $new_amount / $to ) * ( 1 + 0.013836 );//( 1 + 0.015 )

		echo json_encode( [$after_fee, $amount, ( $after_fee >= $am )] );
		echo PHP_EOL;

		if ( $after_fee <= $am ) {
			break;
		}

		$amount -= $minus;
	}

	return $new_amount;
}

function convert_amount( $amount, $from_country_code )
{
	$default_currency = getSetting( 'default_currency' );
	$from_currency    = getCurrency( $from_country_code )['currency'];

	if ( $from_currency == $default_currency ) {
		return $amount;
	}

	$rates  = getRates();
	$to     = $rates[ $from_currency ];
	$amount = floatval( $amount );

	return floatval( number_format( $amount * $to, 2 ) );
}

function convert_amount_back( $amount, $from_country_code )
{
	$default_currency = getSetting( 'default_currency' );
	$from_currency    = getCurrency( $from_country_code )['currency'];

	if ( $from_currency == $default_currency ) {
		return $amount;
	}

	$rates  = getRates();
	$to     = $rates[ $from_currency ];
	$amount = floatval( $amount );

	return floatval( number_format( $amount / $to, 2 ) );
}

function getImportOutputFilename()
{
	return dirname( __FILE__ ) . '/import_output.json';
}

function getExportFilename( $name = 'export_output' )
{
	return dirname( __FILE__ ) . "/data/export/{$name}.csv";
}

function updatePrices( $prices )
{
	$needs_update = [];
	foreach ( $prices as $product_id => $products ) {
		foreach ( $products as $type => $ids ) {
			foreach ( $ids as $variant_id => $currencies ) {
				foreach ( $currencies as $country_code => $price ) {
					if ( ! empty( $price['price'] ) || ! empty( $price['compare_at_price'] ) ) {
						$needs_update[ $product_id ][ $type ][ $variant_id ][ $country_code ] = $price;
					}
				}
			}
		}
	}

	foreach ( $needs_update as $product_id => $products ) {
		foreach ( $products as $type => $ids ) {
			foreach ( $ids as $variant_id => $currencies ) {
				foreach ( $currencies as $country_code => $price ) {
					$p      = $price['price'];
					$c      = $price['compare_at_price'] == 0 ? null : $price['compare_at_price'];
					$sku    = $price['sku'];
					$handle = $price['handle'];
					updateFacadePrice( $product_id, $variant_id, $type, $country_code, $p, $c, $sku, $handle );
				}
			}
		}
	}
}

function getImportStatus( $nonce )
{
	$output = file_get_contents( getImportOutputFilename() );
	$output = json_decode( $output, true );

	return $output[ $nonce ];
}

function sku( $sku )
{
	for ( $i = 0; $i < strlen( $sku ); $i ++ ) {
		$sku[ $i ] = utf8_decode( $sku[ $i ] );
	}
	$sku = str_replace( '?', '', $sku );
	$sku = preg_replace( '/\s+/', ' ', $sku );

	return $sku;
}

function getPriceByHandleSku( $handle, $sku, $country_code )
{
	global $mysqli;

	$sku        = sku( $sku );
	$result     = $mysqli->query( "SELECT * FROM prices WHERE handle = '{$handle}' AND sku = '{$sku}' AND country_code = '{$country_code}';" );
	$variant_id = false;

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	if ( ! empty( $results ) ) {
		$variant_id = $results[0];
	}

	$result->free_result();

	return $variant_id;
}

function readCSV( $handle )
{
	$data = $fields = array();
	$i    = 0;

	if ( $handle ) {
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( empty( $fields ) ) {
				$fields = array_map( 'strtolower', $row );
				continue;
			}
			foreach ( $row as $k => $value ) {
				$data[ $i ][ $fields[ $k ] ] = $value;
			}
			$i ++;
		}
		if ( ! feof( $handle ) ) {
			return false;
		}
		fclose( $handle );
	}

	return $data;
}

function getRelevantProductIds( $pid )
{
	$facade_products = getFacadeProducts( $pid );
	$products        = [];

	if ( ! empty( $facade_products ) ) {
		$products   = array_map( function ( $f ) {
			return $f['facade_id'];
		}, $facade_products );
		$products[] = intval( $pid );
	} else {
		$facade_product = getFacadeProduct( $pid );

		if ( ! empty( $facade_product['product_id'] ) ) {
			$products = getRelevantProductIds( $facade_product['product_id'] );
		}
	}

	return $products;
}

function syncProductsQty( $pid )
{
	$updates     = [];
	$product_ids = getRelevantProductIds( $pid );
	list( $products, $headers ) = getProducts( false, false, false, false, $product_ids, - 1 );
	$variants = $base_product_variants = [];

	foreach ( $products as $product ) {
		$is_base_product = $product['id'] == $pid;
		foreach ( $product['variants'] as $variant ) {
			$sku                = sku( $variant['sku'] );
			$inventory          = [
				'id'  => $variant['inventory_item_id'],
				'qty' => $variant['inventory_quantity'],
			];
			$variants[ $sku ][] = $inventory;

			if ( $is_base_product ) {
				$base_product_variants[ $sku ] = $inventory;
			}
		}
	}

	foreach ( $variants as $sku => $variant ) {
//		$least_qty = $variant[0]['qty'];
		$qtys                       = array_column( $variant, 'qty' );
		$has_diff                   = count( array_unique( $qtys ) ) > 1;
		$base_product_variant_price = $base_product_variants[ $sku ]['qty'];

//		foreach ( $variant as $item ) {
//			if ( $item['qty'] < $least_qty ) {
//				$least_qty = $item['qty'];
//			}
//		}

		if ( $has_diff ) {
			sleep( 1 );
			$inventory_item_ids = array_column( $variant, 'id' );
			$inventory_levels   = getInventoryLevels( $inventory_item_ids );

			foreach ( $inventory_levels as $inventory_level ) {
				unset( $inventory_level['updated_at'] );
				unset( $inventory_level['admin_graphql_api_id'] );

				if ( $inventory_level['available'] != $base_product_variant_price ) {
					$inventory_level['available'] = $base_product_variant_price;
					$updated                      = updateQuantityOnShopify( $inventory_level );

					if ( $updated ) {
						$updates['success'][] = $inventory_level['inventory_item_id'];
					} else {
						$updates['failed'][] = $inventory_level['inventory_item_id'];
					}
				}
			}
		}
	}

	return $updates;
}
