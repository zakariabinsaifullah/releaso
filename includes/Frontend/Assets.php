<?php
/**
 * Front-end assets, registered everywhere and enqueued only where a changelog renders.
 *
 * @package Releaso
 */

namespace Releaso\Frontend;

use Releaso\Support\ChangeTypes;
use Releaso\Support\Settings;

/**
 * Assets.
 */
class Assets {

	const HANDLE = 'releaso';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers the style and script.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( self::HANDLE, RELEASO_URL . 'assets/css/releaso.css', array(), self::version( 'assets/css/releaso.css' ) );
		wp_add_inline_style( self::HANDLE, $this->inline_css() );

		wp_register_script(
			self::HANDLE,
			RELEASO_URL . 'assets/js/releaso.js',
			array(),
			self::version( 'assets/js/releaso.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			self::HANDLE,
			'releasoI18n',
			array(
				'copied'    => __( 'Link copied', 'releaso' ),
				'inAddress' => __( 'Link in the address bar', 'releaso' ),
			)
		);
	}

	/**
	 * Enqueues the assets (called by the renderer).
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Accent colour and type colours from settings / registered types.
	 *
	 * @return string
	 */
	private function inline_css() {
		$vars  = array();
		$rules = array();
		foreach ( ChangeTypes::all() as $key => $type ) {
			$color = sanitize_hex_color( $type['color'] );
			if ( $color ) {
				$vars[] = '--releaso-' . $key . ':' . $color;
			}
			// Keys are sanitize_key()'d, safe in selectors.
			$rules[] = ".releaso__filter--{$key}{--dot:var(--releaso-{$key})}"
				. ".releaso__pill--{$key},.releaso__type--{$key},.releaso__item[data-type=\"{$key}\"]{--c:var(--releaso-{$key})}";
		}
		$accent = sanitize_hex_color( (string) $this->settings->get( 'accent_color', '' ) );
		if ( $accent ) {
			$vars[] = '--releaso-accent:' . $accent;
		}
		return '.releaso{' . implode( ';', $vars ) . '}' . implode( '', $rules );
	}

	/**
	 * File version for cache busting: modification time in development, plugin version otherwise.
	 *
	 * @param string $path Path relative to the plugin.
	 * @return string
	 */
	public static function version( $path ) {
		$file = RELEASO_DIR . '/' . $path;
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_readable( $file ) ) ? (string) filemtime( $file ) : RELEASO_VERSION;
	}
}
