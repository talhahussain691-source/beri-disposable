<?php
/**
 * Support page forms: "Contact us" and "Report a counterfeit".
 *
 *   POST /wp-json/beri/v1/support   (multipart form data)
 *     kind      contact | report
 *     contact:  name, email, topic, message
 *     report:   name, email, product, code, store, place, date, message, contact_ok, photos[] (up to 5 images)
 *
 *   -> { "ok": true }  or  { "ok": false, "message": "..." }
 *
 * The message is emailed with wp_mail() to support@beridisposable.com, with the visitor's address as Reply-To and
 * any photos attached. To send elsewhere, add to wp-config.php:
 *
 *   define( 'BERI_SUPPORT_EMAIL', 'someone@beridisposable.com' );            // both forms
 *   define( 'BERI_COUNTERFEIT_EMAIL', 'authentic@beridisposable.com' );      // counterfeit reports only
 *
 * Each visitor can send 5 messages per 10 minutes.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route(
		'beri/v1',
		'/support',
		array(
			'methods'             => 'POST',
			'callback'            => 'beri_rest_support',
			'permission_callback' => '__return_true',
		)
	);
} );

function beri_support_recipient( $kind ) {
	$to = defined( 'BERI_SUPPORT_EMAIL' ) ? BERI_SUPPORT_EMAIL : 'support@beridisposable.com';
	if ( 'report' === $kind && defined( 'BERI_COUNTERFEIT_EMAIL' ) ) {
		$to = BERI_COUNTERFEIT_EMAIL;
	}
	return apply_filters( 'beri_support_recipient', $to, $kind );
}

function beri_support_reply( $ok, $message = '', $status = 200 ) {
	$body = array( 'ok' => (bool) $ok );
	if ( '' !== $message ) {
		$body['message'] = $message;
	}
	$response = new WP_REST_Response( $body, $status );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

function beri_rest_support( WP_REST_Request $request ) {
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key  = 'beri_support_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= (int) apply_filters( 'beri_support_rate_limit', 5 ) ) {
		return beri_support_reply( false, 'Too many messages. Please try again in a few minutes.', 429 );
	}

	$field = function ( $name, $multiline = false ) use ( $request ) {
		$value = $request->get_param( $name );
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		return trim( mb_substr( $value, 0, $multiline ? 5000 : 200 ) );
	};

	$kind  = 'report' === $field( 'kind' ) ? 'report' : 'contact';
	$email = sanitize_email( $field( 'email' ) );
	$name  = $field( 'name' );
	$msg   = $field( 'message', true );

	if ( ! is_email( $email ) || '' === $msg ) {
		return beri_support_reply( false, 'Please enter a valid email address and a message.', 400 );
	}
	if ( 'contact' === $kind && '' === $name ) {
		return beri_support_reply( false, 'Please enter your name.', 400 );
	}
	if ( 'report' === $kind && '' === $field( 'store' ) ) {
		return beri_support_reply( false, 'Please tell us where you bought it.', 400 );
	}

	if ( 'report' === $kind ) {
		$subject = '[Counterfeit report] ' . ( $field( 'product' ) ? $field( 'product' ) . ' from ' : '' ) . $field( 'store' );
		$lines   = array(
			'Name'                 => $name,
			'Email'                => $email,
			'Product'              => $field( 'product' ),
			'Security code'        => $field( 'code' ),
			'Bought at'            => $field( 'store' ),
			'City and state'       => $field( 'place' ),
			'Date of purchase'     => $field( 'date' ),
			'OK to contact'        => 'yes' === $field( 'contact_ok' ) ? 'Yes' : 'No',
			'What looks wrong'     => $msg,
		);
	} else {
		$subject = '[Contact] ' . ( $field( 'topic' ) ? $field( 'topic' ) : 'Message' ) . ' from ' . $name;
		$lines   = array(
			'Name'    => $name,
			'Email'   => $email,
			'Topic'   => $field( 'topic' ),
			'Message' => $msg,
		);
	}
	$body = '';
	foreach ( $lines as $label => $value ) {
		if ( '' !== $value ) {
			$body .= $label . ': ' . $value . "\n";
		}
	}
	$body .= "\nSent from the Support page on " . home_url( '/' );

	// Photos (counterfeit reports): images only, up to 5 files of 8 MB each.
	$attachments = array();
	$files       = $request->get_file_params();
	if ( ! empty( $files['photos'] ) && ! is_array( $files['photos']['tmp_name'] ) ) {
		// A single file sent as "photos" rather than "photos[]".
		foreach ( $files['photos'] as $k => $v ) {
			$files['photos'][ $k ] = array( $v );
		}
	}
	if ( 'report' === $kind && ! empty( $files['photos'] ) && is_array( $files['photos']['tmp_name'] ) ) {
		$allowed = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heif' );
		$count   = 0;
		foreach ( $files['photos']['tmp_name'] as $i => $tmp ) {
			if ( $count >= 5 || empty( $tmp ) || ! is_uploaded_file( $tmp ) || ! empty( $files['photos']['error'][ $i ] ) ) {
				continue;
			}
			if ( (int) $files['photos']['size'][ $i ] > 8 * MB_IN_BYTES ) {
				continue;
			}
			$type = function_exists( 'mime_content_type' ) ? mime_content_type( $tmp ) : '';
			if ( ! isset( $allowed[ $type ] ) ) {
				continue;
			}
			$copy = trailingslashit( get_temp_dir() ) . 'beri-report-' . wp_generate_password( 8, false ) . '-' . ( $count + 1 ) . '.' . $allowed[ $type ];
			if ( @copy( $tmp, $copy ) ) {
				$attachments[] = $copy;
				$count++;
			}
		}
	}

	$headers = array( 'Reply-To: ' . ( '' !== $name ? $name . ' <' . $email . '>' : $email ) );
	$sent    = wp_mail( beri_support_recipient( $kind ), $subject, $body, $headers, $attachments );
	foreach ( $attachments as $file ) {
		@unlink( $file );
	}

	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	if ( ! $sent ) {
		return beri_support_reply( false, 'We couldn’t send this right now. Please try again or email support@beridisposable.com.', 500 );
	}
	return beri_support_reply( true );
}
