<?php
/**
 * Keeps the cache in step with content: a release saved, scheduled or trashed shows up straight
 * away, a deleted product takes its synced data with it, and plugin updates re-read the
 * changelogs of installed plugins.
 *
 * @package Releaso
 */

namespace Releaso\Core;

use Releaso\Data\ChangelogService;
use Releaso\Data\PostTypes;
use Releaso\Data\ProductRepository;
use Releaso\Source\SourceRegistry;
use WP_Post;

/**
 * Content lifecycle hooks.
 */
class Lifecycle {

	/**
	 * Products.
	 *
	 * @var ProductRepository
	 */
	private $products;

	/**
	 * Changelog service.
	 *
	 * @var ChangelogService
	 */
	private $changelog;

	/**
	 * Sources.
	 *
	 * @var SourceRegistry
	 */
	private $sources;

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products  Products.
	 * @param ChangelogService  $changelog Changelog service.
	 * @param SourceRegistry    $sources   Sources.
	 */
	public function __construct( ProductRepository $products, ChangelogService $changelog, SourceRegistry $sources ) {
		$this->products  = $products;
		$this->changelog = $changelog;
		$this->sources   = $sources;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_status' ), 10, 3 );
		add_action( 'save_post_' . PostTypes::RELEASE, array( $this, 'flush' ) );
		add_action( 'deleted_post', array( $this, 'on_delete' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade' ), 20 );
		add_filter( 'wp_insert_post_data', array( $this, 'tidy_version' ) );
	}

	/**
	 * Any status change of a release or product (publish, schedule firing, trash, restore).
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function on_status( $new, $old, $post ) {
		if ( $new !== $old && in_array( $post->post_type, array( PostTypes::RELEASE, PostTypes::PRODUCT ), true ) ) {
			$this->products->reset();
			$this->changelog->flush();
		}
	}

	/**
	 * Flushes the cache.
	 *
	 * @return void
	 */
	public function flush() {
		$this->changelog->flush();
	}

	/**
	 * A product or release deleted for good.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post (WP 5.5+).
	 * @return void
	 */
	public function on_delete( $post_id, $post = null ) {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( PostTypes::PRODUCT === $type ) {
			$this->changelog->forget( $post_id );
		} elseif ( PostTypes::RELEASE === $type ) {
			$this->changelog->flush();
		}
	}

	/**
	 * After plugin updates, re-read installed plugins' changelogs.
	 *
	 * @return void
	 */
	public function on_upgrade() {
		foreach ( $this->products->all() as $product ) {
			$source = $this->sources->get( $product->source );
			if ( $source && ! $source->is_remote() ) {
				$this->changelog->sync( $product );
			}
		}
	}

	/**
	 * "v1.6.0 " becomes "1.6.0" for release titles.
	 *
	 * @param array $data Post data.
	 * @return array
	 */
	public function tidy_version( $data ) {
		if ( PostTypes::RELEASE === ( $data['post_type'] ?? '' ) ) {
			$data['post_title'] = \Releaso\Model\Release::normalize_version( $data['post_title'] );
		}
		return $data;
	}
}
