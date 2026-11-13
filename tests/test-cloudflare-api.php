<?php
/**
 * @package Lookit_CF_Purge
 */

class Test_Lookit_CF_Purge_Cloudflare_Api extends WP_UnitTestCase {

	/**
	 * Captured outgoing request, populated by the pre_http_request filter.
	 *
	 * @var array
	 */
	private $captured = array();

	/**
	 * Canned response (array, WP_Error, or callable) the next request returns.
	 *
	 * @var mixed
	 */
	private $next_response = null;

	public function set_up() {
		parent::set_up();
		$this->captured      = array();
		$this->next_response = null;
		add_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );
		delete_option( Lookit_CF_Purge_Settings::OPTION_KEY );
		parent::tear_down();
	}

	public function intercept_http( $preempt, $args, $url ) {
		$this->captured = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( is_callable( $this->next_response ) ) {
			return call_user_func( $this->next_response, $args, $url );
		}

		return $this->next_response;
	}

	private function configure() {
		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'test-token',
				'zone_id'   => 'test-zone',
			)
		);
	}

	private function cf_success_response( array $result = array() ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'success' => true,
					'result'  => $result,
				)
			),
		);
	}

	public function test_purge_url_short_circuits_when_not_configured() {
		$result = Lookit_CF_Purge_Cloudflare_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'not configured', $result['message'] );
		$this->assertEmpty( $this->captured, 'No HTTP request should be made when unconfigured.' );
	}

	public function test_purge_url_sends_expected_request_and_succeeds() {
		$this->configure();
		$this->next_response = $this->cf_success_response();

		$result = Lookit_CF_Purge_Cloudflare_Api::purge_url( 'https://example.com/page/' );

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			'https://api.cloudflare.com/client/v4/zones/test-zone/purge_cache',
			$this->captured['url']
		);
		$this->assertSame( 'Bearer test-token', $this->captured['args']['headers']['Authorization'] );

		$body = json_decode( $this->captured['args']['body'], true );
		$this->assertSame(
			array(
				array( 'url' => 'https://example.com/page/' ),
				array(
					'url'     => 'https://example.com/page/',
					'headers' => array( 'CF-Device-Type' => 'desktop' ),
				),
				array(
					'url'     => 'https://example.com/page/',
					'headers' => array( 'CF-Device-Type' => 'mobile' ),
				),
				array(
					'url'     => 'https://example.com/page/',
					'headers' => array( 'CF-Device-Type' => 'tablet' ),
				),
			),
			$body['files']
		);
	}

	/**
	 * @dataProvider device_types_provider
	 */
	public function test_purge_url_filters_device_types( $device_types, array $expected_types ) {
		$this->configure();
		$this->next_response = $this->cf_success_response();
		$filter              = function ( $defaults, $url ) use ( $device_types ) {
			$this->assertSame( array( 'desktop', 'mobile', 'tablet' ), $defaults );
			$this->assertSame( 'https://example.com/page/', $url );
			return $device_types;
		};
		add_filter( 'lookit_cf_purge_device_types', $filter, 10, 2 );

		try {
			$result = Lookit_CF_Purge_Cloudflare_Api::purge_url( 'https://example.com/page/' );
		} finally {
			remove_filter( 'lookit_cf_purge_device_types', $filter, 10 );
		}

		$this->assertTrue( $result['success'] );
		$body     = json_decode( $this->captured['args']['body'], true );
		$expected = array( array( 'url' => 'https://example.com/page/' ) );
		foreach ( $expected_types as $device_type ) {
			$expected[] = array(
				'url'     => 'https://example.com/page/',
				'headers' => array( 'CF-Device-Type' => $device_type ),
			);
		}
		$this->assertSame( $expected, $body['files'] );
	}

	public static function device_types_provider() {
		return array(
			'opt out'        => array( array(), array() ),
			'subset'         => array( array( 'mobile' ), array( 'mobile' ) ),
			'invalid values' => array( array( 'mobile', 'unknown', 'DESKTOP', null, 1, array() ), array( 'mobile' ) ),
			'duplicates'     => array( array( 'tablet', 'tablet' ), array( 'tablet' ) ),
			'invalid return' => array( null, array() ),
		);
	}

	public function test_purge_all_sends_purge_everything() {
		$this->configure();
		$this->next_response = $this->cf_success_response();

		$result = Lookit_CF_Purge_Cloudflare_Api::purge_all();

		$this->assertTrue( $result['success'] );
		$body = json_decode( $this->captured['args']['body'], true );
		$this->assertTrue( $body['purge_everything'] );
	}

	public function test_api_error_response_is_reported_with_status_and_messages() {
		$this->configure();
		$this->next_response = array(
			'response' => array( 'code' => 403 ),
			'body'     => wp_json_encode(
				array(
					'success' => false,
					'errors'  => array(
						array( 'message' => 'Invalid request headers' ),
					),
				)
			),
		);

		$result = Lookit_CF_Purge_Cloudflare_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'HTTP 403', $result['message'] );
		$this->assertStringContainsString( 'Invalid request headers', $result['message'] );
	}

	public function test_transport_error_is_reported() {
		$this->configure();
		$this->next_response = new WP_Error( 'http_request_failed', 'Could not resolve host' );

		$result = Lookit_CF_Purge_Cloudflare_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Could not resolve host', $result['message'] );
	}

	public function test_connection_success_returns_zone_name() {
		$this->configure();
		$this->next_response = $this->cf_success_response( array( 'name' => 'example.com' ) );

		$result = Lookit_CF_Purge_Cloudflare_Api::test_connection();

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'example.com', $result['message'] );
	}

	public function test_connection_rejected_when_cloudflare_returns_failure() {
		$this->configure();
		$this->next_response = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'success' => false ) ),
		);

		$result = Lookit_CF_Purge_Cloudflare_Api::test_connection();

		$this->assertFalse( $result['success'] );
	}
}
