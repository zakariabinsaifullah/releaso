<?php
/**
 * Any URL serving a readme.txt, CHANGELOG.md or JSON changelog: a Pro plugin's update server,
 * a docs site, a CDN. An optional Authorization header covers protected endpoints.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;
use WP_Error;

/**
 * Remote URL source.
 */
class UrlSource extends AbstractSource {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key() {
		return 'url';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Remote URL', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Any readme.txt, CHANGELOG.md or JSON file on the web, e.g. on your Pro plugin\'s update server.', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function fields() {
		return array(
			'url'    => array(
				'label'       => __( 'URL', 'releaso' ),
				'type'        => 'url',
				'placeholder' => 'https://example.com/pro/readme.txt',
				'required'    => true,
			),
			'format' => $this->format_field(),
			'auth'   => array(
				'label'       => __( 'Authorization header (optional)', 'releaso' ),
				'type'        => 'password',
				'placeholder' => 'Bearer …',
				'help'        => __( 'Sent as the Authorization header, for protected endpoints. Never shown publicly.', 'releaso' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Product $product Product.
	 * @param array   $state   State.
	 * @return FetchResult|WP_Error
	 */
	public function fetch( Product $product, array $state ) {
		$url = (string) $product->get( 'url' );
		if ( '' === $url ) {
			return new WP_Error( 'releaso_config', __( 'Enter the changelog URL.', 'releaso' ) );
		}
		$headers = array();
		if ( '' !== (string) $product->get( 'auth' ) ) {
			$headers['Authorization'] = (string) $product->get( 'auth' );
		}
		return $this->fetch_url( $url, (string) $product->get( 'format', 'auto' ), $state, $headers );
	}
}
