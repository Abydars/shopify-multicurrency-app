<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] ) ? $_SESSION['token'] : ( ! empty( $_GET['token'] ) ? $_GET['token'] : false );

if ( ! $authenticated && ! isset( $_GET['action'] ) ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

$error   = "";
$success = "";
$nonce   = time();

if ( isset( $_GET['action'] ) ) {
	$action    = $_GET['action'];
	$timestamp = isset( $_POST['t'] ) ? $_POST['t'] : false;

	header( 'Content-Type: application/json' );

	switch ( $action ) {
		case "upload":

			if ( ! empty( $_FILES["file"]["tmp_name"] ) ) {
				$target_dir    = "uploads/";
				$target_file   = $target_dir . time() . '.csv';
				$imageFileType = strtolower( pathinfo( $target_file, PATHINFO_EXTENSION ) );

				if ( move_uploaded_file( $_FILES["file"]["tmp_name"], $target_file ) ) {
					$handle = @fopen( $target_file, "r" );
					$data   = readCSV( $handle );

					updateSetting( 'import_file', $target_file );
					updateSetting( 'import_total', count( $data ) );
					updateSetting( 'import_done_rows', 0 );
					updateSetting( 'import_per_transaction', 20 );

					echo json_encode( [
						                  'file'   => $target_file,
						                  'status' => true,
						                  'rows'   => count( $data ),
						                  't'      => $timestamp,
					                  ] );
					die;
				}
			}

			echo json_encode( [ 'status' => false ] );
			die;

			break;
		case "check":
			echo json_encode( [
				                  'status' => true,
				                  'done'   => intval( getSetting( 'import_done_rows', 0 ) ),
				                  'total'  => intval( getSetting( 'import_total', 0 ) ),
			                  ] );
			die;

			break;
		case "process":
			ob_start();

			try {
				$prices           = [];
				$currencies       = getCurrencies();
				$currency_codes   = array_map( function ( $currency ) {
					return $currency['code'];
				}, $currencies );
				$products         = [];
				$target_file      = getSetting( 'import_file' );
				$is_transitioning = getSetting( 'import_transitioning', 0 );
				$start_row        = intval( getSetting( 'import_done_rows', 0 ) ) + 1;
				$total            = intval( getSetting( 'import_total' ) );
				$limit            = intval( getSetting( 'import_per_transaction', 20 ) );
				$end_row          = ( $start_row - 1 ) + $limit;

				if ( empty( $target_file ) || $is_transitioning == 1 || $total <= $start_row ) {
					echo json_encode( [
						                  'status'                 => false,
						                  'already_in_transaction' => $is_transitioning,
						                  'is_ended'               => ( $total <= $start_row )
					                  ] );
					die;
				}

				$handle = @fopen( $target_file, "r" );
				$data   = readCSV( $handle );
				$data   = array_filter( $data, function ( $d ) {
					return ! empty( $d['handle'] );
				} );
				$data   = array_values( $data );

				updateSetting( 'import_transitioning', 1 );

				for ( $j = $start_row; $j <= $end_row; $j ++ ) {

					if ( ! isset( $data[ $j ] ) ) {
						break;
					}

					$p                  = $data[ $j ];
					$variant_sku        = sku( $p['variant sku'] );
					$handle             = $p['handle'];
					$variant_id         = false;
					$variant_currencies = [];

					if ( empty( $handle ) ) {
						continue;
					}

					if ( hasRegionTag( $p['tags'] ) ) {
						continue;
					}

					if ( isset( $products[ $handle ] ) ) {
						$product = $products[ $handle ];
					} else {
						$product             = getProductByHandle( $handle );
						$products[ $handle ] = $product;
					}

					if ( empty( $product['id'] ) ) {
						continue;
					}

					foreach ( $p as $k => $v ) {

						if ( empty( $v ) ) {
							continue;
						}

						$currency_price_key         = str_replace( '_price', '', strtolower( $k ) );
						$currency_compare_price_key = str_replace( '_compare_at_price', '', strtolower( $k ) );

						if ( in_array( strtoupper( $currency_price_key ), $currency_codes ) ) {
							$variant_currencies[ strtoupper( $currency_price_key ) ]['price'] = $v;
						}

						if ( in_array( strtoupper( $currency_compare_price_key ), $currency_codes ) ) {
							$variant_currencies[ strtoupper( $currency_compare_price_key ) ]['compare_at_price'] = $v;
						}
					}

					foreach ( $product['variants'] as $v ) {
						if ( sku( $v['sku'] ) == $variant_sku ) {
							$variant_id = $v['id'];
							break;
						}
					}

					if ( empty( $variant_id ) ) {
						continue;
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

				updatePrices( $prices );
				updateSetting( 'import_done_rows', $end_row );
				updateSetting( 'import_transitioning', 0 );

				echo json_encode( [
					                  'status' => true,
					                  'start'  => $start_row,
					                  'end'    => $end_row,
					                  'prices' => $prices
				                  ] );
			} catch ( Exception $e ) {
				updateSetting( 'import_transitioning', 0 );

				echo json_encode( [
					                  'row'    => isset( $j ) ? $j : 0,
					                  'error'  => $e->getMessage(),
					                  'stack'  => $e->getTraceAsString(),
					                  'status' => false,
				                  ] );
			}

			$echo = ob_get_clean();
			echo $echo;

			file_put_contents( dirname( __FILE__ ) . '/logs/import.log', $echo . PHP_EOL, FILE_APPEND );

			die;
			break;
	}
	die;
}

$rows_done   = intval( getSetting( 'import_done_rows', 0 ) );
$import_file = getSetting( 'import_file' );
$ttl_rows    = intval( getSetting( 'import_total', 0 ) );
$is_ended    = $ttl_rows !== 0 && $ttl_rows <= $rows_done;

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
<div class="alert alert-success" id="msg-done" style="display: <?= ( $is_ended ) ? 'block' : 'none'; ?>">
    <strong><?= basename( $import_file ) ?></strong>: Import done
    successfully!
</div>
<form id="form" action="import.php" method="POST" enctype="multipart/form-data" style="display: none;">
    <div class="form-group">
        <label>Upload Shopify structured file with currency columns</label>
        <div>
            <input type="file" name="file" accept="text/csv"/>
        </div>
    </div>
    <input type="hidden" name="action" value="upload"/>
    <input type="hidden" name="t" value="<?= $nonce ?>"/>
    <div class="form-group">
        <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner spinner-border-sm" role="status" aria-hidden="true"
                  style="display: none;"></span>
            Import
        </button>
    </div>
</form>
<div id="progress" style="display: none;">
    <div class="progress">
        <div class="progress-bar" role="progressbar" aria-valuenow="0"
             aria-valuemin="0" aria-valuemax="100" style="width:0%">
            0%
        </div>
    </div>
</div>

<script>
    jQuery(function ($) {
        var total_rows = 0;
        var start_row = 0;
        var limit = 20;
        var end_row = limit;

		<?php if($ttl_rows > $rows_done) { ?>
        startChecking();
		<?php } else { ?>
        $('#form').show();
		<?php } ?>

        function startChecking() {
            $('#form').hide();
            $('#progress').show();

            $.ajax({
                url: 'import.php',
                data: {
                    action: 'check'
                },
                type: 'GET',
                dataType: 'JSON',
                success: function (res) {
                    if (res.status) {
                        setProgress(res.done, res.total);
                    }

                    if (res.done < res.total) {
                        setTimeout(function () {
                            startChecking();
                        }, 1000);
                    } else {
                        $('#form').show();
                        $('#progress').hide();

                        if (res.total <= res.done) {
                            $('#msg-done').show();
                        }
                    }
                },
                error: function (err) {
                    console.error(err);

                    setTimeout(function () {
                        startChecking();
                    }, 1000);
                }
            });
        }

        function setProgress(done, total) {
            var percent = (done * 100) / total;

            $('.progress-bar')
                .text((percent > 100 ? "Completed!" : "Processed " + done + " rows out of " + total))
                .css('width', percent + '%');
        }

        function startProcess(data) {

            if (end_row >= total_rows) {
                return;
            }

            data['action'] = 'process';

            $('#form').hide();
            $('#progress').show();

            setProgress();

            $.ajax({
                url: 'import.php',
                data: data,
                type: 'POST',
                dataType: 'JSON',
                timeout: 0,
                cache: false,
                success: function (res) {
                    console.log(res);

                    if (res.status) {
                        start_row = end_row + 1;
                        end_row += limit;

                        data['start_row'] = start_row;
                        data['end_row'] = end_row;
                    }

                    setTimeout(function () {
                        startProcess(data);
                        setProgress();
                    }, 3000);
                },
                error: function (err) {
                    console.error(err);

                    setTimeout(function () {
                        startProcess(data);
                        setProgress();
                    }, 3000);
                }
            });
        }

        $('form').on('submit', function (e) {
            e.preventDefault();

            var action = $(this).attr('action') + "?action=upload";
            var form_data = new FormData(this);
            var $btn = $(this).find('[type=submit]');

            $btn.attr('disabled', 'disabled');
            $btn.find('.spinner').show();

            $.ajax({
                url: action,
                data: form_data,
                cache: false,
                contentType: false,
                processData: false,
                type: 'POST',
                dataType: 'JSON',
                success: function (res) {
                    if (res.status) {
                        total_rows = res.rows;

                        var data = res;

                        data['start_row'] = start_row;
                        data['end_row'] = end_row;

                        startChecking();

                        //startProcess(data);
                    } else {
                        $btn.removeAttr('disabled');
                        $btn.find('.spinner').hide();
                    }
                },
                error: function (err) {
                    console.error(err);

                    $btn.removeAttr('disabled');
                    $btn.find('.spinner').hide();
                }
            });

            return false;
        });
    });
</script>

<?php include 'inc/footer.php' ?>
