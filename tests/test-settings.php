<?php
/**
 * @package Lookit_CF_Purge
 */

class Test_Lookit_CF_Purge_Settings extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( Lookit_CF_Purge_Settings::OPTION_KEY );
		parent::tear_down();
	}

	public function test_sanitize_trims_and_strips_tags() {
		$input = array(
			'api_token' => '  token-abc123  ',
			'zone_id'   => "  zone<script>alert(1)</script>-id \n",
		);

		$result = Lookit_CF_Purge_Settings::sanitize_settings( $input );

		$this->assertSame( 'token-abc123', $result['api_token'] );
		$this->assertSame( 'zone-id', $result['zone_id'] );
	}

	public function test_sanitize_handles_missing_keys() {
		$result = Lookit_CF_Purge_Settings::sanitize_settings( array() );

		$this->assertSame( '', $result['api_token'] );
		$this->assertSame( '', $result['zone_id'] );
	}

	public function test_sanitize_preserves_existing_token_when_submitted_blank() {
		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'saved-token',
				'zone_id'   => 'zone-1',
			)
		);

		$result = Lookit_CF_Purge_Settings::sanitize_settings(
			array(
				'api_token' => '',
				'zone_id'   => 'zone-2',
			)
		);

		$this->assertSame( 'saved-token', $result['api_token'] );
		$this->assertSame( 'zone-2', $result['zone_id'] );
	}

	public function test_sanitize_replaces_token_when_new_value_submitted() {
		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'old-token',
				'zone_id'   => 'zone-1',
			)
		);

		$result = Lookit_CF_Purge_Settings::sanitize_settings(
			array(
				'api_token' => 'new-token',
				'zone_id'   => 'zone-1',
			)
		);

		$this->assertSame( 'new-token', $result['api_token'] );
	}

	public function test_getters_round_trip_saved_option() {
		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'token-123',
				'zone_id'   => 'zone-456',
			)
		);

		$this->assertSame( 'token-123', Lookit_CF_Purge_Settings::get_api_token() );
		$this->assertSame( 'zone-456', Lookit_CF_Purge_Settings::get_zone_id() );
	}

	public function test_is_configured_requires_both_values() {
		$this->assertFalse( Lookit_CF_Purge_Settings::is_configured() );

		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'token-only',
				'zone_id'   => '',
			)
		);
		$this->assertFalse( Lookit_CF_Purge_Settings::is_configured() );

		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'token',
				'zone_id'   => 'zone',
			)
		);
		$this->assertTrue( Lookit_CF_Purge_Settings::is_configured() );
	}
}
