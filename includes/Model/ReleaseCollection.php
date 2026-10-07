<?php
/**
 * Ordered list of releases with merge and sort helpers.
 *
 * @package Releaso
 */

namespace Releaso\Model;

/**
 * Collection of releases, newest version first once sorted.
 */
final class ReleaseCollection implements \Countable, \IteratorAggregate {

	/**
	 * Releases.
	 *
	 * @var Release[]
	 */
	private $releases = array();

	/**
	 * Constructor.
	 *
	 * @param Release[] $releases Releases.
	 */
	public function __construct( array $releases = array() ) {
		foreach ( $releases as $release ) {
			$this->add( $release );
		}
	}

	/**
	 * Adds a release (empty ones are dropped).
	 *
	 * @param Release $release Release.
	 * @return void
	 */
	public function add( Release $release ) {
		if ( '' !== $release->version && ! $release->is_empty() ) {
			$this->releases[] = $release;
		}
	}

	/**
	 * Releases as a list.
	 *
	 * @return Release[]
	 */
	public function all() {
		return $this->releases;
	}

	/**
	 * The newest release, if any.
	 *
	 * @return Release|null
	 */
	public function latest() {
		return $this->releases[0] ?? null;
	}

	/**
	 * A release by version.
	 *
	 * @param string $version Version.
	 * @return Release|null
	 */
	public function find( $version ) {
		$version = Release::normalize_version( $version );
		foreach ( $this->releases as $release ) {
			if ( $release->version === $version ) {
				return $release;
			}
		}
		return null;
	}

	/**
	 * Total number of changes.
	 *
	 * @return int
	 */
	public function change_count() {
		$n = 0;
		foreach ( $this->releases as $release ) {
			$n += count( $release->changes );
		}
		return $n;
	}

	/**
	 * Joins another collection in; its releases replace ours for the same version.
	 *
	 * @param ReleaseCollection $overrides Releases that win.
	 * @return self New, sorted collection.
	 */
	public function merge( ReleaseCollection $overrides ) {
		$by_version = array();
		foreach ( $this->releases as $release ) {
			$by_version[ $release->version ] = $release;
		}
		foreach ( $overrides->all() as $release ) {
			$by_version[ $release->version ] = $release;
		}
		return ( new self( array_values( $by_version ) ) )->sorted();
	}

	/**
	 * Newest version first; same version falls back to the newer date.
	 *
	 * @return self New collection.
	 */
	public function sorted() {
		$releases = $this->releases;
		usort(
			$releases,
			static function ( Release $a, Release $b ) {
				$cmp = version_compare( $b->version, $a->version );
				return 0 !== $cmp ? $cmp : strcmp( $b->date, $a->date );
			}
		);
		return new self( $releases );
	}

	/**
	 * The first $limit releases.
	 *
	 * @param int $limit Max releases, 0 for all.
	 * @return self
	 */
	public function slice( $limit ) {
		return $limit > 0 ? new self( array_slice( $this->releases, 0, (int) $limit ) ) : $this;
	}

	/**
	 * Only changes of the given types; releases left empty are dropped.
	 *
	 * @param string[] $types Type keys; empty keeps everything.
	 * @return self
	 */
	public function only_types( array $types ) {
		if ( ! $types ) {
			return $this;
		}
		$out = new self();
		foreach ( $this->releases as $release ) {
			$copy          = clone $release;
			$copy->changes = array_values(
				array_filter(
					$release->changes,
					static function ( Change $change ) use ( $types ) {
						return in_array( $change->type, $types, true );
					}
				)
			);
			if ( $copy->changes ) {
				$out->add( $copy );
			}
		}
		return $out;
	}

	/**
	 * To arrays.
	 *
	 * @return array[]
	 */
	public function to_array() {
		return array_map(
			static function ( Release $release ) {
				return $release->to_array();
			},
			$this->releases
		);
	}

	/**
	 * From arrays.
	 *
	 * @param array $rows Release arrays.
	 * @return self
	 */
	public static function from_array( array $rows ) {
		$out = new self();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out->add( Release::from_array( $row ) );
			}
		}
		return $out;
	}

	/**
	 * Count.
	 *
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	public function count() {
		return count( $this->releases );
	}

	/**
	 * Iterator.
	 *
	 * @return \ArrayIterator
	 */
	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator( $this->releases );
	}
}
