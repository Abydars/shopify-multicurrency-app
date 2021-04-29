<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] ) && ! empty( $_SESSION['store'] );

if ( isset( $_POST['t'] ) ) {
	$api_key  = $_POST['api_key'];
	$store    = $_POST['store'];
	$password = $_POST['password'];
	$token    = base64_encode( "{$api_key}:{$password}" );
	list( $products, $headers ) = getProducts( $store, $token );

	if ( ! empty( $products ) ) {
		$_SESSION['token'] = $token;
		$_SESSION['store'] = $store;
		$authenticated     = true;
	}
}

if ( $authenticated ) {
	header( 'location: ' . URL . '/products.php' );
	die;
}

?>
<html>
<head>
    <title>Login - Multi Currency Pricing</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= URL ?>/assets/css/signin.css">
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
            integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"
            integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1"
            crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"
            integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM"
            crossorigin="anonymous"></script>
</head>
<body class="text-center">
<form method="POST" class="form-signin">
    <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
    <div class="form-group">
        <input type="text" class="form-control" name="store" placeholder="Store Name"
               value="<?= ( ! empty( $_POST['store'] ) ? $_POST['store'] : "" ) ?>"/>
    </div>
    <div class="form-group">
        <input type="text" class="form-control" name="api_key" placeholder="API Key"
               value="<?= ( ! empty( $_POST['api_key'] ) ? $_POST['api_key'] : "" ) ?>"/>
    </div>
    <div class="form-group">
        <input type="password" class="form-control" name="password" placeholder="Password"
               value="<?= ( ! empty( $_POST['password'] ) ? $_POST['password'] : "" ) ?>"/>
    </div>
    <input type="hidden" name="t" value="<?= time() ?>"/>
    <input type="submit" class="btn btn-lg btn-primary btn-block"/>
</form>
</body>
</html>
