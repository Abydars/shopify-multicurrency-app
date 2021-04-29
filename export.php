<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] );

if ( ! $authenticated ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

$error   = "";
$success = "";
$nonce   = time();

if ( ! empty( $_FILES["file"]["tmp_name"] ) ) {
	$target_dir    = "uploads/";
	$target_file   = $target_dir . time() . '.csv';
	$target_file   = $target_dir . '2.csv';
	$imageFileType = strtolower( pathinfo( $target_file, PATHINFO_EXTENSION ) );
	$currencies    = getCurrencies();

	if ( move_uploaded_file( $_FILES["file"]["tmp_name"], $target_file ) ) {
		$handle = @fopen( $target_file, "r" );
		$data   = $fields = $columns = array();
		$i      = 0;

		if ( $handle ) {
			while ( ( $row = fgetcsv( $handle, 4096 ) ) !== false ) {
				if ( empty( $fields ) ) {
					$fields  = array_map( 'strtolower', $row );
					$columns = $row;
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
			$rows       = [];

			foreach ( $data as $k => $p ) {

				$variant_sku = $p['variant sku'];
				$handle      = $p['handle'];
				$variant_id  = false;

				if ( hasRegionTag( $p['tags'] ) ) {
					continue;
				}

				if ( isset( $products[ $handle ] ) ) {
					$product = $products[ $handle ];
				} else {

					sleep( 1 );

					$product             = getProductByHandle( $handle );
					$products[ $handle ] = $product;
				}

				foreach ( $product['variants'] as $v ) {
					if ( str_replace( ' ', '', $v['sku'] ) == str_replace( ' ', '', $variant_sku ) ) {
						$variant_id = $v['id'];
						break;
					}
				}

				$row = $p;

				foreach ( $currencies as $currency ) {
					$row["{$currency}_price"]            = getFacadePrice( $product['id'], $variant_id, 'variant', $currency );
					$row["{$currency}_compare_at_price"] = getFacadeCompareAtPrice( $product['id'], $variant_id, 'variant', $currency );
				}

				$rows[] = $row;
			}
		}

		foreach ( $currencies as $currency ) {
			$columns[] = strtoupper( $currency ) . '_price';
			$columns[] = strtoupper( $currency ) . '_compare_at_price';
		}

		$f = fopen( getExportFilename(), 'w' );

		fputcsv( $f, $columns );

		foreach ( $rows as $d ) {
			fputcsv( $f, array_values( $d ) );
		}

		fclose( $f );

		if ( empty( $error ) ) {
			$file_url = URL . '/export_output.csv';
			$success  = "Download exported file from <a href='{$file_url}'>here</a>.";
		}

	} else {
		$error = "Sorry, there was an error uploading your file.";
	}
}

?>

<?php include 'inc/header.php' ?>
<?php include 'inc/sidebar.php' ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Export Template</h1>
</div>
<?php if ( ! empty( $success ) ) : ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ( ! empty( $error ) ) : ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
    <div class="form-group">
        <label>Upload your Shopify imported file</label>
        <div>
            <input type="file" name="file" accept="text/csv"/>
        </div>
    </div>
    <div class="form-group">
        <input type="submit" value="Generate File" class="btn btn-primary"/>
    </div>
    <div>
        <div class="spinner-border spinner text-primary" role="status" style="display: none;">
            <span class="sr-only">Uploading...</span>
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
