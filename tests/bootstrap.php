<?php
/**
 * Unit test bootstrap. The parser and model layer is plain PHP, so it is tested without
 * loading WordPress. Integration tests (sources, REST, admin) would use wp-env + WP_UnitTestCase.
 *
 * @package Releaso
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( is_readable( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require dirname( __DIR__ ) . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class ) {
			if ( 0 === strpos( $class, 'Releaso\\' ) ) {
				$file = dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', substr( $class, 8 ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		}
	);
}
