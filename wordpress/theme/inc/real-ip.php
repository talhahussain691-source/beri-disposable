<?php
/**
 * Visitor IP behind Cloudflare.
 *
 * With Cloudflare in front of the site, every request reaches the server from a Cloudflare address, so the
 * per-visitor limits (verify checks, wholesale and support forms) would count all visitors of one Cloudflare
 * location together. When the request comes from a Cloudflare address, this puts the visitor's own address
 * (CF-Connecting-IP) into REMOTE_ADDR. Requests that do not come from Cloudflare are left unchanged, so the
 * header cannot be used to fake an address.
 *
 * Address ranges: https://www.cloudflare.com/ips/ (update the list below if Cloudflare adds new ranges).
 */

defined( 'ABSPATH' ) || exit;

function beri_cloudflare_ranges() {
	return array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
		'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
		'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32',
	);
}

/** True when $ip is inside the CIDR range $range (IPv4 or IPv6). */
function beri_ip_in_range( $ip, $range ) {
	list( $subnet, $bits ) = explode( '/', $range );
	$ip_bin     = @inet_pton( $ip );
	$subnet_bin = @inet_pton( $subnet );
	if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
		return false;
	}
	$bits  = (int) $bits;
	$bytes = intdiv( $bits, 8 );
	if ( 0 !== substr_compare( $ip_bin, $subnet_bin, 0, $bytes ) ) {
		return false;
	}
	$rest = $bits % 8;
	if ( 0 === $rest ) {
		return true;
	}
	$mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
	return ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask );
}

( function () {
	if ( empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return;
	}
	$visitor = trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] );
	if ( ! filter_var( $visitor, FILTER_VALIDATE_IP ) ) {
		return;
	}
	foreach ( beri_cloudflare_ranges() as $range ) {
		if ( beri_ip_in_range( $_SERVER['REMOTE_ADDR'], $range ) ) {
			$_SERVER['REMOTE_ADDR'] = $visitor;
			return;
		}
	}
} )();
