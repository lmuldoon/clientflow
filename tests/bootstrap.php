<?php
/**
 * PHPUnit bootstrap for ClientFlow tests.
 *
 * Loads the WordPress test suite and the plugin itself.
 *
 * @package ClientFlow
 */

declare( strict_types=1 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "$_tests_dir/includes/functions.php" ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	echo "Please run: bin/install-wp-tests.sh to set up the WP test suite.\n";
	exit( 1 );
}

// Load the WordPress test suite.
require_once "$_tests_dir/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/clientflow.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require "$_tests_dir/includes/bootstrap.php";
