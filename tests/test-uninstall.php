<?php
/**
 * @package Lookit_CF_Purge
 */

class Test_Lookit_CF_Purge_Uninstall extends WP_UnitTestCase {

	public function test_uninstall_deletes_credentials_option() {
		update_option(
			Lookit_CF_Purge_Settings::OPTION_KEY,
			array(
				'api_token' => 'token',
				'zone_id'   => 'zone',
			)
		);

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'lookit-cf-purge/lookit-cf-purge.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( Lookit_CF_Purge_Settings::OPTION_KEY ) );
	}
}
