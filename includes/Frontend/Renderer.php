<?php
/**
 * Renders a changelog for the block, the shortcode and the REST preview.
 *
 * All products and releases are in the HTML, so the changelog reads without JavaScript and
 * search engines see every release; the script adds tabs, filters, search and "show older".
 *
 * @package Releaso
 */

namespace Releaso\Frontend;

use Releaso\Data\ChangelogService;
use Releaso\Data\ProductRepository;
use Releaso\Plugin;
use Releaso\Support\ChangeTypes;
use Releaso\Support\Settings;
use Releaso\Support\Template;

/**
 * Changelog renderer.
 */
class Renderer {

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
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products  Products.
	 * @param ChangelogService  $changelog Changelog service.
	 * @param Settings          $settings  Settings.
	 */
	public function __construct( ProductRepository $products, ChangelogService $changelog, Settings $settings ) {
		$this->products  = $products;
		$this->changelog = $changelog;
		$this->settings  = $settings;
	}

	/**
	 * Available layouts.
	 *
	 * @return array<string,string> key => label.
	 */
	public static function layouts() {
		/**
		 * Filters the layouts. Add one with a template at templates/layouts/{key}.php
		 * (or yourtheme/releaso/layouts/{key}.php).
		 *
		 * @param array $layouts key => label.
		 */
		return apply_filters(
			'releaso_layouts',
			array(
				'timeline' => __( 'Timeline', 'releaso' ),
				'compact'  => __( 'Compact list', 'releaso' ),
			)
		);
	}

