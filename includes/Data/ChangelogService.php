<?php
/**
 * The heart of Releaso: syncs each product's source into a stored snapshot, joins it with the
 * releases written in the admin, and caches the result.
 *
 * Pages never wait on the network: remote sources are synced in the background (WP-Cron), on
 * product save, from the admin "Sync now" button, WP-CLI or the REST API. A failed sync keeps
 * the last good snapshot and records the error for the admin.
 *
 * @package Releaso
 */

namespace Releaso\Data;

use Releaso\Model\Product;
use Releaso\Model\ReleaseCollection;
use Releaso\Source\SourceRegistry;
use Releaso\Support\Settings;
use WP_Error;

/**
 * Changelog service.
 */
class ChangelogService {

	const META_SNAPSHOT = '_releaso_snapshot';
	const META_STATE    = '_releaso_state';
	const CACHE_VERSION = 'releaso_cache_version';
	const CACHE_TTL     = DAY_IN_SECONDS;
	const LOCK_TTL      = 120;

	/**
	 * Sources.
	 *
	 * @var SourceRegistry
	 */
	private $sources;

	/**
	 * Products.
	 *
	 * @var ProductRepository
	 */
	private $products;

	/**
	 * Manual releases.
	 *
	 * @var ReleaseRepository
	 */
	private $releases;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Merged releases resolved in this request.
	 *
	 * @var ReleaseCollection[]
	 */
	private $memo = array();

	/**
	 * Constructor.
	 *
	 * @param SourceRegistry    $sources  Sources.
	 * @param ProductRepository $products Products.
	 * @param ReleaseRepository $releases Manual releases.
	 * @param Settings          $settings Settings.
	 */
	public function __construct( SourceRegistry $sources, ProductRepository $products, ReleaseRepository $releases, Settings $settings ) {
		$this->sources  = $sources;
		$this->products = $products;
		$this->releases = $releases;
		$this->settings = $settings;
	}

	/**
	 * A product's releases: source snapshot joined with manual releases, newest first.
	 * Manual releases replace the source's notes for the same version.
	 *
	 * @param Product $product Product.
	 * @return ReleaseCollection
	 */
	public function releases( Product $product ) {
		if ( isset( $this->memo[ $product->id ] ) ) {
			return $this->memo[ $product->id ];
		}

		$key    = 'releaso_r_' . $product->id . '_' . $this->cache_version();
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			$merged = ReleaseCollection::from_array( $cached );
		} else {
			// Never synced (a product created by code or import): sync once now.
			if ( ! $this->state( $product ) ) {
				$this->sync( $product );
			}
			$merged = $this->snapshot( $product )->merge( $this->releases->for_product( $product->id ) );
			set_transient( $key, $merged->to_array(), self::CACHE_TTL );
		}

		/**
		 * Filters a product's releases before display.
		 *
		 * @param ReleaseCollection $merged  Releases, newest first.
		 * @param Product           $product Product.
		 */
		$merged = apply_filters( 'releaso_releases', $merged, $product );

