<?php
/**
 * Product screens: the source settings, the sync status box, list columns and "Sync now".
 *
 * @package Releaso
 */

namespace Releaso\Admin;

use Releaso\Data\PostTypes;
use Releaso\Plugin;
use WP_Post;

/**
 * Product admin screen.
 */
class ProductScreen {

	const NONCE = 'releaso_product';

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
		add_action( 'add_meta_boxes_' . PostTypes::PRODUCT, array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . PostTypes::PRODUCT, array( $this, 'save' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . PostTypes::PRODUCT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostTypes::PRODUCT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_releaso_sync', array( $this, 'handle_sync' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
	}

	/**
	 * Meta boxes.
	 *
	 * @return void
	 */
	public function meta_boxes() {
		add_meta_box( 'releaso-source', __( 'Changelog source', 'releaso' ), array( $this, 'source_box' ), PostTypes::PRODUCT, 'normal', 'high' );
		add_meta_box( 'releaso-status', __( 'Changelog status', 'releaso' ), array( $this, 'status_box' ), PostTypes::PRODUCT, 'side', 'high' );
		add_meta_box( 'releaso-embed', __( 'Show it on your site', 'releaso' ), array( $this, 'embed_box' ), PostTypes::PRODUCT, 'side' );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string  $text Placeholder.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		return PostTypes::PRODUCT === $post->post_type ? __( 'Product name, e.g. Slider Blocks Pro', 'releaso' ) : $text;
	}

	/**
	 * The source settings box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function source_box( WP_Post $post ) {
		$product = $this->plugin->products->from_post( $post );
		$current = $this->plugin->sources->get( $product->source ) ? $product->source : 'wporg';
		if ( 'auto-draft' === $post->post_status ) {
			$current = 'wporg';
		}

		wp_nonce_field( self::NONCE, 'releaso_product_nonce' );
		?>
		<div class="releaso-admin releaso-source" data-releaso-source>
			<fieldset class="releaso-source__choices">
				<legend class="screen-reader-text"><?php esc_html_e( 'Where does the changelog come from?', 'releaso' ); ?></legend>
				<?php foreach ( $this->plugin->sources->all() as $key => $source ) : ?>
					<label class="releaso-source__choice">
						<input type="radio" name="releaso_source" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
						<span class="releaso-source__icon dashicons dashicons-<?php echo esc_attr( self::source_icon( $key ) ); ?>" aria-hidden="true"></span>
						<span class="releaso-source__name"><?php echo esc_html( $source->label() ); ?></span>
						<span class="releaso-source__desc"><?php echo esc_html( $source->description() ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<?php foreach ( $this->plugin->sources->all() as $key => $source ) : ?>
				<?php $fields = $source->fields(); ?>
				<div class="releaso-source__fields" data-source-fields="<?php echo esc_attr( $key ); ?>"<?php echo $key !== $current ? ' hidden' : ''; ?>>
					<?php
					foreach ( $fields as $field_key => $field ) {
						$field['key'] = $field_key;
						$value        = $key === $product->source ? $product->get( $field_key, $field['default'] ?? '' ) : ( $field['default'] ?? '' );
						Fields::render( "releaso_config[{$key}][{$field_key}]", "releaso-{$key}-{$field_key}", $field, $value );
					}
					if ( ! $fields ) {
						echo '<p class="description">' . esc_html( $source->description() ) . '</p>';
					}
					?>
				</div>
			<?php endforeach; ?>

			<div class="releaso-field">
				<label class="releaso-field__label" for="releaso-link"><?php esc_html_e( 'Product page (optional)', 'releaso' ); ?></label>
				<input type="url" id="releaso-link" name="releaso_link" value="<?php echo esc_attr( $product->link ); ?>" class="regular-text" placeholder="https://">
				<p class="description"><?php esc_html_e( 'Shown as a "Get it" link next to the product name.', 'releaso' ); ?></p>
			</div>

			<p class="description releaso-source__save-note">
				<?php esc_html_e( 'The changelog is read when you save, then kept up to date in the background. Releases you write under Releaso → Releases are added to it and replace the source\'s notes for the same version.', 'releaso' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Dashicon for a source in the source picker; sources added by other plugins get a generic one.
	 *
	 * @param string $key Source key.
	 * @return string
	 */
	public static function source_icon( $key ) {
		$icons = array(
			'wporg'     => 'wordpress',
			'installed' => 'admin-plugins',
			'github'    => 'editor-code',
			'url'       => 'admin-links',
			'text'      => 'media-text',
			'manual'    => 'edit',
		);
		/**
		 * Filters the dashicon (without the "dashicons-" prefix) shown for a source.
		 *
		 * @param string $icon Icon.
		 * @param string $key  Source key.
		 */
		return (string) apply_filters( 'releaso_source_icon', $icons[ $key ] ?? 'admin-generic', $key );
	}

	/**
	 * The status box: last sync, errors, latest versions, Sync now.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function status_box( WP_Post $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Save the product to read its changelog.', 'releaso' ) . '</p>';
			return;
		}
		$product  = $this->plugin->products->from_post( $post );
		$state    = $this->plugin->changelog->state( $product );
		$releases = $this->plugin->changelog->releases( $product );
		self::status_summary( $state );
		?>
		<?php if ( count( $releases ) ) : ?>
			<ul class="releaso-status__versions">
				<?php foreach ( $releases->slice( 5 ) as $release ) : ?>
					<li>
						<strong>v<?php echo esc_html( $release->version ); ?></strong>
						<span><?php echo esc_html( $release->date ? date_i18n( get_option( 'date_format' ), strtotime( $release->date ) ) : '—' ); ?></span>
						<span class="releaso-status__count">
							<?php
							/* translators: %d: number of changes */
							echo esc_html( sprintf( _n( '%d change', '%d changes', count( $release->changes ), 'releaso' ), count( $release->changes ) ) );
							?>
							<?php if ( 'manual' === $release->origin ) : ?>
								· <?php esc_html_e( 'written here', 'releaso' ); ?>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description">
				<?php
				/* translators: %d: number of releases */
				echo esc_html( sprintf( _n( '%d release in total.', '%d releases in total.', count( $releases ), 'releaso' ), count( $releases ) ) );
				?>
			</p>
		<?php endif; ?>
		<p class="releaso-status__actions">
			<a class="button" href="<?php echo esc_url( self::sync_url( $post->ID ) ); ?>" data-releaso-busy><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Sync now', 'releaso' ); ?></a>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::RELEASE . '&product=' . $post->ID ) ); ?>"><?php esc_html_e( 'Write a release', 'releaso' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Prints the sync state line.
	 *
	 * @param array $state Sync state.
	 * @return void
	 */
	public static function status_summary( array $state ) {
		if ( ! $state ) {
			echo '<p class="releaso-status releaso-status--pending">' . esc_html__( 'Not synced yet.', 'releaso' ) . '</p>';
			return;
		}
		if ( ! empty( $state['error'] ) ) {
			echo '<p class="releaso-status releaso-status--error"><strong>' . esc_html__( 'Last sync failed:', 'releaso' ) . '</strong> ' . esc_html( $state['error'] ) . '</p>';
			if ( ! empty( $state['synced_at'] ) ) {
				echo '<p class="description">' . esc_html__( 'Showing the last good copy.', 'releaso' ) . '</p>';
			}
			return;
		}
		echo '<p class="releaso-status releaso-status--ok">';
		printf(
			/* translators: 1: time ago, 2: format */
			esc_html__( 'Checked %1$s ago · read as %2$s', 'releaso' ),
			esc_html( human_time_diff( (int) ( $state['checked_at'] ?? time() ) ) ),
			'<code>' . esc_html( $state['format'] ?? '—' ) . '</code>'
		);
		echo '</p>';
	}

	/**
	 * The embed box: shortcode to copy.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function embed_box( WP_Post $post ) {
		$slug = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
		if ( '' === $slug ) {
			echo '<p class="description">' . esc_html__( 'Save the product to get its shortcode.', 'releaso' ) . '</p>';
			return;
		}
		?>
		<p><?php esc_html_e( 'Add the Releaso Changelog block to any page, or paste this shortcode:', 'releaso' ); ?></p>
		<div class="releaso-copy">
			<input type="text" class="widefat code" readonly value="<?php echo esc_attr( '[releaso products="' . $slug . '"]' ); ?>" onfocus="this.select()">
			<button type="button" class="button releaso-copy__button" data-releaso-copy><?php esc_html_e( 'Copy', 'releaso' ); ?></button>
		</div>
		<p class="description"><?php esc_html_e( 'The product slug (in the shortcode) comes from the name; change it in Quick Edit.', 'releaso' ); ?></p>
		<?php
	}

	/**
	 * Saves the source settings and syncs.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function save( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST['releaso_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['releaso_product_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$key    = sanitize_key( wp_unslash( $_POST['releaso_source'] ?? 'manual' ) );
		$source = $this->plugin->sources->get( $key );
		if ( ! $source ) {
			return;
		}

		$previous = $this->plugin->products->from_post( $post );
		// Raw input goes straight to the source's own sanitizer.
		$input = isset( $_POST['releaso_config'][ $key ] ) && is_array( $_POST['releaso_config'][ $key ] ) ? wp_unslash( $_POST['releaso_config'][ $key ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$input = Fields::keep_secrets( $source->fields(), $input, $previous->source === $key ? $previous->config : array() );
		$link  = esc_url_raw( wp_unslash( $_POST['releaso_link'] ?? '' ), array( 'http', 'https' ) );

		$this->plugin->products->save_source( $post_id, $key, $source->sanitize( $input ), $link );

		$product = $this->plugin->products->find( $post_id );
		$result  = $product ? $this->plugin->changelog->sync( $product, true ) : true;
		$this->plugin->changelog->flush();

		if ( is_wp_error( $result ) ) {
			Admin::notice( '<strong>' . esc_html__( 'Saved, but the changelog could not be read:', 'releaso' ) . '</strong> ' . esc_html( $result->get_error_message() ), 'warning' );
		}
	}

	/**
	 * List columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'                => $columns['cb'],
			'title'             => __( 'Product', 'releaso' ),
			'releaso_source'    => __( 'Source', 'releaso' ),
			'releaso_latest'    => __( 'Latest', 'releaso' ),
			'releaso_releases'  => __( 'Releases', 'releaso' ),
			'releaso_status'    => __( 'Sync', 'releaso' ),
			'releaso_shortcode' => __( 'Shortcode', 'releaso' ),
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
		$product = $this->plugin->products->find( $post_id );
		if ( ! $product ) {
			return;
		}
		switch ( $column ) {
			case 'releaso_source':
				$source = $this->plugin->sources->get( $product->source );
				echo esc_html( $source ? $source->label() : $product->source );
				$detail = $product->get( 'slug' ) ? $product->get( 'slug' ) : ( $product->get( 'repo' ) ? $product->get( 'repo' ) : $product->get( 'plugin' ) );
				if ( $detail ) {
					echo '<br><code>' . esc_html( $detail ) . '</code>';
				}
				break;
			case 'releaso_latest':
				$latest = $this->plugin->changelog->releases( $product )->latest();
				echo $latest ? '<strong>v' . esc_html( $latest->version ) . '</strong>' : '—';
				break;
			case 'releaso_releases':
				echo (int) count( $this->plugin->changelog->releases( $product ) );
				break;
			case 'releaso_status':
				$state = $this->plugin->changelog->state( $product );
				if ( ! empty( $state['error'] ) ) {
					echo '<span class="releaso-dot releaso-dot--error" title="' . esc_attr( $state['error'] ) . '"></span>' . esc_html__( 'Failed', 'releaso' );
				} elseif ( $state ) {
					/* translators: %s: time ago */
					echo '<span class="releaso-dot releaso-dot--ok"></span>' . esc_html( sprintf( __( '%s ago', 'releaso' ), human_time_diff( (int) ( $state['checked_at'] ?? time() ) ) ) );
				} else {
					echo '—';
				}
				break;
			case 'releaso_shortcode':
				echo '<code class="releaso-shortcode" data-releaso-copy tabindex="0" role="button" title="' . esc_attr__( 'Click to copy', 'releaso' ) . '">[releaso products="' . esc_html( $product->slug ) . '"]</code>';
				break;
		}
	}

	/**
	 * "Sync now" row action.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( PostTypes::PRODUCT === $post->post_type && current_user_can( 'edit_post', $post->ID ) && 'trash' !== $post->post_status ) {
			$actions['releaso_sync'] = '<a href="' . esc_url( self::sync_url( $post->ID ) ) . '">' . esc_html__( 'Sync now', 'releaso' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * URL of the sync action (0 syncs every product).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function sync_url( $product_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=releaso_sync&product=' . (int) $product_id ), 'releaso_sync_' . (int) $product_id );
	}

	/**
	 * Handles "Sync now".
	 *
	 * @return void
	 */
	public function handle_sync() {
		$id = absint( $_GET['product'] ?? 0 );
		check_admin_referer( 'releaso_sync_' . $id );

		if ( $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				wp_die( esc_html__( 'You are not allowed to sync this product.', 'releaso' ), 403 );
			}
			$product = $this->plugin->products->find( $id );
			$result  = $product ? $this->plugin->changelog->sync( $product, true ) : new \WP_Error( 'releaso_missing', __( 'Product not found.', 'releaso' ) );
			Admin::notice(
				is_wp_error( $result ) ? esc_html( $result->get_error_message() ) : esc_html__( 'Changelog synced.', 'releaso' ),
				is_wp_error( $result ) ? 'error' : 'success'
			);
		} else {
			if ( ! current_user_can( Plugin::capability() ) ) {
				wp_die( esc_html__( 'You are not allowed to sync products.', 'releaso' ), 403 );
			}
			$results = $this->plugin->changelog->sync_all( true );
			$failed  = array_filter( $results, 'is_wp_error' );
			Admin::notice(
				/* translators: 1: synced count, 2: failed count */
				esc_html( sprintf( __( 'Synced %1$d products, %2$d failed.', 'releaso' ), count( $results ) - count( $failed ), count( $failed ) ) ),
				$failed ? 'warning' : 'success'
			);
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( Admin::MENU ) );
		exit;
	}

	/**
	 * Post updated messages without "View post" links (products are not public).
	 *
	 * @param array $messages Messages.
	 * @return array
	 */
	public function messages( $messages ) {
		$messages[ PostTypes::PRODUCT ] = array(
			0  => '',
			1  => __( 'Product updated.', 'releaso' ),
			4  => __( 'Product updated.', 'releaso' ),
			6  => __( 'Product published.', 'releaso' ),
			7  => __( 'Product saved.', 'releaso' ),
			8  => __( 'Product submitted.', 'releaso' ),
			10 => __( 'Product draft updated.', 'releaso' ),
		);
		return $messages;
	}
}