	/**
	 * Fills in and cleans display arguments.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	public function normalize( array $args ) {
		$types        = array_filter( array_map( 'sanitize_key', self::to_list( $args['types'] ?? array() ) ), array( ChangeTypes::class, 'exists' ) );
		$out          = array(
			'products'  => array_map( 'sanitize_text_field', self::to_list( $args['products'] ?? array() ) ),
			'layout'    => sanitize_key( $args['layout'] ?? '' ),
			'per_page'  => absint( $args['per_page'] ?? 0 ),
			'limit'     => absint( $args['limit'] ?? 0 ),
			'types'     => array_values( $types ),
			'filters'   => self::to_bool( $args['filters'] ?? true ),
			'search'    => self::to_bool( $args['search'] ?? true ),
			'header'    => self::to_bool( $args['header'] ?? true ),
			'expanded'  => self::to_bool( $args['expanded'] ?? false ),
			'className' => sanitize_html_class( $args['className'] ?? '' ),
			'colors'    => self::sanitize_colors( (array) ( $args['colors'] ?? array() ) ),
		);
		$out['style'] = self::color_style( $out['colors'], (array) ( $args['type_colors'] ?? array() ) );

		if ( ! isset( self::layouts()[ $out['layout'] ] ) ) {
			$out['layout'] = (string) $this->settings->get( 'default_layout', 'timeline' );
		}
		if ( ! $out['per_page'] ) {
			$out['per_page'] = (int) $this->settings->get( 'default_per_page', 6 );
		}

		/**
		 * Filters the display arguments of a changelog.
		 *
		 * @param array $out  Normalized arguments.
		 * @param array $args Raw arguments.
		 */
		return apply_filters( 'releaso_render_args', $out, $args );
	}

	/**
	 * Renders a changelog.
	 *
	 * @param array $args See normalize().
	 * @return string HTML.
	 */
	public function render( array $args ) {
		$args     = $this->normalize( $args );
		$products = $this->products->pick( $args['products'] );

		if ( ! $products ) {
			return current_user_can( Plugin::capability() )
				? '<p class="releaso-notice">' . sprintf(
					/* translators: %s: link to the products screen */
					esc_html__( 'No products to show yet. Add one under %s.', 'releaso' ),
					'<a href="' . esc_url( admin_url( 'post-new.php?post_type=releaso_product' ) ) . '">' . esc_html__( 'Releaso → Add product', 'releaso' ) . '</a>'
				) . '</p>'
				: '';
		}

		$panels = array();
		foreach ( $products as $product ) {
			$releases = $this->changelog->releases( $product )->only_types( $args['types'] )->slice( $args['limit'] );
			$panels[] = array(
				'product'  => $product,
				'releases' => $releases,
			);
		}

		Assets::enqueue();

		$view = new View( $this->settings );
		$html = Template::render(
			'changelog',
			array(
				'panels' => $panels,
				'args'   => $args,
				'uid'    => wp_unique_id( 'releaso-' ),
				'types'  => $args['types'] ? array_intersect_key( ChangeTypes::all(), array_flip( $args['types'] ) ) : ChangeTypes::all(),
				'view'   => $view,
			)
		);

		/**
		 * Filters the changelog HTML.
		 *
		 * @param string $html   HTML.
		 * @param array  $args   Arguments.
		 * @param array  $panels Products and their releases.
		 */
		return apply_filters( 'releaso_render', $html, $args, $panels );
	}

	/**
	 * Element colours a block (or shortcode) can set, as option key => CSS custom property.
	 * The stylesheet reads each property with the default as fallback.
	 *
	 * @return array<string,string>
	 */
	public static function color_elements() {
		return array(
			'accent'  => '--releaso-accent',
			'product' => '--releaso-product-color',
			'version' => '--releaso-version-color',
			'date'    => '--releaso-date-color',
			'title'   => '--releaso-title-color',
			'text'    => '--releaso-text-color',
			'latest'  => '--releaso-latest-bg',
			'tag'     => '--releaso-tag-bg',
			'card'    => '--releaso-card',
			'border'  => '--releaso-line',
		);
	}

	/**
	 * Element colours with unknown keys and invalid values dropped.
	 *
	 * @param array $colors Element key => colour.
	 * @return array<string,string>
	 */
	public static function sanitize_colors( array $colors ) {
		$out = array();
		foreach ( array_keys( self::color_elements() ) as $key ) {
			$color = self::sanitize_color( $colors[ $key ] ?? '' );
			if ( '' !== $color ) {
				$out[ $key ] = $color;
			}
		}
		return $out;
	}

	/**
	 * Inline custom properties for element and change type colours.
	 *
	 * @param array $colors      Element key => colour.
	 * @param array $type_colors Change type key => colour.
	 * @return string
	 */
	public static function color_style( array $colors, array $type_colors ) {
		$props = array();
		foreach ( self::color_elements() as $key => $property ) {
			$color = self::sanitize_color( $colors[ $key ] ?? '' );
			if ( '' !== $color ) {
				$props[] = $property . ':' . $color;
			}
		}
		foreach ( $type_colors as $type => $color ) {
			$type  = sanitize_key( $type );
			$color = self::sanitize_color( $color );
			if ( '' !== $color && ChangeTypes::exists( $type ) ) {
				$props[] = '--releaso-' . $type . ':' . $color;
			}
		}
		return implode( ';', $props );
	}

	/**
	 * A colour the editor's picker (or a shortcode) can produce: hex, rgb(a), hsl(a), a theme
	 * preset variable or a named colour.
	 * Anything else (and so anything that could break out of the style attribute) is dropped.
	 *
	 * @param mixed $color Colour.
	 * @return string
	 */
	public static function sanitize_color( $color ) {
		$color = strtolower( trim( (string) $color ) );
		if (
			preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color )
			|| preg_match( '/^(?:rgb|hsl)a?\([0-9.,%\s\/deg]+\)$/', $color )
			|| preg_match( '/^var\(--wp--preset--color--[a-z0-9-]+\)$/', $color )
			|| preg_match( '/^[a-z]{3,20}$/', $color ) // Named colours: "navy", "transparent".
		) {
			return $color;
		}
		return '';
	}

	/**
	 * "a, b" or [ 'a', 'b' ] to a list.
	 *
	 * @param mixed $value Value.
	 * @return string[]
	 */
	private static function to_list( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) $value ) ), 'strlen' ) );
	}

	/**
	 * "yes", "no", "false", 0, true … to a bool.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function to_bool( $value ) {
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), array( 'no', 'false', '0', 'off', '' ), true );
		}
		return (bool) $value;
	}
}
