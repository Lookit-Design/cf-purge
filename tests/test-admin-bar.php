<?php
/**
 * @package Lookit_CF_Purge
 */

class Test_Lookit_CF_Purge_Admin_Bar extends WP_UnitTestCase {

	private $previous_user;

	public function set_up() {
		parent::set_up();
		$this->previous_user = get_current_user_id();
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user );
		remove_action( 'wp_footer', array( 'Lookit_CF_Purge_Admin_Bar', 'render_dialog' ) );
		remove_action( 'admin_footer', array( 'Lookit_CF_Purge_Admin_Bar', 'render_dialog' ) );
		parent::tear_down();
	}

	public function test_admin_has_one_action_and_a_dialog_footer_callback() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->go_to( home_url( '/' ) );
		$bar = new WP_Admin_Bar();
		Lookit_CF_Purge_Admin_Bar::add_purge_button( $bar );

		$this->assertCount( 2, $bar->get_nodes() );
		$action = $bar->get_node( 'lookit-cf-purge-open' );
		$this->assertSame( 'lookit-cf-purge-group', $action->parent );
		$this->assertSame( '#lookit-cf-dialog', $action->href );
		$this->assertSame( 10, has_action( 'wp_footer', array( 'Lookit_CF_Purge_Admin_Bar', 'render_dialog' ) ) );
	}

	public function test_subscriber_cannot_render_the_menu_or_dialog() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$bar = new WP_Admin_Bar();
		Lookit_CF_Purge_Admin_Bar::add_purge_button( $bar );
		$this->assertEmpty( $bar->get_nodes() );

		ob_start();
		Lookit_CF_Purge_Admin_Bar::render_dialog();
		$this->assertSame( '', ob_get_clean() );
	}
}
