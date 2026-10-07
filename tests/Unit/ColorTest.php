<?php
/**
 * Block colour validation tests.
 *
 * @package Releaso
 */

namespace Releaso\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Releaso\Frontend\Renderer;

/**
 * @covers \Releaso\Frontend\Renderer::sanitize_color
 */
class ColorTest extends TestCase {

	/**
	 * Colours the editor's picker produces.
	 *
	 * @return array
	 */
	public function valid() {
		return array(
			'hex'        => array( '#E11D48', '#e11d48' ),
			'short hex'  => array( '#fff', '#fff' ),
			'hex alpha'  => array( '#e11d4880', '#e11d4880' ),
			'rgba'       => array( 'rgba(10, 20, 30, 0.5)', 'rgba(10, 20, 30, 0.5)' ),
			'hsl'        => array( 'hsl(210deg 40% 50%)', 'hsl(210deg 40% 50%)' ),
			'preset var' => array( 'var(--wp--preset--color--accent-3)', 'var(--wp--preset--color--accent-3)' ),
			'named'      => array( 'Navy', 'navy' ),
		);
	}

	/**
	 * Values that must never reach the style attribute.
	 *
	 * @return array
	 */
	public function invalid() {
		return array(
			'breakout'      => array( 'red;}</style><script>' ),
			'declaration'   => array( '#fff;background:url(x)' ),
			'url'           => array( 'url(https://example.com/x.png)' ),
			'expression'    => array( 'expression(alert(1))' ),
			'other var'     => array( 'var(--anything)' ),
			'bad hex'       => array( '#ggg' ),
			'empty'         => array( '' ),
		);
	}

	/**
	 * @dataProvider valid
	 *
	 * @param string $input    Colour.
	 * @param string $expected Cleaned colour.
	 */
	public function test_keeps_picker_colours( $input, $expected ) {
		$this->assertSame( $expected, Renderer::sanitize_color( $input ) );
	}

	/**
	 * @dataProvider invalid
	 *
	 * @param string $input Value.
	 */
	public function test_drops_anything_else( $input ) {
		$this->assertSame( '', Renderer::sanitize_color( $input ) );
	}
}
