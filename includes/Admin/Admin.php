<?php
/**
 * Admin bootstrap: menu, screens, assets and notices.
 *
 * @package Releaso
 */

namespace Releaso\Admin;

use Releaso\Data\PostTypes;
use Releaso\Frontend\Assets;
use Releaso\Plugin;
use Releaso\Support\ChangeTypes;

/**
 * Admin.
 */
class Admin {

	const MENU = 'edit.php?post_type=' . PostTypes::PRODUCT;

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
		( new ProductScreen( $this->plugin ) )->register();
		( new ReleaseScreen( $this->plugin ) )->register();
		( new SettingsPage( $this->plugin->settings, $this->plugin->changelog ) )->register();
		( new ToolsPage( $this->plugin ) )->register();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RELEASO_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'in_admin_header', array( $this, 'header' ) );
	}

	/**
	 * Marks Releaso screens so the admin styles stay scoped to them.
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		$screen = get_current_screen();
		return $screen && self::is_releaso_screen( $screen ) ? $classes . ' releaso-screen ' : $classes;
	}

	/**
	 * The Releaso header bar: brand and section navigation, above every Releaso screen.
	 *
	 * @return void
	 */
	public function header() {
		$screen = get_current_screen();
		if ( ! $screen || ! self::is_releaso_screen( $screen ) ) {
			return;
		}
		$tabs = array(
			'products' => array( __( 'Products', 'releaso' ), admin_url( self::MENU ), 'products' ),
			'releases' => array( __( 'Releases', 'releaso' ), admin_url( 'edit.php?post_type=' . PostTypes::RELEASE ), 'tag' ),
			'tools'    => array( __( 'Import / Export', 'releaso' ), admin_url( self::MENU . '&page=' . ToolsPage::SLUG ), 'database-import' ),
			'settings' => array( __( 'Settings', 'releaso' ), admin_url( self::MENU . '&page=' . SettingsPage::SLUG ), 'admin-settings' ),
		);
		if ( false !== strpos( (string) $screen->id, SettingsPage::SLUG ) ) {
			$active = 'settings';
		} elseif ( false !== strpos( (string) $screen->id, ToolsPage::SLUG ) ) {
			$active = 'tools';
		} else {
			$active = PostTypes::RELEASE === $screen->post_type ? 'releases' : 'products';
		}
		?>
		<div class="releaso-header">
			<a class="releaso-header__brand" href="<?php echo esc_url( admin_url( self::MENU ) ); ?>">
				<span class="releaso-header__logo" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19V5"/><path d="M5 12h6"/><path d="M5 5h9l-3 3.5L14 12"/><circle cx="17" cy="17" r="2.5"/></svg>
				</span>
				<span class="releaso-header__name">Releaso</span>
				<span class="releaso-header__version">v<?php echo esc_html( RELEASO_VERSION ); ?></span>
			</a>
			<nav class="releaso-header__nav" aria-label="<?php esc_attr_e( 'Releaso', 'releaso' ); ?>">
				<?php foreach ( $tabs as $key => list( $label, $url, $icon ) ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="releaso-header__tab<?php echo $key === $active ? ' is-active' : ''; ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<a class="releaso-header__action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::RELEASE ) ); ?>">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Write a release', 'releaso' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Admin styles on Releaso screens; the editor scripts on the edit screens.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ! self::is_releaso_screen( $screen ) ) {
			return;
		}
		$is_settings = false !== strpos( (string) $screen->id, SettingsPage::SLUG );
		if ( $is_settings ) {
			wp_enqueue_style( 'wp-color-picker' );
		}
		wp_enqueue_style( 'releaso-admin', RELEASO_URL . 'assets/css/admin.css', array(), Assets::version( 'assets/css/admin.css' ) );
		wp_add_inline_style( 'releaso-admin', self::type_css() );

		wp_enqueue_script( 'releaso-admin', RELEASO_URL . 'assets/js/admin.js', $is_settings ? array( 'wp-color-picker' ) : array(), Assets::version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'releaso-admin',
			'releasoAdmin',
			array(
				'copy'     => __( 'Copy', 'releaso' ),
				'copied'   => __( 'Copied', 'releaso' ),
				'show'     => __( 'Show', 'releaso' ),
				'hide'     => __( 'Hide', 'releaso' ),
				'unsaved'  => __( 'You have unsaved changes', 'releaso' ),
				'saved'    => __( 'All changes saved', 'releaso' ),
				'preview'  => __( 'Preview', 'releaso' ),
				'dropFile' => __( 'Drop a file here or click to browse', 'releaso' ),
				'tooBig'   => __( 'The file is larger than 2 MB.', 'releaso' ),
			)
		);

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$file = PostTypes::RELEASE === $screen->post_type ? 'release-editor' : 'product-editor';
			wp_enqueue_script( 'releaso-' . $file, RELEASO_URL . 'assets/js/' . $file . '.js', array(), Assets::version( 'assets/js/' . $file . '.js' ), true );
		}
	}

	/**
	 * Change type colours as custom properties for the admin pills and release rows.
	 *
	 * @return string
	 */
	private static function type_css() {
		$css = '';
		foreach ( ChangeTypes::all() as $key => $type ) {
			$color = sanitize_hex_color( $type['color'] );
			if ( $color ) {
				// Keys are sanitize_key()'d, safe in selectors.
				$css .= ".releaso-pill--{$key},.releaso-row[data-type=\"{$key}\"]{--c:{$color}}";
			}
		}
		return $css;
	}

	/**
	 * Whether a screen belongs to Releaso.
	 *
	 * @param \WP_Screen $screen Screen.
	 * @return bool
	 */
	public static function is_releaso_screen( $screen ) {
		return in_array( $screen->post_type, array( PostTypes::PRODUCT, PostTypes::RELEASE ), true )
			|| false !== strpos( (string) $screen->id, 'releaso' );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( self::MENU ) ) . '">' . esc_html__( 'Products', 'releaso' ) . '</a>',
			'<a href="' . esc_url( admin_url( self::MENU . '&page=' . SettingsPage::SLUG ) ) . '">' . esc_html__( 'Settings', 'releaso' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Shows the notice queued with Admin::notice() before the last redirect.
	 *
	 * @return void
	 */
	public function notices() {
		$screen = get_current_screen();
		if ( ! $screen || ! self::is_releaso_screen( $screen ) ) {
			return;
		}
		$notice = get_transient( 'releaso_notice_' . get_current_user_id() );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'releaso_notice_' . get_current_user_id() );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info' ),
			wp_kses(
				$notice['message'],
				array(
					'strong' => array(),
					'code'   => array(),
					'br'     => array(),
				)
			)
		);
	}

	/**
	 * Queues a notice for the current user's next admin page.
	 *
	 * @param string $message Message (may contain <strong>, <code>, <br>).
	 * @param string $type    success, error, warning or info.
	 * @return void
	 */
	public static function notice( $message, $type = 'success' ) {
		set_transient(
			'releaso_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			MINUTE_IN_SECONDS
		);
	}
}
