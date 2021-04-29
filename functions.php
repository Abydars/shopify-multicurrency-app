<?php

session_start();

define( 'URL', 'http://multicurrency.hztech.biz' );
define( 'PRODUCT_DISPLAY_LIMIT', 5 );
define( 'DBHOST', 'localhost' );
define( 'DBUSER', 'hztech_multicurrency' );
define( 'DBPASS', '$&.R?1B-!j!n' );
define( 'DBNAME', 'hztech_multicurrency' );

global $mysqli, $rates;

$mysqli = new mysqli( DBHOST, DBUSER, DBPASS, DBNAME );

if ( $mysqli->connect_errno ) {
	echo "Failed to connect to MySQL: " . $mysqli->connect_error;
	exit();
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

function getRates( $no_usd = true )
{
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

	$rates = json_decode( $response, true );

	if ( $no_usd ) {
		unset( $rates['USD'] );
	}

	return $rates;
}

function getRates1()
{
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL            => "https://api.exchangeratesapi.io/latest?base=USD",
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

	return $rates['rates'];
}

function getStoreUrl()
{
	$store = $_SESSION['store'];

	return "https://{$store}.myshopify.com";
}

function getProducts( $store = false, $token = false, $url = false, $search = false )
{
	$limit = PRODUCT_DISPLAY_LIMIT;
	if ( empty( $store ) && ! empty( $_SESSION['store'] ) ) {
		$store = $_SESSION['store'];
	}

	if ( empty( $token ) && ! empty( $_SESSION['token'] ) ) {
		$token = $_SESSION['token'];
	}

	if ( empty( $url ) ) {
		$url = "https://{$store}.myshopify.com/admin/api/2020-07/products.json?limit={$limit}";
	}

	if ( ! empty( $search ) ) {
		$url .= "&title=" . urlencode( $search );
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

	if ( isset( $res['products'] ) ) {
		$products = $res['products'];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = implode( ', ', $errors );
		}

		var_dump( 'Errors: ', $errors, $res );
		throw new Exception( $errors );
	}

	return [ $products, $headers ];
}

function getProductByHandle( $handle )
{
	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/products.json?handle={$handle}";
	$curl  = curl_init();

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

	$res = json_decode( $response, true );

	if ( ! empty( $res['products'] ) ) {
		$product = $res['products'][0];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = implode( ', ', $errors );
		}

		var_dump( 'Errors: ', $errors, $res );
		throw new Exception( $errors );
	}

	return $product;
}

function getProduct( $product_id )
{
	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/products/{$product_id}.json";
	$curl  = curl_init();

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

	if ( ! empty( $res['product'] ) ) {
		$product = $res['product'];
	} else {
		$errors = $res['errors'];

		if ( is_array( $errors ) ) {
			$errors = implode( ', ', $errors );
		}

		throw new Exception( $errors );
	}

	return $product;
}

