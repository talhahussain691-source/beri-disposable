<?php
/**
 * One-time setup when the theme is activated: creates any BERI page that is missing, sets Home as the
 * front page and turns on "Post name" links, so the menu links work straight away.
 * Existing pages are never changed or deleted.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_switch_theme', 'beri_setup_pages' );

function beri_setup_pages() {
	$ids = array();
	foreach ( beri_pages() as $key => $page ) {
		$existing = get_page_by_path( $page['slug'] );
		if ( $existing ) {
			$ids[ $key ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $page['title'],
				'post_name'   => $page['slug'],
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[ $key ] = $id;
		}
	}

	if ( ! empty( $ids['home-color-images'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home-color-images'] );
	}

	if ( '' === get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	flush_rewrite_rules();
}