		$this->memo[ $product->id ] = $merged;
		return $merged;
	}

	/**
	 * The last synced releases from the product's source.
	 *
	 * @param Product $product Product.
	 * @return ReleaseCollection
	 */
	public function snapshot( Product $product ) {
		$rows = get_post_meta( $product->id, self::META_SNAPSHOT, true );
		return is_array( $rows ) ? ReleaseCollection::from_array( $rows ) : new ReleaseCollection();
	}

	/**
	 * Sync state: synced_at, checked_at, error, count, latest, format, plus source validators.
	 *
	 * @param Product $product Product.
	 * @return array
	 */
	public function state( Product $product ) {
		$state = get_post_meta( $product->id, self::META_STATE, true );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Fetches the product's source and stores the snapshot.
	 *
	 * @param Product $product Product.
	 * @param bool    $force   Ignore validators and fetch everything again.
	 * @return true|WP_Error
	 */
	public function sync( Product $product, $force = false ) {
		$source = $this->sources->get( $product->source );
		if ( ! $source ) {
			/* translators: %s: source key */
			return $this->record_error( $product, new WP_Error( 'releaso_source', sprintf( __( 'Unknown source "%s".', 'releaso' ), $product->source ) ) );
		}

		if ( ! $this->lock( $product->id ) ) {
			return new WP_Error( 'releaso_locked', __( 'A sync of this product is already running.', 'releaso' ) );
		}

		$state  = $this->state( $product );
		$result = $source->fetch( $product, $force ? array() : $state );
		$this->unlock( $product->id );

		/**
		 * Fires after a product's source was fetched.
		 *
		 * @param \Releaso\Source\FetchResult|WP_Error $result  Result.
		 * @param Product                              $product Product.
		 */
		do_action( 'releaso_fetched', $result, $product );

		if ( is_wp_error( $result ) ) {
			return $this->record_error( $product, $result );
		}

		$now  = time();
		$next = array_merge(
			$result->state,
			array(
				'source'     => $product->source,
				'checked_at' => $now,
				'synced_at'  => $result->not_modified ? ( $state['synced_at'] ?? $now ) : $now,
				'error'      => '',
			)
		);

		if ( ! $result->not_modified ) {
			$releases = $result->releases->sorted();
			update_post_meta( $product->id, self::META_SNAPSHOT, $releases->to_array() );
			$latest          = $releases->latest();
			$next['count']   = count( $releases );
			$next['latest']  = $latest ? $latest->version : '';
			$next['changed'] = $now;
		} else {
			$next['count']   = $state['count'] ?? 0;
			$next['latest']  = $state['latest'] ?? '';
			$next['changed'] = $state['changed'] ?? $now;
		}

		update_post_meta( $product->id, self::META_STATE, $next );

		if ( ! $result->not_modified ) {
			$this->flush();
			/**
			 * Fires when a product's synced changelog changed.
			 *
			 * @param Product $product Product.
			 * @param array   $next    New state.
			 * @param array   $state   Previous state.
			 */
			do_action( 'releaso_changelog_updated', $product, $next, $state );
		}

		return true;
	}

	/**
	 * Syncs every product whose source is due (remote: the sync interval; local: always cheap).
	 *
	 * @param bool $force Sync everything regardless of the interval.
	 * @return array<int,true|WP_Error> Results by product ID.
	 */
	public function sync_all( $force = false ) {
		$results = array();
		foreach ( $this->products->all() as $product ) {
			if ( $force || $this->is_due( $product ) ) {
				$results[ $product->id ] = $this->sync( $product, $force );
			}
		}
		return $results;
	}

	/**
	 * Whether a product should be synced now.
	 *
	 * @param Product $product Product.
	 * @return bool
	 */
	public function is_due( Product $product ) {
		$state  = $this->state( $product );
		$source = $this->sources->get( $product->source );
		if ( ! $state || ( $state['source'] ?? '' ) !== $product->source ) {
			return true;
		}
		$interval = $source && $source->is_remote()
			? (int) $this->settings->get( 'sync_interval', 12 ) * HOUR_IN_SECONDS
			: HOUR_IN_SECONDS;
		// Retry failures sooner, but not on every run.
		if ( ! empty( $state['error'] ) ) {
			$interval = min( $interval, HOUR_IN_SECONDS );
		}
		return ( time() - (int) ( $state['checked_at'] ?? 0 ) ) >= $interval;
	}

	/**
	 * Drops every cached merged changelog (cheap: bumps a version number).
	 *
	 * @return void
	 */
	public function flush() {
		update_option( self::CACHE_VERSION, $this->cache_version() + 1, true );
		$this->memo = array();
	}

	/**
	 * Removes a product's synced data.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function forget( $product_id ) {
		delete_post_meta( $product_id, self::META_SNAPSHOT );
		delete_post_meta( $product_id, self::META_STATE );
		$this->flush();
	}

	/**
	 * Current cache version.
	 *
	 * @return int
	 */
	private function cache_version() {
		return (int) get_option( self::CACHE_VERSION, 1 );
	}

	/**
	 * Records a failed sync, keeping the last good snapshot.
	 *
	 * @param Product  $product Product.
	 * @param WP_Error $error   Error.
	 * @return WP_Error
	 */
	private function record_error( Product $product, WP_Error $error ) {
		$state               = $this->state( $product );
		$state['error']      = $error->get_error_message();
		$state['error_at']   = time();
		$state['checked_at'] = time();
		$state['source']     = $product->source;
		update_post_meta( $product->id, self::META_STATE, $state );

		/**
		 * Fires when a product's sync fails.
		 *
		 * @param WP_Error $error   Error.
		 * @param Product  $product Product.
		 */
		do_action( 'releaso_sync_failed', $error, $product );

		return $error;
	}

	/**
	 * Takes the sync lock of a product (atomic: add_option fails when it exists).
	 *
	 * @param int $id Product ID.
	 * @return bool
	 */
	private function lock( $id ) {
		$name = 'releaso_lock_' . $id;
		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}
		// A lock left behind by a crashed request expires.
		if ( time() - (int) get_option( $name, 0 ) > self::LOCK_TTL ) {
			update_option( $name, time(), false );
			return true;
		}
		return false;
	}

	/**
	 * Releases the sync lock.
	 *
	 * @param int $id Product ID.
	 * @return void
	 */
	private function unlock( $id ) {
		delete_option( 'releaso_lock_' . $id );
	}
}
