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

	private $request_count = 0;

	/**
	 * Canned response (array, WP_Error, or callable) the next request returns.
	 *
	 * @var mixed
	 */
	private $next_response = null;

	public function set_up() {
		parent::set_up();
		$this->captured      = array();
		$this->request_count = 0;
		$this->next_response = null;
		add_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );
		delete_option( Lookit_CF_Purge_Settings::OPTION_KEY );
		parent::tear_down();
	}

	public function intercept_http( $preempt, $args, $url ) {
		++$this->request_count;
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

	public function test_purge_prefix_sends_only_prefix_without_query_or_fragment() {
		$this->configure();
		$this->next_response = $this->cf_success_response();
		$result              = Lookit_CF_Purge_Cloudflare_Api::purge_prefix( 'https://EXAMPLE.com:443/News/%E2%9C%93/?language=en#section' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'https://api.cloudflare.com/client/v4/zones/test-zone/purge_cache', $this->captured['url'] );
		$this->assertSame( 'Bearer test-token', $this->captured['args']['headers']['Authorization'] );
		$this->assertSame( array( 'prefixes' => array( 'example.com/News/%E2%9C%93/' ) ), json_decode( $this->captured['args']['body'], true ) );
		$this->assertStringContainsString( 'all variants and child paths', $result['message'] );
	}

	public function test_purge_prefix_requires_credentials() {
		$result = Lookit_CF_Purge_Cloudflare_Api::purge_prefix( 'https://example.com/news/' );
		$this->assertFalse( $result['success'] );
		$this->assertEmpty( $this->captured );
	}

	/**
	 * @dataProvider invalid_prefix_urls
	 */
	public function test_purge_prefix_rejects_unsafe_or_unsupported_urls( string $url ) {
		$this->configure();
		$result = Lookit_CF_Purge_Cloudflare_Api::purge_prefix( $url );
		$this->assertFalse( $result['success'] );
		$this->assertEmpty( $this->captured );
	}

	public static function invalid_prefix_urls() {
		return array(
			'homepage'             => array( 'https://example.com/' ),
			'bare hostname'        => array( 'https://example.com' ),
			'root with query'      => array( 'https://example.com/?page=1' ),
			'invalid scheme'       => array( 'ftp://example.com/news/' ),
			'user info'            => array( 'https://user@example.com/news/' ),
			'custom port'          => array( 'https://example.com:8443/news/' ),
			'dot segments'         => array( 'https://example.com/news/../' ),
			'encoded dot segments' => array( 'https://example.com/news/%2e%2e/' ),
			'repeated slashes'     => array( 'https://example.com//' ),
			'encoded slash'        => array( 'https://example.com/%2f' ),
			'encoded backslash'    => array( 'https://example.com/news/%5c../' ),
			'too many separators'  => array( 'https://example.com/' . str_repeat( 'path/', 31 ) ),
		);
	}

	public function test_purge_prefix_accepts_31_path_separators() {
		$this->configure();
		$this->next_response = $this->cf_success_response();
		$result              = Lookit_CF_Purge_Cloudflare_Api::purge_prefix( 'http://example.com:80/' . str_repeat( 'path/', 30 ) );
		$this->assertTrue( $result['success'] );
	}

	public function test_purge_prefix_reports_cloudflare_rejection_without_fallback() {
		$this->configure();
		$this->next_response = array(
			'response' => array( 'code' => 429 ),
			'body'     => wp_json_encode(
				array(
					'success' => false,
					'errors'  => array( array( 'message' => 'Rate limit reached' ) ),
				)
			),
		);
		$result              = Lookit_CF_Purge_Cloudflare_Api::purge_prefix( 'https://example.com/news/' );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'HTTP 429', $result['message'] );
		$this->assertStringContainsString( 'Rate limit reached', $result['message'] );
		$this->assertSame( 1, $this->request_count );
		$this->assertArrayHasKey( 'prefixes', json_decode( $this->captured['args']['body'], true ) );
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