function createProduct( $product_data )
{
	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/products.json";
	$curl  = curl_init();

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
	if ( empty( $store ) && ! empty( $_SESSION['store'] ) ) {
		$store = $_SESSION['store'];
	}

	if ( empty( $token ) && ! empty( $_SESSION['token'] ) ) {
		$token = $_SESSION['token'];
	}

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

function getCurrencies()
{
	$enabled    = getEnabledCurrencies();
	$currencies = [];

	foreach ( $enabled as $item ) {
		$currencies[] = $item['currency'];
	}

	if ( empty( $currencies ) ) {
		$currencies = [ 'GBP', 'EUR' ];
	}

	return $currencies;
}

function getRegionTags()
{
	$currencies = getCurrencies();

	return array_map( function ( $currency ) {
		return 'region-' . strtoupper( $currency );
	}, $currencies );
}

function hasRegionTag( $tags )
{
	$region_tags = getRegionTags();
	$tags        = explode( ', ', $tags );
	$intersect   = array_intersect( $region_tags, $tags );

	return count( $intersect ) > 0;
}

function getFacadePrice( $product_id, $variant_id, $type, $currency )
{
	$facade = getFacade( $product_id, $variant_id, $type, $currency );

	if ( $facade === false ) {
		return false;
	}

	return $facade['price'];
}

function getFacadeCompareAtPrice( $product_id, $variant_id, $type, $currency )
{
	$facade = getFacade( $product_id, $variant_id, $type, $currency );

	if ( $facade === false ) {
		return false;
	}

	return $facade['compare_at_price'];
}

function getFacadeId( $product_id, $variant_id, $type, $currency )
{
	$facade = getFacade( $product_id, $variant_id, $type, $currency );

	if ( $facade === false ) {
		return false;
	}

	return $facade['facade_id'];
}

function getFacade( $product_id, $variant_id, $type, $currency )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices WHERE product_id = ? AND variant_id = ? AND `type` = ? AND currency = ?;" );
	$q->bind_param( "ddss", $product_id, $variant_id, $type, $currency );
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

function updateFacadePrice( $product_id, $variant_id, $type, $currency, $price, $compare_at_price, $facade_id = null, $facade_variant_id = null )
{
	global $mysqli;

	$facade = getFacade( $product_id, $variant_id, $type, $currency );

	if ( empty( $price ) ) {
		$price = null;
	}

	if ( empty( $compare_at_price ) ) {
		$compare_at_price = null;
	}

	if ( $facade === false ) {
		$q = $mysqli->prepare( "INSERT INTO prices (product_id, variant_id, type, price, compare_at_price, currency, facade_id, facade_variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?);" );
		$q->bind_param( "ddssssdd", $product_id, $variant_id, $type, $price, $compare_at_price, $currency, $facade_id, $facade_variant_id );

		$executed = $q->execute();
		$q->close();
	} else {
		$q = $mysqli->prepare( "UPDATE prices SET price = ?, compare_at_price = ? WHERE product_id = ? AND variant_id = ? AND `type` = ? AND currency = ?;" );
		$q->bind_param( "ssddss", $price, $compare_at_price, $product_id, $variant_id, $type, $currency );

		$executed = $q->execute();
		$q->close();
	}

	if ( $executed ) {
		$facade_product_id = getFacadeProductId( $product_id, $currency );
		$product           = getProduct( $product_id );
		$created           = ! empty( $facade_product_id );

		if ( ! $created ) {
			$facade_product = duplicateProductOnShopify( $product_id, $currency );

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
				updateFacadeIds( $product_id, $variant['id'], 'variant', $currency, $variant_price, $variant_compare_at_price, $facade_id, $facade_variant_id );
			}

			if ( $variant_id == $variant['id'] ) {
				if ( empty( $price ) ) {
					$converted = convert_amount( $variant['price'], $currency );
				} else {
					$converted = convert_amount( $price, $currency );
				}

				if ( empty( $compare_at_price ) ) {
					$converted_compare_at_price = convert_amount( $variant['compare_at_price'], $currency );
				} else {
					$converted_compare_at_price = convert_amount( $compare_at_price, $currency );
				}

				updatePriceOnShopify( $facade_variant_id, $converted, $converted_compare_at_price );
			}
		}
	}

	return $executed;
}

