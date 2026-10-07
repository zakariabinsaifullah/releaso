<?php
/**
 * Change type customisation tests.
 *
 * @package Releaso
 */

namespace Releaso\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Releaso\Parser\Classifier;
use Releaso\Support\ChangeTypes;

/**
 * @covers \Releaso\Support\ChangeTypes::with_saved
 */
class ChangeTypesTest extends TestCase {

	/**
	 * Two built-in types, as ChangeTypes::builtin() returns them.
	 *
	 * @return array
	 */
	private function builtin() {
		return array(
			'new'   => array(
				'label'   => 'New',
				'color'   => '#13a57c',
				'aliases' => array( 'new', 'added' ),
			),
			'fixed' => array(
				'label'   => 'Fixed',
				'color'   => '#e2742a',
				'aliases' => array( 'fixed', 'fix' ),
			),
		);
	}

	public function test_renames_and_recolours_builtin_types_but_keeps_their_words() {
		$types = ChangeTypes::with_saved(
			$this->builtin(),
			array(
				'fixed' => array(
					'label'   => 'Bug fixes',
					'color'   => '#b45309',
					'aliases' => array( 'ignored' ),
				),
			)
		);

		$this->assertSame( 'Bug fixes', $types['fixed']['label'] );
		$this->assertSame( '#b45309', $types['fixed']['color'] );
		$this->assertSame( array( 'fixed', 'fix' ), $types['fixed']['aliases'] );
		$this->assertSame( 'New', $types['new']['label'] );
	}

	public function test_adds_custom_types_after_the_builtin_ones() {
		$types = ChangeTypes::with_saved(
			$this->builtin(),
			array(
				'breaking' => array(
					'label'   => 'Breaking',
					'color'   => '#6d4c41',
					'aliases' => array( 'breaking', 'bc' ),
					'custom'  => true,
				),
			)
		);

		$this->assertSame( array( 'new', 'fixed', 'breaking' ), array_keys( $types ) );
		$this->assertSame( array( 'breaking', 'bc' ), $types['breaking']['aliases'] );
	}

	public function test_ignores_unknown_keys_that_are_not_custom_types() {
		$types = ChangeTypes::with_saved( $this->builtin(), array( 'ghost' => array( 'label' => 'Ghost' ) ) );

		$this->assertArrayNotHasKey( 'ghost', $types );
	}

	public function test_classifier_recognises_custom_type_keywords() {
		$types      = ChangeTypes::with_saved(
			$this->builtin(),
			array(
				'breaking' => array(
					'label'   => 'Breaking',
					'aliases' => array( 'breaking', 'bc' ),
					'custom'  => true,
				),
			)
		);
		$classifier = new Classifier( array_map( fn( $t ) => $t['aliases'], $types ), 'new' );

		$this->assertSame( 'breaking', $classifier->classify( 'BC: renamed the filter' )->type );
		$this->assertSame( 'breaking', $classifier->type_for( '### Breaking' ) );
		$this->assertSame( 'fixed', $classifier->classify( 'Fixed: arrows' )->type );
	}
}
