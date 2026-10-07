<?php
/**
 * A plugin installed on this site: its readme.txt, CHANGELOG.md or changelog.txt.
 * Synced again whenever plugins are updated, so it follows the installed version.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;
use WP_Error;

/**
 * Installed plugin source.
 */
class InstalledPluginSource extends AbstractSource {

	const CANDIDATES = array( 'readme.txt', 'README.txt', 'CHANGELOG.md', 'changelog.md', 'changelog.txt', 'CHANGELOG.txt', 'readme.md', 'README.md' );

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key() {
		return 'installed';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Installed plugin', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Reads a plugin on this site (readme.txt or CHANGELOG.md). Great for Pro plugins that are not on WordPress.org.', 'releaso' );
	}

	/**
	 * Reads local files: no network involved.
	 *
	 * @return bool
	 */
	public function is_remote() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function fields() {
		$plugins = array( '' => __( '— Choose a plugin —', 'releaso' ) );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			$folder = dirname( $file );
			if ( '.' !== $folder ) {
				$plugins[ $folder ] = $data['Name'] . ' (' . $folder . ')';
			}
		}

		return array(
			'plugin' => array(
				'label'    => __( 'Plugin', 'releaso' ),
				'type'     => 'select',
				'options'  => $plugins,
				'default'  => '',
				'required' => true,
			),
			'file'   => array(
				'label'       => __( 'File (optional)', 'releaso' ),
				'type'        => 'text',
				'placeholder' => 'readme.txt',
				'help'        => __( 'Path inside the plugin folder. Leave empty to find readme.txt or CHANGELOG.md.', 'releaso' ),
			),
			'format' => $this->format_field(),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $config Raw.
	 * @return array
	 */
	public function sanitize( array $config ) {
		$out           = parent::sanitize( $config );
		$out['plugin'] = sanitize_file_name( (string) ( $config['plugin'] ?? '' ) );
		$out['file']   = ltrim( str_replace( array( '..', '\\' ), '', $out['file'] ), '/' );
		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Product $product Product.
	 * @param array   $state   State.
	 * @return FetchResult|WP_Error
	 */
	public function fetch( Product $product, array $state ) {
		$folder = (string) $product->get( 'plugin' );
		if ( '' === $folder ) {
			return new WP_Error( 'releaso_config', __( 'Choose a plugin.', 'releaso' ) );
		}

		$root = realpath( WP_PLUGIN_DIR . '/' . $folder );
		if ( ! $root || 0 !== strpos( $root, realpath( WP_PLUGIN_DIR ) . DIRECTORY_SEPARATOR ) || ! is_dir( $root ) ) {
			/* translators: %s: plugin folder */
			return new WP_Error( 'releaso_missing', sprintf( __( 'The plugin folder "%s" was not found.', 'releaso' ), $folder ) );
		}

		$explicit = (string) $product->get( 'file' );
		$contents = array();
		foreach ( '' !== $explicit ? array( $explicit ) : self::CANDIDATES as $name ) {
			$path = realpath( $root . '/' . $name );
			// realpath() also de-duplicates readme.txt / README.txt on case-insensitive disks.
			if ( $path && ! isset( $contents[ $path ] ) && 0 === strpos( $path, $root . DIRECTORY_SEPARATOR ) && is_file( $path ) && is_readable( $path ) ) {
				$contents[ $path ] = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		$hash = md5( implode( "\0", array_keys( $contents ) ) . implode( "\0", $contents ) . $product->get( 'format' ) );
		if ( $contents && ( $state['hash'] ?? '' ) === $hash ) {
			return FetchResult::not_modified( $state );
		}

		// Many plugins keep only the latest version in readme.txt and the full history in
		// changelog.txt / CHANGELOG.md: use whichever file has the most releases.
		$best  = null;
		$error = null;
		foreach ( $contents as $path => $content ) {
			$name   = basename( $path );
			$result = $this->parse(
				$content,
				(string) $product->get( 'format', 'auto' ),
				$name,
				array(
					'hash' => $hash,
					'file' => $name,
				)
			);
			if ( is_wp_error( $result ) ) {
				$error = $error ? $error : $result;
			} elseif ( ! $best || count( $result->releases ) > count( $best->releases ) ) {
				$best = $result;
			}
		}

		if ( $best ) {
			return $best;
		}
		if ( $error && '' !== $explicit ) {
			return $error;
		}

		/* translators: %s: plugin folder */
		return new WP_Error( 'releaso_missing', sprintf( __( 'No changelog file found in the "%s" plugin folder.', 'releaso' ), $folder ) );
	}
}