function updatePriceOnShopify( $variant_id, $price, $compare_at_price )
{
	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
	$url   = "https://{$store}.myshopify.com/admin/api/2020-07/variants/{$variant_id}.json";
	$curl  = curl_init();

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

function updateFacadeIds( $product_id, $variant_id, $type, $currency, $price, $compare_at_price, $facade_id = null, $facade_variant_id = null )
{
	global $mysqli;

	$facade = getFacade( $product_id, $variant_id, $type, $currency );

	if ( $facade == false ) {
		$q = $mysqli->prepare( "INSERT INTO prices (product_id, variant_id, type, price, compare_at_price, currency, facade_id, facade_variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?);" );
		$q->bind_param( "ddssssdd", $product_id, $variant_id, $type, $price, $compare_at_price, $currency, $facade_id, $facade_variant_id );

		$executed = $q->execute();
		$q->close();
	} else {
		$q = $mysqli->prepare( "UPDATE prices SET facade_id = ?, facade_variant_id = ?  WHERE product_id = ? AND variant_id = ? AND `type` = ? AND currency = ?;" );
		$q->bind_param( "ddddss", $facade_id, $facade_variant_id, $product_id, $variant_id, $type, $currency );

		$executed = $q->execute();
		$q->close();
	}

	return $executed;
}

function getFacadeProductId( $product_id, $currency )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM prices WHERE product_id = ? AND currency = ? AND (facade_id IS NOT NULL || facade_id != '');" );
	$q->bind_param( "ds", $product_id, $currency );
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

function deleteFacadePrice( $product_id, $variant_id, $type, $currency )
{
	global $mysqli;

	$price = getFacadePrice( $product_id, $variant_id, $type, $currency );

	if ( ! empty( $price ) ) {
		$q = $mysqli->prepare( "DELETE FROM prices WHERE variant_id = ? AND `type` = ? AND currency = ?;" );
		$q->bind_param( "dss", $variant_id, $type, $currency );

		return $q->execute();
	}

	return false;
}

function updateProductTagsOnShopify( $product_id, $tags, $new_tags )
{
	$tags  = explode( ', ', $tags );
	$tags  = array_merge( $tags, $new_tags );
	$tags  = array_unique( $tags );
	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
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

function duplicateProductOnShopify( $product_id, $currency )
{
	$product           = getProduct( $product_id );
	$product['handle'] .= "-{$currency}";
	$product['title']  .= " - {$currency}";

	$images   = $product['images'];
	$variants = $product['variants'];
	$images   = $product['images'];

	unset( $product['id'] );
	unset( $product['images'] );
	unset( $product['admin_graphql_api_id'] );

	$product['tags']   = explode( ', ', $product['tags'] );
	$product['tags'][] = "region-{$currency}";
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
	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
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

	$store = $_SESSION['store'];
	$token = $_SESSION['token'];
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

function getEnabledCurrencies()
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM currencies WHERE enabled = 1;" );
	$q->execute();

	$result = $q->get_result();

	if ( $result->num_rows < 1 ) {
		return false;
	}

	$results = $result->fetch_all( MYSQLI_ASSOC );
	$result->free_result();

	return $results;
}

function getCurrency( $name )
{
	global $mysqli;

	$q = $mysqli->prepare( "SELECT * FROM currencies WHERE currency = ?;" );
	$q->bind_param( 's', $name );
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

function enableCurrency( $name, $enabled = 1 )
{
	global $mysqli;

	$currency = getCurrency( $name );

	if ( $currency === false ) {
		$q = $mysqli->prepare( "INSERT INTO currencies (currency, enabled) VALUES (?, ?);" );
		$q->bind_param( "si", $name, $enabled );

		$q->execute();
		$q->close();
	} else {
		$q = $mysqli->prepare( "UPDATE currencies SET enabled = ? WHERE currency = ?;" );
		$q->bind_param( "is", $enabled, $name );

		$q->execute();
		$q->close();
	}
}

function convert_amount( $amount, $currency )
{
	$rates                  = getRates( false );
	$from                   = $rates[ $currency ];
	$to                     = $rates['USD'];
	$amount                 = floatval( $amount );
	$new_amount             = ( ( $amount * $from ) / $to );
	$conversion_fee_percent = 101.467 / 100; // conversion fee percent
	$conversion_fee         = ( ( $new_amount * $conversion_fee_percent ) - $new_amount );
	$new_amount             = $new_amount - $conversion_fee;

	return $new_amount;
}

function getImportOutputFilename()
{
	return dirname( __FILE__ ) . '/import_output.json';
}

function getExportFilename()
{
	return dirname( __FILE__ ) . '/export_output.csv';
}

function updatePrices( $prices, $nonce = false )
{
	$needs_update = [];
	foreach ( $prices as $product_id => $products ) {
		foreach ( $products as $type => $ids ) {
			foreach ( $ids as $variant_id => $currencies ) {
				foreach ( $currencies as $currency => $price ) {
					if ( ! empty( $price['price'] ) || ! empty( $price['compare_at_price'] ) ) {
						$needs_update[ $product_id ][ $type ][ $variant_id ][ $currency ] = $price;
					}
				}
			}
		}
	}

	$done        = 0;
	$output_file = getImportOutputFilename();

	if ( $nonce ) {
		file_put_contents( $output_file, json_encode( [
			                                              $nonce => [
				                                              'total' => count( $needs_update ),
				                                              'done'  => $done
			                                              ]
		                                              ] ) );
	}

	foreach ( $needs_update as $product_id => $products ) {
		foreach ( $products as $type => $ids ) {
			foreach ( $ids as $variant_id => $currencies ) {
				foreach ( $currencies as $currency => $price ) {
					$p = $price['price'];
					$c = $price['compare_at_price'];
					updateFacadePrice( $product_id, $variant_id, $type, $currency, $p, $c );
				}
			}
		}

		if ( $nonce ) {
			$done ++;

			file_put_contents( $output_file, json_encode( [
				                                              $nonce => [
					                                              'total' => count( $needs_update ),
					                                              'done'  => $done
				                                              ]
			                                              ] ) );
		}
	}
}

function getImportStatus( $nonce )
{
	$output = file_get_contents( getImportOutputFilename() );
	$output = json_decode( $output, true );

	return $output[ $nonce ];
}
