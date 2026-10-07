<?php
/**
 * [releaso] shortcode.
 *
 *   [releaso]                                       every product
 *   [releaso products="slider-blocks,slider-pro"]   chosen products, as tabs
 *   [releaso layout="compact" per_page="10" limit="20" types="new,fixed" filters="no" search="no" header="no"]
 *
 * @package Releaso
 */

namespace Releaso\Frontend;

/**
 * Shortcode.
 */
class Shortcode {

	const TAG = 'releaso';

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Renderer $renderer Renderer.
	 */
	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'add' ) );
	}

	/**
	 * Adds the shortcode.
	 *
	 * @return void
	 */
	public function add() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		// Element colours: accent_color, version_color, date_color … (see Renderer::color_elements()).
		$color_atts = array();
		foreach ( array_keys( Renderer::color_elements() ) as $element ) {
			$color_atts[ $element . '_color' ] = '';
		}

		$atts = shortcode_atts(
			$color_atts + array(
				'products'    => '',
				'product'     => '', // alias.
				'layout'      => '',
				'per_page'    => '',
				'show'        => '', // alias of per_page.
				'limit'       => 0,
				'types'       => '',
				'filters'     => 'yes',
				'search'      => 'yes',
				'header'      => 'yes',
				'expanded'    => 'no',
				'class'       => '',
				'type_colors' => '', // "new:#0ea5e9, fixed:#e11d48".
			),
			$atts,
			self::TAG
		);

		$colors = array();
		foreach ( array_keys( Renderer::color_elements() ) as $element ) {
			$colors[ $element ] = $atts[ $element . '_color' ];
		}
		$type_colors = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $atts['type_colors'] ) ) ) as $pair ) {
			$parts = array_map( 'trim', explode( ':', $pair, 2 ) );
			if ( 2 === count( $parts ) ) {
				$type_colors[ $parts[0] ] = $parts[1];
			}
		}

		$html = $this->renderer->render(
			array(
				'products'    => '' !== $atts['products'] ? $atts['products'] : $atts['product'],
				'layout'      => $atts['layout'],
				'per_page'    => '' !== $atts['per_page'] ? $atts['per_page'] : $atts['show'],
				'limit'       => $atts['limit'],
				'types'       => $atts['types'],
				'filters'     => $atts['filters'],
				'search'      => $atts['search'],
				'header'      => $atts['header'],
				'expanded'    => $atts['expanded'],
				'className'   => $atts['class'],
				'colors'      => $colors,
				'type_colors' => $type_colors,
			)
		);

		return '' === $html ? '' : '<div class="wp-shortcode-releaso">' . $html . '</div>';
	}
}
