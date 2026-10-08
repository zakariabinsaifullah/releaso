<?php
/**
 * PSR-4 autoloader for the Releaso namespace, so the plugin runs without `composer install`.
 * Composer's autoloader (vendor/) is used instead when present, e.g. in development.
 *
 * @package Releaso
 */

defined( 'ABSPATH' ) || exit;

if ( is_readable( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Releaso\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
