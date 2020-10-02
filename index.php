<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] );

if ( ! $authenticated ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

if ( isset( $_GET['amount'] ) ) {
	$amount = floatval( $_GET['amount'] );

	echo convert_amount( $amount, 'US' );
	die;
}

$error   = "";
$success = "";

if ( count( $_POST ) > 0 ) {
	$enabled_currencies = getCurrencies( 1 );
	$currency_codes     = array_map( function ( $currency ) {
		return $currency['code'];
	}, $enabled_currencies );

	foreach ( $currency_codes as $code ) {
		if ( ! in_array( $code, $_POST['currencies'] ) ) {
			enableCurrency( $code, 0 );
		}
	}

	foreach ( $_POST['currencies'] as $currency ) {
		enableCurrency( $currency );
	}

	$default_currency = $_POST['default_currency'];
	$app_secret       = $_POST['app_secret'];

	updateSetting( 'default_currency', $default_currency );
	updateSetting( 'app_secret', $app_secret );

	$success = "Settings saved.";
}

$currencies       = getCurrencies();
$default_currency = getSetting( 'default_currency' );
$app_secret       = getSetting( 'app_secret' );
$rates            = getRates();
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
    <div class="form-group">
        <label>App Secret (required for webhooks)</label>
        <input type="text" class="form-control" name="app_secret" value="<?= $app_secret ?>"/>
    </div>
    <div class="form-group">
        <label>Default Currency</label>
        <select name="default_currency" class="form-control">
			<?php foreach ( $rates as $currency => $rate ) { ?>
                <option <?= ( $default_currency == $currency ? 'selected' : '' ) ?>><?= $currency ?></option>
			<?php } ?>
        </select>
    </div>
    <div class="form-group">
        <label>Select countries to sell</label>
        <div class="row">
			<?php foreach ( $currencies as $currency ) :
				$is_enabled = $currency['enabled'] == 1;
				?>
                <div class="col-md-2">
                    <label>
                        <input type="checkbox"
                               name="currencies[]"
                               value="<?= $currency['code'] ?>" <?= ( $is_enabled ? 'checked' : '' ) ?>/>&nbsp;<?= $currency['name'] ?>
                        (<?= $currency['currency'] ?>)
                    </label>
                </div>
			<?php endforeach; ?>
        </div>
    </div>
    <div class="text-right">
        <input type="submit" value="Save" class="btn btn-primary"/>
    </div>
</form>
<?php include 'inc/footer.php' ?>
