<?php
defined( 'ABSPATH' ) || exit;

class Lookit_CF_Purge_Ajax_Handler {

	public static function init() {
		add_action( 'wp_ajax_lookit_cf_purge_url', array( __CLASS__, 'handle_purge_url' ) );
		add_action( 'wp_ajax_lookit_cf_purge_all', array( __CLASS__, 'handle_purge_all' ) );
	}

	public static function handle_purge_url() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.', 403 );
		}

		if ( ! check_ajax_referer( 'lookit_cf_purge_url', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed. Please refresh and try again.', 403 );
		}

		$raw_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$url     = esc_url_raw( $raw_url );

		if ( empty( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
			wp_send_json_error( 'Invalid URL provided.' );
		}

		// Must belong to this site
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$req_host  = wp_parse_url( $url, PHP_URL_HOST );

		if ( $site_host !== $req_host ) {
			wp_send_json_error( 'URL does not belong to this site.' );
		}

		$result = Lookit_CF_Purge_Cloudflare_Api::purge_url( $url );

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	public static function handle_purge_all() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.', 403 );
		}

		if ( ! check_ajax_referer( 'lookit_cf_purge_url', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed. Please refresh and try again.', 403 );
		}

		$result = Lookit_CF_Purge_Cloudflare_Api::purge_all();

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
}
