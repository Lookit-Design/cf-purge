<?php
defined( 'ABSPATH' ) || exit;

class Lookit_CF_Purge_Cloudflare_Api {

	const CF_API_BASE = 'https://api.cloudflare.com/client/v4';

	/**
	 * Complete CF-Device-Type header values; desktop includes all other devices.
	 * Check this reference for changes to the supported categories.
	 *
	 * @see https://developers.cloudflare.com/automatic-platform-optimization/reference/cache-device-type/
	 */
	private const DEVICE_TYPES = array( 'desktop', 'mobile', 'tablet' );

	/**
	 * Purge a single URL from Cloudflare's edge cache.
	 */
	public static function purge_url( string $url ): array {

		if ( ! Lookit_CF_Purge_Settings::is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare credentials are not configured.',
			);
		}

		$files = array( array( 'url' => $url ) );
		foreach ( self::DEVICE_TYPES as $device_type ) {
			$files[] = array(
				'url'     => $url,
				'headers' => array( 'CF-Device-Type' => $device_type ),
			);
		}

		$response = wp_remote_post(
			self::CF_API_BASE . '/zones/' . Lookit_CF_Purge_Settings::get_zone_id() . '/purge_cache',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . Lookit_CF_Purge_Settings::get_api_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'files' => $files ) ),
			)
		);

		return self::parse_response( $response, 'Cache purged for: ' . $url );
	}

	/**
	 * Purge all cache-key variants under a URL prefix, including child paths.
	 *
	 * @see https://developers.cloudflare.com/cache/how-to/purge-cache/purge_by_prefix/
	 */
	public static function purge_prefix( string $url ): array {
		if ( ! Lookit_CF_Purge_Settings::is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare credentials are not configured.',
			);
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid URL provided.',
			);
		}

		$path = $parts['path'] ?? '/';
		if ( '' === $path || '/' === $path ) {
			return array(
				'success' => false,
				'message' => 'A homepage prefix would clear every page on this hostname. Use Purge Entire Site instead.',
			);
		}

		// Reject paths whose normalization could expand the requested purge scope.
		$decoded_path = rawurldecode( $path );
		if ( preg_match( '#(?:^|/)[.]{1,2}(?:/|$)#', $decoded_path ) || false !== strpos( $decoded_path, '\\' ) || false !== strpos( $decoded_path, '//' ) || preg_match( '/[\x00-\x1f\x7f]/', $decoded_path ) || preg_match( '/%2f|%3f|%23/i', $path ) || substr_count( $path, '/' ) > 31 || ( isset( $parts['port'] ) && ( 'https' === strtolower( $parts['scheme'] ) ? 443 : 80 ) !== $parts['port'] ) ) {
			return array(
				'success' => false,
				'message' => 'Prefix purge requires a path without dot segments, at most 31 path separators, and a standard HTTP or HTTPS port.',
			);
		}

		$prefix   = strtolower( $parts['host'] ) . $path;
		$response = wp_remote_post(
			self::CF_API_BASE . '/zones/' . Lookit_CF_Purge_Settings::get_zone_id() . '/purge_cache',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . Lookit_CF_Purge_Settings::get_api_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'prefixes' => array( $prefix ) ) ),
			)
		);

		return self::parse_response( $response, 'Cache purge requested for prefix: ' . $prefix . ' (all variants and child paths).' );
	}

	/**
	 * Purge the entire site cache from Cloudflare.
	 */
	public static function purge_all(): array {

		if ( ! Lookit_CF_Purge_Settings::is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare credentials are not configured.',
			);
		}

		$response = wp_remote_post(
			self::CF_API_BASE . '/zones/' . Lookit_CF_Purge_Settings::get_zone_id() . '/purge_cache',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . Lookit_CF_Purge_Settings::get_api_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'purge_everything' => true ) ),
			)
		);

		return self::parse_response( $response, 'Entire Cloudflare cache purged successfully.' );
	}

	/**
	 * Lightweight connection test.
	 */
	public static function test_connection(): array {

		if ( ! Lookit_CF_Purge_Settings::is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Credentials not saved. Enter your API Token and Zone ID first.',
			);
		}

		$response = wp_remote_get(
			self::CF_API_BASE . '/zones/' . Lookit_CF_Purge_Settings::get_zone_id(),
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . Lookit_CF_Purge_Settings::get_api_token(),
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Connection failed: ' . $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['success'] ) ) {
			return array(
				'success' => true,
				'message' => '✅ Connected successfully. Zone: ' . ( $body['result']['name'] ?? 'unknown' ),
			);
		}

		return array(
			'success' => false,
			'message' => 'Cloudflare rejected the request.',
		);
	}

	/**
	 * Shared response parser.
	 */
	private static function parse_response( $response, string $success_message ): array {

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Request failed: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['success'] ) ) {
			return array(
				'success' => true,
				'message' => $success_message,
			);
		}

		$errors = array();
		if ( ! empty( $body['errors'] ) ) {
			foreach ( $body['errors'] as $err ) {
				$errors[] = $err['message'] ?? 'Unknown error';
			}
		}

		return array(
			'success' => false,
			'message' => 'Cloudflare API error (HTTP ' . $status_code . '): ' . implode( ' | ', $errors ),
		);
	}
}
