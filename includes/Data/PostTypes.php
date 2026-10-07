<?php
/**
 * The two post types: products (whose changelog is shown) and releases written in the admin.
 *
 * @package Releaso
 */

namespace Releaso\Data;

/**
 * Post type registration.
 */
class PostTypes {

	const PRODUCT = 'releaso_product';
	const RELEASE = 'releaso_release';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Registers the post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type(
			self::PRODUCT,
			/**
			 * Filters the product post type arguments.
			 *
			 * @param array $args Arguments.
			 */
			apply_filters(
				'releaso_product_post_type_args',
				array(
					'labels'          => array(
						'name'               => __( 'Products', 'releaso' ),
						'singular_name'      => __( 'Product', 'releaso' ),
						'menu_name'          => __( 'Releaso', 'releaso' ),
						'all_items'          => __( 'Products', 'releaso' ),
						'add_new'            => __( 'Add product', 'releaso' ),
						'add_new_item'       => __( 'Add a product', 'releaso' ),
						'edit_item'          => __( 'Edit product', 'releaso' ),
						'new_item'           => __( 'New product', 'releaso' ),
						'search_items'       => __( 'Search products', 'releaso' ),
						'not_found'          => __( 'No products yet. Add the plugins whose changelog you want to show.', 'releaso' ),
						'not_found_in_trash' => __( 'No products in the trash.', 'releaso' ),
					),
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => true,
					'show_in_rest'    => false,
					'menu_icon'       => 'dashicons-megaphone',
					'menu_position'   => 26,
					'supports'        => array( 'title', 'page-attributes' ),
					'hierarchical'    => false,
					'capability_type' => 'post',
					'map_meta_cap'    => true,
					'rewrite'         => false,
					'query_var'       => false,
				)
			)
		);

		register_post_type(
			self::RELEASE,
			/**
			 * Filters the release post type arguments.
			 *
			 * @param array $args Arguments.
			 */
			apply_filters(
				'releaso_release_post_type_args',
				array(
					'labels'          => array(
						'name'               => __( 'Releases', 'releaso' ),
						'singular_name'      => __( 'Release', 'releaso' ),
						'all_items'          => __( 'Releases', 'releaso' ),
						'add_new'            => __( 'Add release', 'releaso' ),
						'add_new_item'       => __( 'Add a release', 'releaso' ),
						'edit_item'          => __( 'Edit release', 'releaso' ),
						'new_item'           => __( 'New release', 'releaso' ),
						'search_items'       => __( 'Search releases', 'releaso' ),
						'not_found'          => __( 'No releases written here yet. Releases from each product\'s source still show on the site.', 'releaso' ),
						'not_found_in_trash' => __( 'No releases in the trash.', 'releaso' ),
					),
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => 'edit.php?post_type=' . self::PRODUCT,
					'show_in_rest'    => false,
					'supports'        => array( 'title' ),
					'capability_type' => 'post',
					'map_meta_cap'    => true,
					'rewrite'         => false,
					'query_var'       => false,
				)
			)
		);
	}
}
