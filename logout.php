<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] );

if ( ! $authenticated ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

unset( $_SESSION['token'] );
header( 'location: ' . URL . '/login.php' );
die;
