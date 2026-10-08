<?php
/**
 * Release screens: the release notes editor, list columns and the product filter.
 *
 * @package Releaso
 */

namespace Releaso\Admin;

use Releaso\Data\PostTypes;
use Releaso\Data\ReleaseRepository;
use Releaso\Model\Change;
use Releaso\Model\Release;
use Releaso\Parser\ReadmeParser;
use Releaso\Plugin;
use Releaso\Support\ChangeTypes;
use Releaso\Support\Posts;
use WP_Post;
use WP_Query;

/**
 * Release admin screen.
 */
class ReleaseScreen {

	const NONCE = 'releaso_release';

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . PostTypes::RELEASE, array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . PostTypes::RELEASE, array( $this, 'save' ), 5 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . PostTypes::RELEASE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostTypes::RELEASE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'product_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string  $text Placeholder.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		return PostTypes::RELEASE === $post->post_type ? __( 'Version, e.g. 1.6.0', 'releaso' ) : $text;
	}

	/**
	 * Meta boxes.
	 *
	 * @return void
	 */
	public function meta_boxes() {
		add_meta_box( 'releaso-release', __( 'Release notes', 'releaso' ), array( $this, 'box' ), PostTypes::RELEASE, 'normal', 'high' );
	}

	/**
	 * Products as id => name, any status but trash.
	 *
	 * @return array<int,string>
	 */
	private function product_options() {
		$posts = Posts::all(
			array(
				'post_type'   => PostTypes::PRODUCT,
				'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		return wp_list_pluck( $posts, 'post_title', 'ID' );
	}

	/**
	 * The release editor.
	 *
	 * @param WP_Post $post Release.
	 * @return void
	 */
	public function box( WP_Post $post ) {
		$products = $this->product_options();
		$current  = (int) get_post_meta( $post->ID, ReleaseRepository::META_PRODUCT, true );
		if ( ! $current && isset( $_GET['product'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current = absint( $_GET['product'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$release = $this->plugin->releases->from_post( $post );
		$items   = $release->changes ? $release->changes : array( new Change( 'new', '' ) );
		$types   = ChangeTypes::options();

		wp_nonce_field( self::NONCE, 'releaso_release_nonce' );
		?>
		<div class="releaso-admin">
			<?php if ( ! $products ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Add a product first under %s.', 'releaso' ),
						'<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::PRODUCT ) ) . '">' . esc_html__( 'Releaso → Add product', 'releaso' ) . '</a>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<div class="releaso-admin__grid">
				<p class="releaso-field">
					<label class="releaso-field__label" for="releaso-product"><?php esc_html_e( 'Product', 'releaso' ); ?></label>
					<select id="releaso-product" name="releaso_product" required>
						<?php foreach ( $products as $id => $name ) : ?>
							<option value="<?php echo (int) $id; ?>" <?php selected( $current, $id ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="releaso-field">
					<label class="releaso-field__label" for="releaso-title"><?php esc_html_e( 'Headline (optional)', 'releaso' ); ?></label>
					<input type="text" id="releaso-title" name="releaso_title" class="widefat" value="<?php echo esc_attr( $release->title ); ?>" placeholder="<?php esc_attr_e( 'e.g. The Panorama release', 'releaso' ); ?>">
				</p>
			</div>
			<p class="description"><?php esc_html_e( 'The release date is the publish date (Publish box): save as a draft to prepare notes, or schedule the release for its day. A release written here replaces the source\'s notes for the same version.', 'releaso' ); ?></p>

			<p class="releaso-field">
				<label class="releaso-field__label" for="releaso-notes"><?php esc_html_e( 'Intro (optional)', 'releaso' ); ?></label>
				<textarea id="releaso-notes" name="releaso_notes" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'A sentence or two above the list of changes. **Bold**, `code` and [links](https://…) work.', 'releaso' ); ?>"><?php echo esc_textarea( $release->notes ); ?></textarea>
			</p>

			<table class="widefat releaso-admin__items">
				<thead>
					<tr>
						<th class="releaso-col-move"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'releaso' ); ?></span></th>
						<th class="releaso-col-type"><?php esc_html_e( 'Type', 'releaso' ); ?></th>
						<th class="releaso-col-tag"><?php esc_html_e( 'Tag', 'releaso' ); ?></th>
						<th><?php esc_html_e( 'Change', 'releaso' ); ?></th>
						<th class="releaso-col-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'releaso' ); ?></span></th>
					</tr>
				</thead>
				<tbody data-releaso-rows>
					<?php foreach ( $items as $i => $item ) : ?>
						<?php $this->row( $i, $item, $types ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<template data-releaso-template><?php $this->row( '__i__', new Change( 'new', '' ), $types ); ?></template>

			<p class="releaso-admin__actions">
				<button type="button" class="button" data-releaso-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a change', 'releaso' ); ?></button>
				<button type="button" class="button-link" data-releaso-paste-toggle aria-expanded="false"><?php esc_html_e( 'Paste from a changelog instead', 'releaso' ); ?></button>
			</p>

			<div class="releaso-admin__paste" data-releaso-paste hidden>
				<label for="releaso-paste"><?php esc_html_e( 'Paste changelog lines, one per line, such as "* Added: …" or "- Fixed (Pro): …". They replace the rows above when you save.', 'releaso' ); ?></label>
				<textarea id="releaso-paste" name="releaso_paste" rows="8" class="large-text code" placeholder="* Added: New Hover Slider designs&#10;* Fixed (Pro): Panorama height on mobile"></textarea>
			</div>

			<p class="releaso-field">
				<label class="releaso-field__label" for="releaso-url"><?php esc_html_e( 'Link to the full release (optional)', 'releaso' ); ?></label>
				<input type="url" id="releaso-url" name="releaso_url" class="regular-text" value="<?php echo esc_attr( $release->url ); ?>" placeholder="https://">
			</p>
		</div>
		<?php
	}

	/**
	 * One change row.
	 *
	 * @param int|string           $i     Row index.
	 * @param Change               $item  Change.
	 * @param array<string,string> $types Types.
	 * @return void
	 */
	private function row( $i, Change $item, array $types ) {
		$name = 'releaso_changes[' . $i . ']';
		?>
		<tr class="releaso-row" data-type="<?php echo esc_attr( $item->type ); ?>">
			<td class="releaso-col-move">
				<button type="button" class="button-link" data-releaso-up aria-label="<?php esc_attr_e( 'Move up', 'releaso' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="button-link" data-releaso-down aria-label="<?php esc_attr_e( 'Move down', 'releaso' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
			</td>
			<td class="releaso-col-type">
				<select name="<?php echo esc_attr( $name ); ?>[type]" aria-label="<?php esc_attr_e( 'Type', 'releaso' ); ?>">
					<?php foreach ( $types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $item->type, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="releaso-col-tag">
				<input type="text" name="<?php echo esc_attr( $name ); ?>[tag]" value="<?php echo esc_attr( $item->tag ); ?>" placeholder="Pro" aria-label="<?php esc_attr_e( 'Tag', 'releaso' ); ?>">
			</td>
			<td>
				<input type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[text]" value="<?php echo esc_attr( $item->text ); ?>" placeholder="<?php esc_attr_e( 'What changed, in one line. `code`, **bold** and [links](https://…) work.', 'releaso' ); ?>" aria-label="<?php esc_attr_e( 'Change', 'releaso' ); ?>">
			</td>
			<td class="releaso-col-remove">
				<button type="button" class="button-link button-link-delete" data-releaso-remove aria-label="<?php esc_attr_e( 'Remove this change', 'releaso' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the release notes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['releaso_release_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['releaso_release_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$release        = new Release( get_post_field( 'post_title', $post_id ) );
		$release->title = sanitize_text_field( wp_unslash( $_POST['releaso_title'] ?? '' ) );
		$release->notes = sanitize_textarea_field( wp_unslash( $_POST['releaso_notes'] ?? '' ) );
		$release->url   = esc_url_raw( wp_unslash( $_POST['releaso_url'] ?? '' ), array( 'http', 'https' ) );

		$paste = sanitize_textarea_field( wp_unslash( $_POST['releaso_paste'] ?? '' ) );
		if ( '' !== trim( $paste ) ) {
			// Pasted lines go through the readme rules (prefixes, sections, wording).
			( new ReadmeParser( ChangeTypes::classifier() ) )->fill( $release, $paste );
		} else {
			$rows = isset( $_POST['releaso_changes'] ) && is_array( $_POST['releaso_changes'] ) ? wp_unslash( $_POST['releaso_changes'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
			foreach ( $rows as $row ) {
				$type = sanitize_key( $row['type'] ?? '' );
				$release->add(
					new Change(
						ChangeTypes::exists( $type ) ? $type : 'improved',
						sanitize_text_field( $row['text'] ?? '' ),
						sanitize_text_field( $row['tag'] ?? '' )
					)
				);
			}
		}

		$product_id = absint( $_POST['releaso_product'] ?? 0 );
		$this->plugin->releases->write_meta( $post_id, $product_id, $release );
	}

	/**
	 * List columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'              => $columns['cb'],
			'title'           => __( 'Version', 'releaso' ),
			'releaso_product' => __( 'Product', 'releaso' ),
			'releaso_changes' => __( 'Changes', 'releaso' ),
			'date'            => __( 'Release date', 'releaso' ),
		);
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function column( $column, $post_id ) {
		if ( 'releaso_product' === $column ) {
			$id = (int) get_post_meta( $post_id, ReleaseRepository::META_PRODUCT, true );
			echo $id && get_post( $id ) ? '<a href="' . esc_url( add_query_arg( 'releaso_product', $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>' : '—';
		}
		if ( 'releaso_changes' === $column ) {
			$release = $this->plugin->releases->from_post( get_post( $post_id ) );
			$parts   = array();
			foreach ( $release->counts() as $type => $n ) {
				$parts[] = '<span class="releaso-pill releaso-pill--' . esc_attr( $type ) . '">' . (int) $n . ' ' . esc_html( strtolower( ChangeTypes::label( $type ) ) ) . '</span>';
			}
			echo $parts ? implode( ' ', $parts ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
	}

	/**
	 * Product filter above the releases list.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function product_filter( $post_type ) {
		if ( PostTypes::RELEASE !== $post_type ) {
			return;
		}
		$current = absint( $_GET['releaso_product'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<label class="screen-reader-text" for="releaso-filter-product">' . esc_html__( 'Filter by product', 'releaso' ) . '</label>';
		echo '<select id="releaso-filter-product" name="releaso_product"><option value="">' . esc_html__( 'All products', 'releaso' ) . '</option>';
		foreach ( $this->product_options() as $id => $name ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $id, selected( $current, $id, false ), esc_html( $name ) );
		}
		echo '</select>';
	}

	/**
	 * Applies the product filter.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function filter_query( WP_Query $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || PostTypes::RELEASE !== $query->get( 'post_type' ) ) {
			return;
		}
		$product = absint( $_GET['releaso_product'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $product ) {
			$query->set( 'meta_key', ReleaseRepository::META_PRODUCT );
			$query->set( 'meta_value', $product );
		}
	}
}
