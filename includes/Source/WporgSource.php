<?php
/**
 * A plugin hosted on WordPress.org: its readme.txt from the plugin's SVN repository.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;
use WP_Error;

/**
 * WordPress.org source.
 */
class WporgSource extends AbstractSource {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key() {
		return 'wporg';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'WordPress.org plugin', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Reads the changelog from the plugin\'s readme.txt on WordPress.org and keeps it up to date.', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function fields() {
		return array(
			'slug'   => array(
				'label'       => __( 'Plugin slug', 'releaso' ),
				'type'        => 'text',
				'placeholder' => 'slider-blocks',
				'help'        => __( 'The part after wordpress.org/plugins/ in the plugin\'s URL.', 'releaso' ),
				'required'    => true,
			),
			'branch' => array(
				'label'   => __( 'Read from', 'releaso' ),
				'type'    => 'select',
				'default' => 'trunk',
				'options' => array(
					'trunk'  => __( 'Trunk (latest development readme)', 'releaso' ),
					'stable' => __( 'Stable tag (the released version)', 'releaso' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $config Raw.
	 * @return array
	 */
	public function sanitize( array $config ) {
		$out = parent::sanitize( $config );
		// Accept a pasted URL: https://wordpress.org/plugins/slider-blocks/ → slider-blocks.
		if ( preg_match( '#WordPress\.org/plugins/([a-z0-9-]+)#i', $out['slug'], $m ) ) {
			$out['slug'] = $m[1];
		}
		$out['slug'] = sanitize_title( $out['slug'] );
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
		$slug = (string) $product->get( 'slug' );
		if ( '' === $slug ) {
			return new WP_Error( 'releaso_config', __( 'Enter the plugin slug.', 'releaso' ) );
		}

		$base = 'https://plugins.svn.wordpress.org/' . rawurlencode( $slug );
		$url  = $base . '/trunk/readme.txt';

		if ( 'stable' === $product->get( 'branch' ) ) {
			$info = $this->http->get( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . rawurlencode( $slug ) . '&request[fields][sections]=0' );
			if ( ! is_wp_error( $info ) ) {
				$data = json_decode( $info['body'], true );
				$tag  = is_array( $data ) ? (string) ( $data['version'] ?? '' ) : '';
				if ( '' !== $tag && 'trunk' !== $tag ) {
					$url = $base . '/tags/' . rawurlencode( $tag ) . '/readme.txt';
				}
			}
		}

		/**
		 * Filters the readme URL of a WordPress.org plugin.
		 *
		 * @param string  $url     URL.
		 * @param Product $product Product.
		 */
		$url = apply_filters( 'releaso_wporg_readme_url', $url, $product );

		$result = $this->fetch_url( $url, 'readme', $state );
		if ( is_wp_error( $result ) && 404 === ( $result->get_error_data()['status'] ?? 0 ) ) {
			/* translators: %s: plugin slug */
			return new WP_Error( 'releaso_not_found', sprintf( __( 'No plugin "%s" on WordPress.org.', 'releaso' ), $slug ) );
		}
		return $result;
	}
}
