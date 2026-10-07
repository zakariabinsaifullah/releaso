<?php
/**
 * No external source: the product shows only the releases written under Releaso → Releases.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;
use Releaso\Model\ReleaseCollection;

/**
 * Manual-only source.
 */
class ManualSource extends AbstractSource {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key() {
		return 'manual';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Releases written here only', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Write each release under Releaso → Releases, or import them from a file under Releaso → Import / Export.', 'releaso' );
	}

	/**
	 * Nothing to fetch.
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
		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Product $product Product.
	 * @param array   $state   State.
	 * @return FetchResult
	 */
	public function fetch( Product $product, array $state ) {
		return FetchResult::releases( new ReleaseCollection(), array( 'format' => 'manual' ) );
	}
}
