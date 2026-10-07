<?php
/**
 * Where a product's changelog comes from. Register custom sources with the `releaso_sources`
 * filter (e.g. a licensing server for a Pro plugin).
 *
 * Example:
 *
 *     add_filter( 'releaso_sources', function ( $sources, $http, $settings ) {
 *         $sources[] = new My_Edd_Source( $http, $settings ); // extends Releaso\Source\AbstractSource
 *         return $sources;
 *     }, 10, 3 );
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;

/**
 * Changelog source.
 */
interface SourceInterface {

	/**
	 * Unique key, stored with the product.
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Name shown in the admin.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * One-line explanation shown in the admin.
	 *
	 * @return string
	 */
	public function description();

	/**
	 * Settings fields: key => [ label, type (text|url|textarea|select|checkbox|password), placeholder, help, options, required ].
	 *
	 * @return array<string,array>
	 */
	public function fields();

	/**
	 * Cleans submitted settings.
	 *
	 * @param array $config Raw settings.
	 * @return array
	 */
	public function sanitize( array $config );

	/**
	 * Whether fetching costs a network request (and so runs in the background on a schedule).
	 *
	 * @return bool
	 */
	public function is_remote();

	/**
	 * Fetches and parses the changelog.
	 *
	 * @param Product $product Product.
	 * @param array   $state   Last sync state (etag, last_modified …) for conditional requests.
	 * @return FetchResult|\WP_Error
	 */
	public function fetch( Product $product, array $state );
}
