<?php
/**
 * Wholesale inquiry form -> Beri Wholesale plugin.
 *
 * The Wholesale page form posts to the plugin's AJAX action (beri_wholesale_plugin) with form_version=theme,
 * using the field names the plugin expects (company, contact_name, email, phone_number, business_type, region,
 * products[], message, consent). The plugin saves the inquiry and emails it; its admin pages list and export
 * them, and set the recipient address.
 *
 * The plugin checks a WordPress nonce. A page served from a cache (SiteGround, Cloudflare) can carry a nonce
 * that has expired, so the form asks this endpoint for a fresh one right before sending:
 *
 *   GET /wp-json/beri/v1/wholesale-nonce  ->  { "nonce": "..." }
 *
 * The form's business types and products match the plugin's default choices. If they are changed in
 * Beri Wholesale -> Form Settings, change them on the Wholesale page too.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route(
		'beri/v1',
		'/wholesale-nonce',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				if ( ! class_exists( 'Beri_Wholesale_Plugin' ) ) {
					return new WP_REST_Response( array( 'error' => 'not_connected' ), 503 );
				}
				$response = new WP_REST_Response( array( 'nonce' => wp_create_nonce( Beri_Wholesale_Plugin::NONCE_ACTION ) ), 200 );
				$response->header( 'Cache-Control', 'no-store' );
				return $response;
			},
		)
	);
} );

/** True when the Beri Wholesale plugin is active. */
function beri_wholesale_connected() {
	return class_exists( 'Beri_Wholesale_Plugin' );
}
