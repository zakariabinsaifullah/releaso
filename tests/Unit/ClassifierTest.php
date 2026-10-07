<?php
/**
 * Classifier tests.
 *
 * @package Releaso
 */

namespace Releaso\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Releaso\Parser\Classifier;

/**
 * @covers \Releaso\Parser\Classifier
 */
class ClassifierTest extends TestCase {

	/**
	 * Lines, expected type, tag and text.
	 *
	 * @return array
	 */
	public function lines() {
		return array(
			'prefix'                => array( 'Added: Dark mode', 'new', '', 'Dark mode' ),
			'prefix with tag'       => array( 'Fixed (Pro): Panorama height', 'fixed', 'Pro', 'Panorama height' ),
			'bracketed type'        => array( '[Fixed] Crash on save', 'fixed', '', 'Crash on save' ),
			'dash separator'        => array( 'FIX - Crash on save', 'fixed', '', 'Crash on save' ),
			'bold prefix'           => array( '**Improved:** Faster loading', 'improved', '', 'Faster loading' ),
			'conventional commit'   => array( 'feat(blocks): carousel', 'new', 'blocks', 'carousel' ),
			'leading tag'           => array( '[Pro] Added: Video slides', 'new', 'Pro', 'Video slides' ),
			'scope tag'             => array( '**blocks:** new carousel', 'new', 'blocks', 'new carousel' ),
			'wording fix'           => array( 'The arrows bug is fixed', 'fixed', '', 'The arrows bug is fixed' ),
			'wording security'      => array( 'Escape output to prevent XSS', 'security', '', 'Escape output to prevent XSS' ),
			'wording new'           => array( 'Initial release', 'new', '', 'Initial release' ),
			'fallback improved'     => array( 'Faster editor loading', 'improved', '', 'Faster editor loading' ),
			'non-type word kept'    => array( 'WooCommerce: compatibility with 9.0', 'improved', '', 'WooCommerce: compatibility with 9.0' ),
			'hyphenated word kept'  => array( 'Fix-ups for the toolbar', 'fixed', '', 'Fix-ups for the toolbar' ),
		);
	}

	/**
	 * @dataProvider lines
	 *
	 * @param string $line Line.
	 * @param string $type Expected type.
	 * @param string $tag  Expected tag.
	 * @param string $text Expected text.
	 */
	public function test_classify( $line, $type, $tag, $text ) {
		$change = ( new Classifier() )->classify( $line );
		$this->assertSame( array( $type, $tag, $text ), array( $change->type, $change->tag, $change->text ) );
	}

	public function test_section_type_is_the_fallback() {
		$this->assertSame( 'removed', ( new Classifier() )->classify( 'Legacy shortcode', 'removed' )->type );
	}

	public function test_explicit_prefix_beats_section() {
		$this->assertSame( 'fixed', ( new Classifier() )->classify( 'Fixed: x', 'new' )->type );
	}

	public function test_type_for_headings() {
		$classifier = new Classifier();
		$this->assertSame( 'fixed', $classifier->type_for( 'Bug Fixes' ) );
		$this->assertSame( 'new', $classifier->type_for( '🚀 New features' ) );
		$this->assertSame( 'improved', $classifier->type_for( 'Changed' ) );
		$this->assertSame( '', $classifier->type_for( "What's Changed" ) );
		$this->assertSame( '', $classifier->type_for( 'Contributors' ) );
	}

	public function test_custom_types() {
		$classifier = new Classifier( array( 'breaking' => array( 'breaking' ), 'other' => array( 'misc' ) ), 'other' );
		$this->assertSame( 'breaking', $classifier->classify( 'Breaking: drop PHP 7' )->type );
		$this->assertSame( 'other', $classifier->classify( 'Something' )->type );
	}
}
