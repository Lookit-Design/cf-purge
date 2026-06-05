<?php
/**
 * PHPUnit bootstrap for the WordPress integration test suite.
 *
 * @package Lookit_CF_Purge
 */

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tmp_dir   = getenv( 'TMPDIR' ) ? rtrim( getenv( 'TMPDIR' ), '/\\' ) : sys_get_temp_dir();
	$_tests_dir = $_tmp_dir . '/wordpress-tests-lib';
}

$_functions = $_tests_dir . '/includes/functions.php';

if ( ! file_exists( $_functions ) ) {
	echo "Could not find the WordPress test suite at {$_tests_dir}." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput
	echo 'Run bin/install-wp-tests.sh first.' . PHP_EOL;
	exit( 1 );
}

require_once $_functions;

/**
 * Load the plugin under test.
 */
function _lookit_cf_purge_load_plugin() {
	require dirname( __DIR__ ) . '/lookit-cf-purge.php';
}
tests_add_filter( 'muplugins_loaded', '_lookit_cf_purge_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
