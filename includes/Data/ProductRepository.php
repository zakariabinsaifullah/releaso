<?php
/**
 * Reads and writes products (releaso_product posts and their source settings).
 *
 * @package Releaso
 */

namespace Releaso\Data;

use Releaso\Model\Product;
use WP_Post;

/**
 * Product repository.
 */
class ProductRepository {

	const META_SOURCE = '_releaso_source';
	const META_CONFIG = '_releaso_config';
	const META_LINK   = '_releaso_link';

	/**
	 * Published products for this request.
	 *
	 * @var Product[]|null
	 */
	private $published = null;

	/**
	 * Published products in menu order.
	 *
	 * @return Product[]
	 */
	public function all() {
		if ( null === $this->published ) {
			$posts = get_posts(
				array(
					'post_type'              => PostTypes::PRODUCT,
					'post_status'            => 'publish',
					'posts_per_page'         => 200,
					'orderby'                => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);

			$this->published = array_map( array( $this, 'from_post' ), $posts );
		}
		return $this->published;
	}

	/**
	 * Products by slug (published only), in the order asked for; empty asks for all.
	 *
	 * @param string[] $slugs Slugs or IDs.
	 * @return Product[]
	 */
	public function pick( array $slugs ) {
		$all = $this->all();
		if ( ! $slugs ) {
			return $all;
		}
		$out = array();
		foreach ( $slugs as $wanted ) {
			$wanted = trim( (string) $wanted );
			foreach ( $all as $product ) {
				if ( $product->slug === $wanted || (string) $product->id === $wanted ) {
					$out[ $product->id ] = $product;
				}
			}
		}
		return array_values( $out );
	}

	/**
	 * A product by ID, any status.
	 *
	 * @param int $id Post ID.
	 * @return Product|null
	 */
	public function find( $id ) {
		$post = get_post( (int) $id );
		return $post && PostTypes::PRODUCT === $post->post_type ? $this->from_post( $post ) : null;
	}

	/**
	 * A published product by slug or ID.
	 *
	 * @param string|int $slug Slug or ID.
	 * @return Product|null
	 */
	public function find_by_slug( $slug ) {
		$found = $this->pick( array( (string) $slug ) );
		return $found[0] ?? null;
	}

	/**
	 * Builds a product from its post.
	 *
	 * @param WP_Post $post Post.
	 * @return Product
	 */
	public function from_post( WP_Post $post ) {
		$config = get_post_meta( $post->ID, self::META_CONFIG, true );
		$source = (string) get_post_meta( $post->ID, self::META_SOURCE, true );

		return new Product(
			array(
				'id'     => $post->ID,
				'slug'   => $post->post_name ? $post->post_name : sanitize_title( $post->post_title ),
				'name'   => $post->post_title,
				'source' => '' !== $source ? $source : 'manual',
				'config' => is_array( $config ) ? $config : array(),
				'link'   => (string) get_post_meta( $post->ID, self::META_LINK, true ),
				'status' => $post->post_status,
			)
		);
	}

	/**
	 * Saves a product's source settings.
	 *
	 * @param int    $id     Post ID.
	 * @param string $source Source key.
	 * @param array  $config Sanitized settings.
	 * @param string $link   Product link.
	 * @return void
	 */
	public function save_source( $id, $source, array $config, $link ) {
		update_post_meta( $id, self::META_SOURCE, $source );
		update_post_meta( $id, self::META_CONFIG, $config );
		update_post_meta( $id, self::META_LINK, $link );
		$this->published = null;
	}

	/**
	 * Forgets the request cache.
	 *
	 * @return void
	 */
	public function reset() {
		$this->published = null;
	}
}
