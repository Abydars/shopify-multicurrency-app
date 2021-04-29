<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] );

if ( ! $authenticated ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

$error   = "";
$success = "";

if ( count( $_POST ) > 0 ) {
	$enabled_currencies = getEnabledCurrencies();
	$currency_names     = array_map( function ( $currency ) {
		return $currency['currency'];
	}, $enabled_currencies );

	foreach ( $currency_names as $currency_name ) {
		if ( ! in_array( $currency_name, $_POST['currencies'] ) ) {
			enableCurrency( $currency_name, 0 );
		}
	}

	foreach ( $_POST['currencies'] as $currency ) {
		enableCurrency( $currency );
	}
	$success = "Settings saved.";
}

$rates              = getRates();
$enabled_currencies = getEnabledCurrencies();
$currency_names     = array_map( function ( $currency ) {
	return $currency['currency'];
}, $enabled_currencies );

if ( isset( $_GET['amount'] ) ) {
	$amount = floatval( $_GET['amount'] );

	foreach ( $rates as $currency => $rate ) {
		$converted = convert_amount( $amount, $currency );
		echo "{$currency}: {$converted}<br/>";
	}
}
?>

<?php include 'inc/header.php' ?>
<?php include 'inc/sidebar.php' ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Settings</h1>
</div>
<?php if ( ! empty( $success ) ) : ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<form method="POST">
    <h5>Currencies</h5>
    <div class="row">
		<?php foreach ( $rates as $currency => $rate ) :
			$is_enabled = in_array( $currency, $currency_names );
			?>
            <div class="col-md-2">
                <label>
                    <input type="checkbox"
                           name="currencies[<?= $currency ?>]"
                           value="<?= $currency ?>" <?= ( $is_enabled ? 'checked' : '' ) ?>/>&nbsp;<?= $currency ?>
                </label>
            </div>
		<?php endforeach; ?>
    </div>
    <div class="text-right">
        <input type="submit" value="Save" class="btn btn-primary"/>
    </div>
</form>
<?php include 'inc/footer.php' ?>
