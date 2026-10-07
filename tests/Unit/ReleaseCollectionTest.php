<?php
/**
 * Release collection tests: merge, sort, filters, round trip.
 *
 * @package Releaso
 */

namespace Releaso\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Releaso\Model\Change;
use Releaso\Model\Release;
use Releaso\Model\ReleaseCollection;

/**
 * @covers \Releaso\Model\ReleaseCollection
 * @covers \Releaso\Model\Release
 */
class ReleaseCollectionTest extends TestCase {

	/**
	 * A release with one change.
	 *
	 * @param string $version Version.
	 * @param string $type    Type.
	 * @param string $origin  Origin.
	 * @return Release
	 */
	private function release( $version, $type = 'new', $origin = Release::ORIGIN_SOURCE ) {
		$release         = new Release( $version, '2026-01-01', array( new Change( $type, "{$version} {$type}" ) ) );
		$release->origin = $origin;
		return $release;
	}

	public function test_sorts_by_semver() {
		$sorted = ( new ReleaseCollection( array( $this->release( '1.9.0' ), $this->release( '1.10.0' ), $this->release( '2.0.0-beta.1' ), $this->release( '2.0.0' ) ) ) )->sorted();
		$this->assertSame( array( '2.0.0', '2.0.0-beta.1', '1.10.0', '1.9.0' ), array_map( fn( $r ) => $r->version, $sorted->all() ) );
	}

	public function test_manual_release_replaces_source_release() {
		$source = new ReleaseCollection( array( $this->release( '1.0.0' ), $this->release( '1.1.0' ) ) );
		$manual = new ReleaseCollection( array( $this->release( '1.1.0', 'fixed', Release::ORIGIN_MANUAL ), $this->release( '1.2.0', 'new', Release::ORIGIN_MANUAL ) ) );
		$merged = $source->merge( $manual );

		$this->assertSame( array( '1.2.0', '1.1.0', '1.0.0' ), array_map( fn( $r ) => $r->version, $merged->all() ) );
		$this->assertSame( Release::ORIGIN_MANUAL, $merged->find( '1.1.0' )->origin );
		$this->assertSame( 'fixed', $merged->find( 'v1.1.0' )->changes[0]->type );
	}

	public function test_only_types_drops_empty_releases() {
		$all  = new ReleaseCollection( array( $this->release( '1.0.0', 'new' ), $this->release( '1.1.0', 'fixed' ) ) );
		$only = $all->only_types( array( 'fixed' ) );
		$this->assertCount( 1, $only );
		$this->assertCount( 2, $all, 'original untouched' );
	}

	public function test_empty_releases_are_dropped() {
		$this->assertCount( 0, new ReleaseCollection( array( new Release( '1.0.0' ), new Release( '' ) ) ) );
	}

	public function test_round_trip() {
		$release        = $this->release( '1.0.0' );
		$release->title = 'T';
		$release->notes = 'N';
		$release->url   = 'https://example.com';
		$again          = ReleaseCollection::from_array( ( new ReleaseCollection( array( $release ) ) )->to_array() );
		$this->assertEquals( $release, $again->latest() );
	}

	public function test_normalize_version() {
		$this->assertSame( '1.2.0', Release::normalize_version( ' v1.2.0 ' ) );
		$this->assertSame( '1.2.0', Release::normalize_version( 'Version 1.2.0' ) );
	}
}
