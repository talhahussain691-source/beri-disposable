<?php
/**
 * BERI theme setup.
 *
 * The seven BERI pages are full designs of their own (front-page.php and page-*.php). Each one renders
 * from its markup with assets/js/beri-runtime.js, so WordPress only supplies the page URLs, the <head>
 * hooks for plugins, and the product verification endpoint.
 */

defined( 'ABSPATH' ) || exit;

define( 'BERI_THEME_VERSION', '1.0.0' );

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/verify-endpoint.php';
require_once __DIR__ . '/inc/verify-bridge.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
} );

/**
 * The BERI pages and their slugs. The key is the page name used inside the designs
 * (for example "Cliq.dc.html" becomes "cliq").
 */
function beri_pages() {
	return array(
		'home-color-images' => array( 'slug' => 'home', 'title' => 'Home' ),
		'cliq'              => array( 'slug' => 'cliq', 'title' => 'BERI Cliq' ),
		'crush-v2'          => array( 'slug' => 'crush', 'title' => 'BERI Crush' ),
		'cirql-v2'          => array( 'slug' => 'cirql', 'title' => 'BERI Cirql' ),
		'eliquid'           => array( 'slug' => 'e-liquid', 'title' => 'BERI E-liquid' ),
		'authenticate'      => array( 'slug' => 'authenticate', 'title' => 'Authenticate' ),
		'wholesale'         => array( 'slug' => 'wholesale', 'title' => 'Wholesale' ),
	);
}

/** URL of a BERI page by its design name, falling back to /slug/ when the page does not exist yet. */
function beri_page_url( $key ) {
	$pages = beri_pages();
	if ( 'home-color-images' === $key ) {
		return home_url( '/' );
	}
	$slug = $pages[ $key ]['slug'];
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}

/** True on the front page and on pages that use one of the BERI templates. */
function beri_is_design_page() {
	if ( is_front_page() ) {
		return true;
	}
	if ( ! is_page() ) {
		return false;
	}
	$template = get_page_template_slug();
	if ( $template && 0 === strpos( $template, 'page-' ) ) {
		return true;
	}
	$slugs = wp_list_pluck( beri_pages(), 'slug' );
	return in_array( get_post_field( 'post_name', get_queried_object_id() ), $slugs, true );
}

// Page links and the verify hook for the design runtime. Printed early in <head>, before the page scripts.
add_action( 'wp_head', function () {
	if ( ! beri_is_design_page() ) {
		return;
	}
	$urls = array();
	foreach ( array_keys( beri_pages() ) as $key ) {
		$urls[ $key ] = beri_page_url( $key );
	}
	// Older design names still used by a few links.
	$urls['beri-premium'] = $urls['home-color-images'];
	$urls['crush']        = $urls['crush-v2'];
	$urls['cirql']        = $urls['cirql-v2'];
	$verify               = esc_url_raw( rest_url( 'beri/v1/verify' ) );
	?>
<script>
window.BERI_PAGES = <?php echo wp_json_encode( $urls ); ?>;
// Authenticate page: sends the code to this site, which asks the verify plugin (see inc/verify-bridge.php).
window.BERI_VERIFY = function (code) {
	return fetch(<?php echo wp_json_encode( $verify ); ?>, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ code: code })
	}).then(function (r) { return r.json(); });
};
</script>
	<?php
}, 1 );

// The designs bring all their own styles; drop the default block styles on those pages so nothing overrides them.
add_action( 'wp_enqueue_scripts', function () {
	if ( ! beri_is_design_page() ) {
		return;
	}
	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}, 100 );

// Lighter <head>: no emoji scripts.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
