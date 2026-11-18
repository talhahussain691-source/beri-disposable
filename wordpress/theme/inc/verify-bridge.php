<?php
/**
 * ============================================================================
 *  CONNECT THE VERIFY PLUGIN HERE
 * ============================================================================
 *
 * This is the only file to edit to connect the Authenticate page to the verify plugin.
 * The page design (form, buttons, result screens) is already done; please don't load the
 * plugin's own form template, CSS or JS on the Authenticate page.
 *
 * How it works:
 *   1. A visitor types a code on the Authenticate page and presses VERIFY CODE.
 *   2. The page sends it to /wp-json/beri/v1/verify (inc/verify-endpoint.php).
 *   3. That endpoint calls beri_verify_bridge() below with the cleaned code
 *      (upper case, only A-Z, 0-9 and "-").
 *   4. Return one of these arrays and the page shows the matching result screen:
 *
 *      Genuine, first check:
 *        array( 'status' => 'genuine', 'product' => 'BERI Crush · Blue Razz Ice', 'batch' => 'B2026-11', 'check_count' => 1 )
 *
 *      Genuine code that was checked before:
 *        array( 'status' => 'already_verified', 'product' => '...', 'batch' => '...', 'check_count' => 4, 'first_checked' => '12 Jan 2026' )
 *
 *      Code not in the records:
 *        array( 'status' => 'invalid' )
 *
 *   "product", "batch", "check_count" and "first_checked" are optional; leave out what the plugin doesn't have.
 *   Return null if the check could not run (the page then asks the visitor to try again).
 *
 * The endpoint already limits each visitor to 20 checks per 10 minutes.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'beri_verify_code', 'beri_verify_bridge', 10, 2 );

function beri_verify_bridge( $result, $code ) {
	// Another plugin may already have answered through the same filter.
	if ( null !== $result ) {
		return $result;
	}

	// ---- Replace this block with the call to the verify plugin. -----------------------------
	//
	// Example:
	//
	// if ( ! function_exists( 'your_plugin_check_code' ) ) {
	// 	return null; // plugin not active
	// }
	// $r = your_plugin_check_code( $code );
	// if ( ! $r ) {
	// 	return array( 'status' => 'invalid' );
	// }
	// return array(
	// 	'status'        => $r->times_checked > 1 ? 'already_verified' : 'genuine',
	// 	'product'       => $r->product_name,
	// 	'batch'         => $r->batch_no,
	// 	'check_count'   => $r->times_checked,
	// 	'first_checked' => $r->first_check_date,
	// );
	//
	// ------------------------------------------------------------------------------------------

	return null; // Not connected yet.
}
