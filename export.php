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

if ( isset( $_POST['action'] ) ) {
	$action    = $_POST['action'];
	$timestamp = $_POST['t'];

	header( 'Content-Type: application/json' );

	switch ( $action ) {
		case "upload":

			if ( ! empty( $_FILES["file"]["tmp_name"] ) ) {
				$target_dir    = "uploads/";
				$target_file   = $target_dir . time() . '.csv';
				$imageFileType = strtolower( pathinfo( $target_file, PATHINFO_EXTENSION ) );

				if ( move_uploaded_file( $_FILES["file"]["tmp_name"], $target_file ) ) {
					$handle = @fopen( $target_file, "r" );
					$data   = $fields = $columns = array();
					$i      = 0;

					if ( $handle ) {
						while ( ( $row = fgetcsv( $handle ) ) !== false ) {
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

					echo json_encode( [
						                  'file'    => $target_file,
						                  'status'  => true,
						                  'rows'    => count( $data ),
						                  't'       => $timestamp,
						                  'columns' => $columns
					                  ] );
					die;
				}
			}

			echo json_encode( [ 'status' => false ] );
			die;

			break;
		case "process":

			$currencies  = getCurrencies(1);
			$target_file = $_POST['file'];
			$columns     = $_POST['columns'];
			$start_row   = $_POST['start_row'];
			$end_row     = $_POST['end_row'];
			$handle      = @fopen( $target_file, "r" );
			$data        = readCSV( $handle );

			$skus    = array_map( function ( $r ) {
				return sku( $r['variant sku'] );
			}, $data );
			$skus    = array_unique( $skus );
			$skus_in = '"' . implode( '", "', $skus ) . '"';

			global $mysqli;

			$result      = $mysqli->query( "SELECT * FROM prices WHERE sku IN ({$skus_in});" );
			$has_records = $result->num_rows > 0;

			$result->free_result();

			for ( $k = $start_row; $k <= $end_row; $k ++ ) {
				$p           = $data[ $k ];
				$variant_sku = sku( $p['variant sku'] );
				$handle      = $p['handle'];
				$variant_id  = false;
				$row         = $p;

				if ( hasRegionTag( $p['tags'] ) ) {
					continue;
				}

				if ( $has_records ) {
					foreach ( $currencies as $currency ) {
						$price = getPriceByHandleSku( $handle, $variant_sku, $currency['code'] );

//						file_put_contents( dirname( __FILE__ ) . '/test.txt', json_encode( [
//							                                                                   'handle'   => $handle,
//							                                                                   'sku'      => $variant_sku,
//							                                                                   'currency' => $currency,
//							                                                                   'prices'   => $price
//						                                                                   ] ) . PHP_EOL . PHP_EOL, FILE_APPEND );

						$row["{$currency['code']}_price"]            = ! empty( $price['price'] ) ? $price['price'] : "";
						$row["{$currency['code']}_compare_at_price"] = ! empty( $price['compare_at_price'] ) ? $price['compare_at_price'] : "";
					}
				}
				$rows[] = $row;
			}

			$f            = fopen( getExportFilename( $timestamp ), 'a+' );
			$has_contents = file_get_contents( getExportFilename( $timestamp ) );

			if ( empty( $has_contents ) ) {
				foreach ( $currencies as $currency ) {
					$columns[] = strtoupper( $currency['code'] ) . '_price';
					$columns[] = strtoupper( $currency['code'] ) . '_compare_at_price';
				}

				fputcsv( $f, $columns );
			}

			foreach ( $rows as $d ) {
				fputcsv( $f, array_values( $d ) );
			}

			fclose( $f );

			echo json_encode( [
				                  'status' => true,
				                  'start'  => $start_row,
				                  'end'    => $end_row
			                  ] );

			die;
			break;
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
<form id="form" method="POST" enctype="multipart/form-data">
    <div class="form-group">
        <label>Upload your Shopify imported file</label>
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
            Generate File
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
        var limit = 500;
        var end_row = limit;

        function setProgress() {
            var percent = (end_row * 100) / total_rows;

            $('.progress-bar')
                .text((percent > 100 ? "Completed!" : "Processed " + end_row + " rows out of " + total_rows))
                .css('width', percent + '%');
        }

        function startProcess(data) {

            if (end_row >= total_rows) {
                window.location.href = '<?= URL ?>/data/export/<?= $nonce ?>.csv';
                return;
            }

            data['action'] = 'process';

            $('#form').hide();
            $('#progress').show();

            setProgress();

            $.ajax({
                data: data,
                type: 'POST',
                dataType: 'JSON',
                success: function (res) {
                    console.log(res);

                    if (res.status) {
                        start_row = end_row + 1;
                        end_row += limit;

                        data['start_row'] = start_row;
                        data['end_row'] = end_row;
                    }

                    startProcess(data);
                    setProgress();
                },
                error: function (err) {
                    console.error(err);

                    startProcess(data);
                    setProgress();
                }
            });
        }

        $('form').on('submit', function (e) {
            e.preventDefault();

            var action = $(this).attr('action');
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

                        startProcess(data);
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
