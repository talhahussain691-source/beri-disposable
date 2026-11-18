<?php
/**
 * Product verification endpoint used by the Authenticate page.
 *
 *   POST /wp-json/beri/v1/verify   { "code": "XXXX-XXXX-XXXX" }
 *
 * Answers with JSON:
 *   { "status": "genuine" | "already_verified" | "invalid" | "error",
 *     "product": "...", "batch": "...", "check_count": 1, "first_checked": "..." }
 *
 * The check itself is done by the verify plugin, connected in inc/verify-bridge.php.
 * Nothing in this file needs to change to connect the plugin.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route(
		'beri/v1',
		'/verify',
		array(
			'methods'             => 'POST',
			'callback'            => 'beri_rest_verify',
			'permission_callback' => '__return_true',
			'args'                => array(
				'code' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);
} );

function beri_rest_verify( WP_REST_Request $request ) {
	// Rate limit: 20 checks per 10 minutes per visitor, so codes cannot be guessed by a script.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'beri_verify_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= (int) apply_filters( 'beri_verify_rate_limit', 20 ) ) {
		return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Too many attempts. Please try again in a few minutes.' ), 429 );
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	// Codes are letters, digits and dashes; anything else is dropped.
	$code = strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $request->get_param( 'code' ) ) );
	$code = substr( $code, 0, 64 );
	if ( '' === $code ) {
		return new WP_REST_Response( array( 'status' => 'invalid' ), 200 );
	}

	/**
	 * The verify plugin answers through this filter (see inc/verify-bridge.php).
	 * Return null when not connected.
	 */
	$result = apply_filters( 'beri_verify_code', null, $code );

	if ( null === $result && defined( 'BERI_VERIFY_TEST_CODES' ) && BERI_VERIFY_TEST_CODES ) {
		$result = beri_verify_test_codes( $code );
	}
	if ( ! is_array( $result ) ) {
		return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Verification is not connected yet.' ), 503 );
	}

	$allowed = array( 'genuine', 'already_verified', 'invalid' );
	$status  = isset( $result['status'] ) && in_array( $result['status'], $allowed, true ) ? $result['status'] : 'error';
	$out     = array( 'status' => $status );
	foreach ( array( 'product', 'batch', 'first_checked' ) as $field ) {
		if ( ! empty( $result[ $field ] ) ) {
			$out[ $field ] = sanitize_text_field( (string) $result[ $field ] );
		}
	}
	if ( isset( $result['check_count'] ) ) {
		$out['check_count'] = max( 0, (int) $result['check_count'] );
	}
	$response = new WP_REST_Response( $out, 200 );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * Test answers, only when wp-config.php has: define( 'BERI_VERIFY_TEST_CODES', true );
 * Use it to check the page before the plugin is connected. Turn it off on the live site.
 */
function beri_verify_test_codes( $code ) {
	if ( 'BERI-TEST-0001' === $code ) {
		return array( 'status' => 'genuine', 'product' => 'BERI Crush · Blue Razz Ice', 'batch' => 'TEST', 'check_count' => 1 );
	}
	if ( 'BERI-TEST-0002' === $code ) {
		return array( 'status' => 'already_verified', 'product' => 'BERI Cliq · Miami Mint', 'batch' => 'TEST', 'check_count' => 4, 'first_checked' => '12 Jan 2026' );
	}
	return array( 'status' => 'invalid' );
}
