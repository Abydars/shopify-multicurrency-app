<?php
require_once 'functions.php';

$authenticated = ! empty( $_SESSION['token'] );

if ( ! $authenticated ) {
	header( 'location: ' . URL . '/login.php' );
	die;
}

if ( isset( $_POST['t'] ) ) {
	$prices = $_POST['prices'];

	updatePrices( $prices );
}

$url        = isset( $_GET['purl'] ) ? base64_decode( $_GET['purl'] ) : false;
$links      = [];
$currencies = getCurrencies();
$search     = isset( $_GET['s'] ) ? $_GET['s'] : false;

list( $products, $header ) = getProducts( false, false, $url, $search );

if ( ! empty( $products ) ) {
	$products = array_map( function ( $product ) {
		$tags                     = explode( ', ', $product['tags'] );
		$region_tags              = getRegionTags();
		$intersect                = array_intersect( $tags, $region_tags );
		$product['is_duplicated'] = ( count( $intersect ) > 0 );

		return $product;
	}, $products );
}

$header_links = explode( ', ', $header['link'][0] );

foreach ( $header_links as $link ) {
	$parts     = explode( ';', $link );
	$this_link = trim( $parts[0] );
	$this_link = substr( $this_link, 1, strlen( $this_link ) );
	$this_link = substr( $this_link, 0, strlen( $this_link ) - 1 );
	$rel       = trim( $parts[1] );
	$rel       = str_replace( "rel=\"", "", $rel );
	$rel       = str_replace( "\"", "", $rel );

	$links[ $rel ] = $this_link;
}
?>

<?php include 'inc/header.php' ?>
<?php include 'inc/sidebar.php' ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Prices</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
		<?php
		foreach ( $links as $k => $link ) {
			if ( ! empty( $link ) ) {
				echo '<a class="btn btn-warning" href="' . URL . '/products.php?purl=' . base64_encode( $link ) . '">' . ucwords( $k ) . ' page</a>&nbsp;';
			}
		}
		?>
        <a class="btn btn-primary" href="#" id="btn-save">Save Changes</a>
    </div>
</div>
<div class="table-navigation">
    <a href="#" id="btn-move-left">&lt; Scroll Left</a>
    <a href="#" id="btn-move-right">Scroll Right &gt;</a>
</div>
<form method="post">
    <div class="table-responsive">
        <table id="table" class="table table-bordered">
            <thead>
            <tr>
                <th align="left">Product/Variation</th>
                <th>USD</th>
				<?php foreach ( $currencies as $currency ) { ?>
                    <th><?= $currency ?></th>
				<?php } ?>
            </tr>
            </thead>
			<?php foreach ( $products as $product ) {
				list( $variants, $variants_header ) = getProductVariants( $product['id'] );
				$colspan = count( $currencies ) + 2;
				?>
                <tr class="<?= ( $product['is_duplicated'] ? 'bg-light duplicated' : '' ) ?>">
                    <th align="left" <?= ( ! $product['is_duplicated'] ? "colspan='{$colspan}'" : '' ) ?>><a
                                target="_blank"
                                href="<?= getStoreUrl() ?>/products/<?= $product['handle'] ?>"><?= $product['id'] . ' - ' . $product['title'] ?></a>
                    </th>
					<?php if ( $product['is_duplicated'] ) { ?>
                        <td class="text-center" style="vertical-align: middle"
                            colspan="<?= ( $colspan - 1 ) ?>">DUPLICATED
                            PRODUCT
                        </td>
					<?php } ?>
                </tr>
				<?php if ( ! $product['is_duplicated'] ) { ?>
					<?php foreach ( $variants as $variant ) { ?>
                        <td><?= $variant['sku'] ?></td>
                        <td align="center">
                            <div class="form-group">
                                <input type="number" step="any" class="form-control" value="<?= $variant['price'] ?>"
                                       data-variant-id="<?= $variant['id'] ?>" readonly/>
                            </div>
                            <div class="form-group">
                                <input type="number" step="any" class="form-control"
                                       value="<?= $variant['compare_at_price'] ?>"
                                       data-variant-id="<?= $variant['id'] ?>" readonly/>
                            </div>
                        </td>
						<?php foreach ( $currencies as $currency ) { ?>
                            <td align="center">
                                <div class="form-group">
                                    <input name="prices[<?= $product['id'] ?>][variant][<?= $variant['id'] ?>][<?= $currency ?>][price]"
                                           type="number"
                                           placeholder="Price"
                                           step="any"
                                           class="form-control"
                                           value="<?= getFacadePrice( $product['id'], $variant['id'], 'variant', $currency ) ?>"/>
                                </div>
                                <div class="form-group">
                                    <input name="prices[<?= $product['id'] ?>][variant][<?= $variant['id'] ?>][<?= $currency ?>][compare_at_price]"
                                           type="number"
                                           placeholder="Compare Price"
                                           step="any"
                                           class="form-control"
                                           value="<?= getFacadeCompareAtPrice( $product['id'], $variant['id'], 'variant', $currency ) ?>"/>
                                </div>
                            </td>
						<?php } ?>
                        </tr>
					<?php } ?>
				<?php } ?>
			<?php } ?>
        </table>
    </div>
    <input type="hidden" name="t" value="<?= time() ?>"/>
    <div class="mt-4 mb-3">
		<?php
		foreach ( $links as $k => $link ) {
			if ( ! empty( $link ) ) {
				echo '<a class="btn btn-warning" href="' . URL . '/products.php?purl=' . base64_encode( $link ) . '">' . ucwords( $k ) . ' page</a>&nbsp;';
			}
		}
		?>
    </div>
    <div class="text-right" id="submit-section">
        <input type="submit" value="Save Changes" class="btn btn-primary"/>
    </div>
</form>

<script>
    jQuery(function ($) {
        $('#btn-move-left').on('click', function (e) {
            e.preventDefault();

            var currentLeft = $('.table-responsive').scrollLeft();

            $('.table-responsive').animate({
                scrollLeft: currentLeft - 200
            });
        });

        $('#btn-move-right').on('click', function (e) {
            e.preventDefault();

            var currentLeft = $('.table-responsive').scrollLeft();

            $('.table-responsive').animate({
                scrollLeft: currentLeft + 200
            });
        });

        $('#btn-save').on('click', function (e) {
            e.preventDefault();

            $('#table').parents('form').submit();
        });

        $(window).scroll(function () {
            var t = $(window).scrollTop();
            var l = $('#submit-section').offset().top;

            if ((t + screen.height) > l) {
                $('.table-navigation').fadeOut();
            } else {
                $('.table-navigation').fadeIn();
            }
        });
    });
</script>

<?php include 'inc/footer.php' ?>
