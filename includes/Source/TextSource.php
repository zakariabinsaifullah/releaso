<?php
/**
 * A changelog pasted (or loaded from a file) into the product: readme.txt, Markdown or JSON.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;
use WP_Error;

/**
 * Pasted text source.
 */
class TextSource extends AbstractSource {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key() {
		return 'text';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Paste or upload a file', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Paste a changelog, or load a .txt, .md or .json file into the box. Edit it here whenever you release.', 'releaso' );
	}

	/**
	 * Stored with the product: no network involved.
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
		return array(
			'content' => array(
				'label'       => __( 'Changelog', 'releaso' ),
				'type'        => 'textarea',
				'placeholder' => "= 1.2.0 - 2026-09-29 =\n* Added: New Hover Slider designs\n* Fixed (Pro): Panorama height on mobile",
				'file'        => '.txt,.md,.markdown,.json',
				'required'    => true,
			),
			'format'  => $this->format_field(),
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
		$content = (string) $product->get( 'content' );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'releaso_config', __( 'Paste a changelog.', 'releaso' ) );
		}
		$hash = md5( $content . $product->get( 'format' ) );
		if ( ( $state['hash'] ?? '' ) === $hash ) {
			return FetchResult::not_modified( $state );
		}
		return $this->parse( $content, (string) $product->get( 'format', 'auto' ), '', array( 'hash' => $hash ) );
	}
}
