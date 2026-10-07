<?php
/**
 * Loads templates, letting a theme override them by copying a file to
 * `yourtheme/releaso/{name}.php` (child theme first, then parent).
 *
 * @package Releaso
 */

namespace Releaso\Support;

/**
 * Template loader.
 */
class Template {

	/**
	 * Path of a template, the theme's copy when there is one.
	 *
	 * @param string $name Template name without .php, e.g. "layouts/timeline".
	 * @return string
	 */
	public static function locate( $name ) {
		$name  = trim( str_replace( array( '..', '\\' ), '', (string) $name ), '/' );
		$found = locate_template( array( 'releaso/' . $name . '.php' ) );
		$path  = $found ? $found : RELEASO_DIR . '/templates/' . $name . '.php';

		/**
		 * Filters the path of a Releaso template.
		 *
		 * @param string $path Path.
		 * @param string $name Template name.
		 */
		return apply_filters( 'releaso_template', $path, $name );
	}

	/**
	 * Renders a template to a string.
	 *
	 * @param string $name Template name.
	 * @param array  $vars Variables available in the template.
	 * @return string
	 */
	public static function render( $name, array $vars = array() ) {
		$path = self::locate( $name );
		if ( ! is_readable( $path ) ) {
			return '';
		}
		ob_start();
		( static function ( $releaso_path, $releaso_vars ) {
			extract( $releaso_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
			include $releaso_path;
		} )( $path, $vars );
		return (string) ob_get_clean();
	}
}
