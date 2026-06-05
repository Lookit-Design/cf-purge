<?php
defined( 'ABSPATH' ) || exit;

class Lookit_CF_Purge_Cloudflare_Api {

	const CF_API_BASE = 'https://api.cloudflare.com/client/v4';

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

		$response = wp_remote_post(
			self::CF_API_BASE . '/zones/' . Lookit_CF_Purge_Settings::get_zone_id() . '/purge_cache',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . Lookit_CF_Purge_Settings::get_api_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'files' => array( $url ) ) ),
			)
		);

		return self::parse_response( $response, 'Cache purged for: ' . $url );
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
