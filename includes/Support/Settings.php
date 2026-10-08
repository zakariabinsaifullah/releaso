<?php
/**
 * Typed access to the plugin settings (one autoloaded option).
 *
 * @package Releaso
 */

namespace Releaso\Support;

/**
 * Settings store.
 */
class Settings {

	const OPTION = 'releaso_settings';

	/**
	 * Cached values.
	 *
	 * @var array|null
	 */
	private $values = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'sync_interval'      => 12,      // hours between background syncs of remote sources.
			'http_timeout'       => 10,      // seconds.
			'github_token'       => '',      // for private repositories; RELEASO_GITHUB_TOKEN in wp-config.php wins.
			'include_unreleased' => false,   // show "Unreleased" sections of Markdown changelogs.
			'default_layout'     => 'timeline',
			'default_per_page'   => 6,
			'accent_color'       => '',
			'date_format'        => '',      // '' uses the site's date format.
			'delete_data'        => false,   // remove everything on uninstall.
			'types'              => array(), // change type labels, colours and custom types, see ChangeTypes.
		);
	}

	/**
	 * All settings, defaults filled in.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->values ) {
			$stored       = get_option( self::OPTION, array() );
			$this->values = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		/**
		 * Filters the Releaso settings.
		 *
		 * @param array $values Settings.
		 */
		return apply_filters( 'releaso_settings', $this->values );
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Fallback when unknown.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * The GitHub token: the RELEASO_GITHUB_TOKEN constant, or the saved setting.
	 *
	 * @return string
	 */
	public function github_token() {
		if ( defined( 'RELEASO_GITHUB_TOKEN' ) && RELEASO_GITHUB_TOKEN ) {
			return (string) RELEASO_GITHUB_TOKEN;
		}
		return (string) $this->get( 'github_token', '' );
	}

	/**
	 * Forgets cached values (after an update).
	 *
	 * @return void
	 */
	public function reset() {
		$this->values = null;
	}

	/**
	 * Sanitizes submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = $this->all();
		$defaults = self::defaults();

		$token = isset( $input['github_token'] ) ? trim( sanitize_text_field( $input['github_token'] ) ) : '';
		// The field shows a mask; an untouched mask keeps the saved token.
		if ( '' !== $token && preg_match( '/^•+$/u', $token ) ) {
			$token = $current['github_token'];
		}

		$layout = sanitize_key( $input['default_layout'] ?? $defaults['default_layout'] );

		$this->reset();

		return array(
			'sync_interval'      => in_array( (int) ( $input['sync_interval'] ?? 0 ), array( 1, 6, 12, 24, 168 ), true ) ? (int) $input['sync_interval'] : $defaults['sync_interval'],
			'http_timeout'       => min( 60, max( 3, absint( $input['http_timeout'] ?? $defaults['http_timeout'] ) ) ),
			'github_token'       => $token,
			'include_unreleased' => ! empty( $input['include_unreleased'] ),
			'default_layout'     => in_array( $layout, array_keys( \Releaso\Frontend\Renderer::layouts() ), true ) ? $layout : $defaults['default_layout'],
			'default_per_page'   => min( 100, max( 1, absint( $input['default_per_page'] ?? $defaults['default_per_page'] ) ) ),
			'accent_color'       => (string) sanitize_hex_color( $input['accent_color'] ?? '' ),
			'date_format'        => sanitize_text_field( $input['date_format'] ?? '' ),
			'delete_data'        => ! empty( $input['delete_data'] ),
			'types'              => ChangeTypes::sanitize( $input['types'] ?? array() ),
		);
	}
}
