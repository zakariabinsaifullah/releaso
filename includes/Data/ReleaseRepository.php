<?php
/**
 * Releases written in the admin (releaso_release posts). The title is the version, the publish
 * date is the release date, so drafts stay hidden and scheduled releases appear on their date.
 *
 * @package Releaso
 */

namespace Releaso\Data;

use Releaso\Model\Change;
use Releaso\Model\Release;
use Releaso\Model\ReleaseCollection;
use Releaso\Support\ChangeTypes;
use WP_Post;

/**
 * Manual release repository.
 */
class ReleaseRepository {

	const META_PRODUCT = '_releaso_product';
	const META_CHANGES = '_releaso_changes';
	const META_NOTES   = '_releaso_notes';
	const META_TITLE   = '_releaso_title';
	const META_URL     = '_releaso_url';

	/**
	 * Published releases of a product.
	 *
	 * @param int $product_id Product ID.
	 * @return ReleaseCollection
	 */
	public function for_product( $product_id ) {
		$posts = get_posts(
			array(
				'post_type'              => PostTypes::RELEASE,
				'post_status'            => 'publish',
				'posts_per_page'         => 500,
				'meta_key'               => self::META_PRODUCT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => (int) $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$out = new ReleaseCollection();
		foreach ( $posts as $post ) {
			$out->add( $this->from_post( $post ) );
		}
		return $out;
	}

	/**
	 * Builds a release from its post.
	 *
	 * @param WP_Post $post Post.
	 * @return Release
	 */
	public function from_post( WP_Post $post ) {
		$release         = new Release( $post->post_title, get_the_date( 'Y-m-d', $post ) );
		$release->origin = Release::ORIGIN_MANUAL;
		$release->title  = (string) get_post_meta( $post->ID, self::META_TITLE, true );
		$release->notes  = (string) get_post_meta( $post->ID, self::META_NOTES, true );
		$release->url    = (string) get_post_meta( $post->ID, self::META_URL, true );

		foreach ( (array) get_post_meta( $post->ID, self::META_CHANGES, true ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$change = Change::from_array( $row );
			if ( ! ChangeTypes::exists( $change->type ) ) {
				$change->type = 'improved';
			}
			$release->add( $change );
		}
		return $release;
	}

	/**
	 * The post of a product's version, any status but trash.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $version    Version.
	 * @return WP_Post|null
	 */
	public function find_post( $product_id, $version ) {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::RELEASE,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'title'          => Release::normalize_version( $version ),
				'posts_per_page' => 1,
				'meta_key'       => self::META_PRODUCT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);
		return $posts[0] ?? null;
	}

	/**
	 * Creates or updates a release post.
	 *
	 * @param int     $product_id Product ID.
	 * @param Release $release    Release.
	 * @param bool    $overwrite  Replace an existing release of the same version.
	 * @param string  $status     Post status for new releases.
	 * @return string 'created', 'updated' or 'skipped'; WP_Error message on failure.
	 */
	public function save( $product_id, Release $release, $overwrite = false, $status = 'publish' ) {
		$existing = $this->find_post( $product_id, $release->version );
		if ( $existing && ! $overwrite ) {
			return 'skipped';
		}

		$date = $release->date ? $release->date . ' 12:00:00' : current_time( 'mysql' );
		$args = array(
			'post_type'   => PostTypes::RELEASE,
			'post_title'  => $release->version,
			'post_status' => $existing ? $existing->post_status : $status,
			'post_date'   => $date,
		);
		if ( $existing ) {
			$args['ID']            = $existing->ID;
			$args['edit_date']     = true;
			$args['post_date_gmt'] = get_gmt_from_date( $date );
		}

		$id = $existing ? wp_update_post( $args, true ) : wp_insert_post( $args, true );
		if ( is_wp_error( $id ) ) {
			return $id->get_error_message();
		}

		$this->write_meta( $id, $product_id, $release );
		return $existing ? 'updated' : 'created';
	}

	/**
	 * Writes a release's meta.
	 *
	 * @param int     $post_id    Release post ID.
	 * @param int     $product_id Product ID.
	 * @param Release $release    Release.
	 * @return void
	 */
	public function write_meta( $post_id, $product_id, Release $release ) {
		update_post_meta( $post_id, self::META_PRODUCT, (int) $product_id );
		update_post_meta(
			$post_id,
			self::META_CHANGES,
			array_map(
				static function ( Change $change ) {
					return $change->to_array();
				},
				$release->changes
			)
		);
		update_post_meta( $post_id, self::META_TITLE, $release->title );
		update_post_meta( $post_id, self::META_NOTES, $release->notes );
		update_post_meta( $post_id, self::META_URL, $release->url );
	}
}
