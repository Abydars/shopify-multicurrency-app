<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] );

if ( ! $authenticated ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

if ( isset( $_GET['check_status'] ) ) {
	$status = getImportStatus( $_GET['check_status'] );
	echo json_encode( [ $status, $_GET['check_status'] ] );
	die;
}

$error   = "";
$success = "";
$nonce   = time();

if ( ! empty( $_FILES["file"]["tmp_name"] ) ) {
	$target_dir    = "uploads/";
	$target_file   = $target_dir . time() . '.csv';
	$target_file   = $target_dir . '1.csv';
	$imageFileType = strtolower( pathinfo( $target_file, PATHINFO_EXTENSION ) );

	if ( move_uploaded_file( $_FILES["file"]["tmp_name"], $target_file ) ) {
		$handle = @fopen( $target_file, "r" );
		$data   = $fields = array();
		$i      = 0;

		if ( $handle ) {
			while ( ( $row = fgetcsv( $handle, 4096 ) ) !== false ) {
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
				$error = "Error: unexpected fgets() fail\n";
			}
			fclose( $handle );
		}

		if ( empty( $error ) ) {
			$prices     = [];
			$currencies = getCurrencies();
			$products   = [];

			foreach ( $data as $p ) {
				$variant_sku        = $p['variant sku'];
				$handle             = $p['handle'];
				$variant_id         = false;
				$variant_currencies = [];

				if ( hasRegionTag( $p['tags'] ) ) {
					continue;
				}

				if ( isset( $products[ $handle ] ) ) {
					$product = $products[ $handle ];
				} else {
					$product             = getProductByHandle( $handle );
					$products[ $handle ] = $product;
				}

				foreach ( $p as $k => $v ) {
					$currency_price_key         = str_replace( '_price', '', strtolower( $k ) );
					$currency_compare_price_key = str_replace( '_compare_at_price', '', strtolower( $k ) );

					if ( in_array( strtoupper( $currency_price_key ), $currencies ) ) {
						$variant_currencies[ strtoupper( $currency_price_key ) ]['price'] = $v;
					}

					if ( in_array( strtoupper( $currency_compare_price_key ), $currencies ) ) {
						$variant_currencies[ strtoupper( $currency_compare_price_key ) ]['compare_at_price'] = $v;
					}
				}

				foreach ( $product['variants'] as $v ) {
					if ( str_replace( ' ', '', $v['sku'] ) == str_replace( ' ', '', $variant_sku ) ) {
						$variant_id = $v['id'];
						break;
					}
				}

				if ( ! isset( $prices[ $product['id'] ] ) ) {
					$prices[ $product['id'] ] = [];
				}

				if ( ! isset( $prices[ $product['id'] ]['variant'] ) ) {
					$prices[ $product['id'] ]['variant'] = [];
				}

				if ( ! isset( $prices[ $product['id'] ]['variant'][ $variant_id ] ) ) {
					$prices[ $product['id'] ]['variant'][ $variant_id ] = $variant_currencies;
				}
			}

			updatePrices( $prices, $nonce );

			$success = "Imported successfully.";
		}

	} else {
		$error = "Sorry, there was an error uploading your file.";
	}
}

?>
<?php include 'inc/header.php' ?>
<?php include 'inc/sidebar.php' ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Import Prices</h1>
</div>
<?php if ( ! empty( $success ) ) : ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ( ! empty( $error ) ) : ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
    <div class="form-group">
        <label>Upload Shopify structured file with currency columns</label>
        <div>
            <input type="file" name="file" accept="text/csv"/>
        </div>
    </div>
    <input type="hidden" name="t" value="<?= $nonce ?>"/>
    <input type="submit" value="Import" class="btn btn-primary"/>

    <div>
        <div class="spinner-border spinner text-primary" role="status" style="display: none;">
            <span class="sr-only">Importing...</span>
        </div>
    </div>
</form>

<script>
    jQuery(function ($) {
        $('form').on('submit', function () {
            $(this).find('input[type=submit]').attr('disabled', 'disabled');
            $(this).find('.spinner').show();

            return true;
        });
    });
</script>

<?php include 'inc/footer.php' ?>
