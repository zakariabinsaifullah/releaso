<?php
/**
 * The Releaso Changelog block (releaso/changelog), rendered on the server.
 * Editor code lives in src/blocks/changelog and is built to build/blocks/changelog.
 *
 * @package Releaso
 */

namespace Releaso\Frontend;

use Releaso\Support\Settings;

/**
 * Block.
 */
class Block {

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Renderer $renderer Renderer.
	 * @param Settings $settings Settings.
	 */
	public function __construct( Renderer $renderer, Settings $settings ) {
		$this->renderer = $renderer;
		$this->settings = $settings;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ), 20 );
	}

	/**
	 * Registers the block from its built block.json.
	 *
	 * @return void
	 */
	public function register_block() {
		$dir = RELEASO_DIR . '/build/blocks/changelog';
		if ( ! is_readable( $dir . '/block.json' ) ) {
			return; // Not built yet: run `npm run build`.
		}

		$type = register_block_type(
			$dir,
			array( 'render_callback' => array( $this, 'render' ) )
		);

		if ( $type && ! empty( $type->editor_script_handles ) ) {
			wp_add_inline_script(
				$type->editor_script_handles[0],
				'window.releasoBlock = ' . wp_json_encode(
					array(
						'layouts'    => Renderer::layouts(),
						'types'      => \Releaso\Support\ChangeTypes::options(),
						'typeColors' => wp_list_pluck( \Releaso\Support\ChangeTypes::all(), 'color' ),
						'perPage'    => (int) $this->settings->get( 'default_per_page', 6 ),
						'addProduct' => admin_url( 'post-new.php?post_type=releaso_product' ),
						'addRelease' => admin_url( 'post-new.php?post_type=releaso_release' ),
					)
				) . ';',
				'before'
			);
			wp_set_script_translations( $type->editor_script_handles[0], 'releaso', RELEASO_DIR . '/languages' );
		}
	}

	/**
	 * Server render.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$html = $this->renderer->render(
			array(
				'products'    => $attributes['products'] ?? array(),
				'layout'      => $attributes['layout'] ?? '',
				'per_page'    => $attributes['perPage'] ?? 0,
				'limit'       => $attributes['limit'] ?? 0,
				'types'       => $attributes['types'] ?? array(),
				'filters'     => $attributes['showFilters'] ?? true,
				'search'      => $attributes['showSearch'] ?? true,
				'header'      => $attributes['showHeader'] ?? true,
				'expanded'    => $attributes['expanded'] ?? false,
				'colors'      => $attributes['colors'] ?? array(),
				'type_colors' => $attributes['typeColors'] ?? array(),
			)
		);
		return '' === $html ? '' : '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}
}
