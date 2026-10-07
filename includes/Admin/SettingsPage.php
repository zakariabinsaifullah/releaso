<?php
/**
 * Releaso → Settings (Settings API).
 *
 * @package Releaso
 */

namespace Releaso\Admin;

use Releaso\Cron\Scheduler;
use Releaso\Data\ChangelogService;
use Releaso\Data\PostTypes;
use Releaso\Frontend\Renderer;
use Releaso\Plugin;
use Releaso\Support\ChangeTypes;
use Releaso\Support\Settings;

/**
 * Settings page.
 */
class SettingsPage {

	const SLUG  = 'releaso-settings';
	const GROUP = 'releaso_settings_group';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Changelog service.
	 *
	 * @var ChangelogService
	 */
	private $changelog;

	/**
	 * Constructor.
	 *
	 * @param Settings         $settings  Settings.
	 * @param ChangelogService $changelog Changelog service.
	 */
	public function __construct( Settings $settings, ChangelogService $changelog ) {
		$this->settings  = $settings;
		$this->changelog = $changelog;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'updated' ), 10, 2 );
		add_filter( 'option_page_capability_' . self::GROUP, array( Plugin::class, 'capability' ) );
	}

	/**
	 * Menu entry.
	 *
	 * @return void
	 */
	public function menu() {
		add_submenu_page( Admin::MENU, __( 'Releaso settings', 'releaso' ), __( 'Settings', 'releaso' ), Plugin::capability(), self::SLUG, array( $this, 'page' ), 30 );
	}

	/**
	 * Registers the option and fields.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'display', __( 'Display', 'releaso' ), array( $this, 'display_intro' ), self::SLUG );
		add_settings_section( 'types', __( 'Change types', 'releaso' ), array( $this, 'types_intro' ), self::SLUG );
		add_settings_section( 'sync', __( 'Sync', 'releaso' ), array( $this, 'sync_intro' ), self::SLUG );
		add_settings_section( 'advanced', __( 'Advanced', 'releaso' ), array( $this, 'advanced_intro' ), self::SLUG );

		$fields = array(
			'default_layout'     => array( 'display', __( 'Default layout', 'releaso' ) ),
			'default_per_page'   => array( 'display', __( 'Releases before "Show older"', 'releaso' ) ),
			'accent_color'       => array( 'display', __( 'Accent colour', 'releaso' ) ),
			'date_format'        => array( 'display', __( 'Date format', 'releaso' ) ),
			'sync_interval'      => array( 'sync', __( 'Check remote sources every', 'releaso' ) ),
			'github_token'       => array( 'sync', __( 'GitHub token', 'releaso' ) ),
			'http_timeout'       => array( 'sync', __( 'Request timeout', 'releaso' ) ),
			'include_unreleased' => array( 'advanced', __( 'Unreleased changes', 'releaso' ) ),
			'delete_data'        => array( 'advanced', __( 'Uninstall', 'releaso' ) ),
		);
		foreach ( $fields as $key => list( $section, $label ) ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'field' ),
				self::SLUG,
				$section,
				array(
					'key'       => $key,
					'label_for' => 'releaso-setting-' . $key,
				)
			);
		}
	}

	/**
	 * Display section intro.
	 *
	 * @return void
	 */
	public function display_intro() {
		echo '<p>' . esc_html__( 'Defaults for every changelog on your site. Each block or shortcode can still override them.', 'releaso' ) . '</p>';
	}

	/**
	 * Change types section intro.
	 *
	 * @return void
	 */
	public function types_intro() {
		echo '<p>' . esc_html__( 'Rename and recolour the labels on each change, or add your own types such as "Breaking" or "Deprecated".', 'releaso' ) . '</p>';
	}

	/**
	 * The change types editor: built-in types (rename, recolour, reset) and custom types.
	 *
	 * @return void
	 */
	private function types_editor() {
		$builtin = ChangeTypes::builtin();
		$types   = ChangeTypes::with_saved( $builtin, ChangeTypes::saved() );
		?>
		<div class="releaso-types" data-releaso-types>
			<div class="releaso-types__list" data-releaso-type-rows>
				<?php
				foreach ( $types as $key => $type ) {
					$this->type_row( (string) $key, $type, $builtin[ $key ] ?? null );
				}
				?>
			</div>
			<template data-releaso-type-template>
				<?php
				$this->type_row(
					'new-__i__',
					array(
						'label'   => '',
						'color'   => '#6a6477',
						'aliases' => array(),
					),
					null
				);
				?>
			</template>
			<div class="releaso-types__foot">
				<button type="button" class="button" data-releaso-type-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a type', 'releaso' ); ?></button>
				<p class="description"><?php esc_html_e( 'Keywords mark a line as this type when a changelog is read, e.g. "Breaking: …" or a "### Breaking" heading. Changing them re-reads every source in the background.', 'releaso' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * One change type row.
	 *
	 * @param string     $key      Type key ("new-__i__" for the template).
	 * @param array      $type     Current label, color and aliases.
	 * @param array|null $defaults Built-in defaults, null for a custom type.
	 * @return void
	 */
	private function type_row( $key, array $type, $defaults ) {
		$name    = Settings::OPTION . '[types][' . $key . ']';
		$color   = self::hex6( $type['color'] ) ? self::hex6( $type['color'] ) : '#6a6477';
		$label   = (string) $type['label'];
		$is_new  = 0 === strpos( $key, 'new-' ) && ! $defaults;
		$aliases = array_diff( (array) ( $type['aliases'] ?? array() ), array( $key, strtolower( $label ) ) );
		?>
		<div class="releaso-type<?php echo $defaults ? ' is-builtin' : ''; ?>" style="--c:<?php echo esc_attr( $color ); ?>"<?php echo $is_new ? '' : ' data-key="' . esc_attr( $key ) . '"'; ?>>
			<label class="releaso-type__swatch">
				<span class="screen-reader-text"><?php esc_html_e( 'Colour', 'releaso' ); ?></span>
				<input type="color" name="<?php echo esc_attr( $name ); ?>[color]" value="<?php echo esc_attr( $color ); ?>" data-releaso-type-color>
			</label>
			<div class="releaso-type__main">
				<input type="text" class="releaso-type__label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Name, e.g. Breaking', 'releaso' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'releaso' ); ?>" data-releaso-type-label<?php echo $defaults ? ' required' : ''; ?>>
				<?php if ( $defaults ) : ?>
					<span class="releaso-type__meta">
						<?php
						/* translators: %s: example keywords */
						echo esc_html( sprintf( __( 'Built-in · matches %s…', 'releaso' ), implode( ', ', array_slice( $defaults['aliases'], 0, 4 ) ) ) );
						?>
					</span>
				<?php else : ?>
					<input type="text" class="releaso-type__aliases" name="<?php echo esc_attr( $name ); ?>[aliases]" value="<?php echo esc_attr( implode( ', ', $aliases ) ); ?>" placeholder="<?php esc_attr_e( 'Keywords, comma-separated: breaking, bc', 'releaso' ); ?>" aria-label="<?php esc_attr_e( 'Keywords', 'releaso' ); ?>">
					<?php if ( ! $is_new ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>[key]" value="<?php echo esc_attr( $key ); ?>">
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<span class="releaso-pill releaso-type__preview" data-releaso-type-preview><?php echo esc_html( '' !== $label ? $label : __( 'Preview', 'releaso' ) ); ?></span>
			<span class="releaso-type__key"><?php echo $is_new ? '' : '<code>' . esc_html( $key ) . '</code>'; ?></span>
			<?php if ( $defaults ) : ?>
				<button type="button" class="button-link releaso-type__action" data-releaso-type-reset data-label="<?php echo esc_attr( $defaults['label'] ); ?>" data-color="<?php echo esc_attr( self::hex6( $defaults['color'] ) ); ?>"><?php esc_html_e( 'Reset', 'releaso' ); ?></button>
			<?php else : ?>
				<button type="button" class="button-link button-link-delete releaso-type__action" data-releaso-type-remove aria-label="<?php esc_attr_e( 'Remove this type', 'releaso' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * A colour as #rrggbb (what <input type="color"> needs), or ''.
	 *
	 * @param string $color Hex colour.
	 * @return string
	 */
	private static function hex6( $color ) {
		$color = (string) sanitize_hex_color( $color );
		if ( 4 === strlen( $color ) ) {
			$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
		}
		return strtolower( $color );
	}

	/**
	 * Advanced section intro.
	 *
	 * @return void
	 */
	public function advanced_intro() {
		echo '<p>' . esc_html__( 'Parsing details and what happens to your data when Releaso is removed.', 'releaso' ) . '</p>';
	}

	/**
	 * Sync section intro.
	 *
	 * @return void
	 */
	public function sync_intro() {
		echo '<p>' . esc_html__( 'Remote changelogs are checked in the background, never while a visitor waits. Unchanged files cost a quick "not modified" reply.', 'releaso' ) . '</p>';
	}

	/**
	 * Prints a field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function field( $args ) {
		$key   = $args['key'];
		$value = $this->settings->get( $key );
		$name  = Settings::OPTION . '[' . $key . ']';
		$id    = 'releaso-setting-' . $key;

		switch ( $key ) {
			case 'default_layout':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( Renderer::layouts() as $layout => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $layout ), selected( $value, $layout, false ), esc_html( $label ) );
				}
				echo '</select><p class="description">' . esc_html__( 'Blocks and shortcodes can choose their own.', 'releaso' ) . '</p>';
				break;

			case 'default_per_page':
				printf( '<span class="releaso-input-group"><input type="number" min="1" max="100" id="%1$s" name="%2$s" value="%3$d" class="small-text"><span class="releaso-input-group__suffix">%4$s</span></span>', esc_attr( $id ), esc_attr( $name ), (int) $value, esc_html__( 'releases', 'releaso' ) );
				break;

			case 'accent_color':
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text releaso-color" placeholder="#5a18e6" data-default-color="#5a18e6">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				echo '<p class="description">' . esc_html__( 'Hex colour for the timeline, links and highlights. Empty uses the default purple. Themes can also set --releaso-accent.', 'releaso' ) . '</p>';
				break;

			case 'date_format':
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( get_option( 'date_format' ) ) );
				echo '<p class="description">' . esc_html__( 'PHP date format. Empty uses the site\'s format.', 'releaso' ) . '</p>';
				break;

			case 'sync_interval':
				$options = array(
					1   => __( 'Hour', 'releaso' ),
					6   => __( '6 hours', 'releaso' ),
					12  => __( '12 hours', 'releaso' ),
					24  => __( 'Day', 'releaso' ),
					168 => __( 'Week', 'releaso' ),
				);
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $options as $hours => $label ) {
					printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $hours, selected( (int) $value, $hours, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'github_token':
				if ( defined( 'RELEASO_GITHUB_TOKEN' ) && RELEASO_GITHUB_TOKEN ) {
					echo '<p>' . esc_html__( 'Set by the RELEASO_GITHUB_TOKEN constant in wp-config.php.', 'releaso' ) . '</p>';
					break;
				}
				printf(
					'<input type="password" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="new-password" placeholder="github_pat_…" data-releaso-secret>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ? Fields::MASK : '' )
				);
				echo '<p class="description">' . esc_html__( 'Needed for private repositories, and raises GitHub\'s rate limit. A fine-grained token with read-only "Contents" access is enough. For extra safety define RELEASO_GITHUB_TOKEN in wp-config.php instead.', 'releaso' ) . '</p>';
				break;

			case 'http_timeout':
				printf( '<span class="releaso-input-group"><input type="number" min="3" max="60" id="%1$s" name="%2$s" value="%3$d" class="small-text"><span class="releaso-input-group__suffix">%4$s</span></span>', esc_attr( $id ), esc_attr( $name ), (int) $value, esc_html__( 'seconds', 'releaso' ) );
				break;

			case 'include_unreleased':
				printf( '<label class="releaso-toggle"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s><span class="releaso-toggle__track" aria-hidden="true"></span><span class="releaso-toggle__label">%4$s</span></label>', esc_attr( $id ), esc_attr( $name ), checked( ! empty( $value ), true, false ), esc_html__( 'Show the "Unreleased" section of Markdown changelogs', 'releaso' ) );
				break;

			case 'delete_data':
				printf( '<label class="releaso-toggle"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s><span class="releaso-toggle__track" aria-hidden="true"></span><span class="releaso-toggle__label">%4$s</span></label>', esc_attr( $id ), esc_attr( $name ), checked( ! empty( $value ), true, false ), esc_html__( 'Delete all products, releases and settings when the plugin is deleted', 'releaso' ) );
				break;
		}
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public function page() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		?>
		<div class="wrap releaso-wrap">
			<div class="releaso-page-head">
				<div>
					<h1><?php esc_html_e( 'Settings', 'releaso' ); ?></h1>
					<p class="releaso-page-head__lead"><?php esc_html_e( 'How changelogs look, how often they are refreshed and how Releaso handles your data.', 'releaso' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end">
			<?php settings_errors(); // "Settings saved." is only automatic under Settings → …. ?>
			<div class="releaso-settings-layout">
				<?php $this->section_nav(); ?>
				<form method="post" action="options.php" class="releaso-settings" data-releaso-settings>
					<?php
					settings_fields( self::GROUP );
					$this->sections();
					?>
					<div class="releaso-savebar">
						<span class="releaso-savebar__status" data-releaso-save-status aria-live="polite"></span>
						<?php submit_button( __( 'Save changes', 'releaso' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
				<?php $this->aside(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Section menu (left column on wide screens).
	 *
	 * @return void
	 */
	private function section_nav() {
		global $wp_settings_sections;
		?>
		<nav class="releaso-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'releaso' ); ?>">
			<?php foreach ( (array) ( $wp_settings_sections[ self::SLUG ] ?? array() ) as $section ) : ?>
				<a href="#releaso-section-<?php echo esc_attr( $section['id'] ); ?>" data-releaso-section-link>
					<span class="dashicons dashicons-<?php echo esc_attr( self::section_icon( $section['id'] ) ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $section['title'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Live preview of the display settings and a quick start (right column on wide screens).
	 *
	 * @return void
	 */
	private function aside() {
		$accent = sanitize_hex_color( (string) $this->settings->get( 'accent_color', '' ) );
		$layout = (string) $this->settings->get( 'default_layout', 'timeline' );
		$sample = array(
			array(
				'2.4.0',
				__( 'Today', 'releaso' ),
				array(
					array( 'new', __( 'Panorama slider layout', 'releaso' ) ),
					array( 'improved', __( 'Faster image loading on mobile', 'releaso' ) ),
				),
			),
			array(
				'2.3.1',
				__( '2 weeks ago', 'releaso' ),
				array(
					array( 'fixed', __( 'Arrows hidden behind captions', 'releaso' ) ),
					array( 'security', __( 'Escaped slide titles in the editor', 'releaso' ) ),
				),
			),
		);
		?>
		<aside class="releaso-settings-aside">
			<div class="releaso-card releaso-preview-card">
				<header class="releaso-card__head">
					<span class="releaso-card__icon dashicons dashicons-visibility" aria-hidden="true"></span>
					<div>
						<h2><?php esc_html_e( 'Live preview', 'releaso' ); ?></h2>
						<p><?php esc_html_e( 'Follows the layout and accent colour as you change them.', 'releaso' ); ?></p>
					</div>
				</header>
				<div class="releaso-mini releaso-mini--<?php echo esc_attr( sanitize_html_class( $layout ) ); ?>" data-releaso-preview<?php echo $accent ? ' style="--mini-accent:' . esc_attr( $accent ) . '"' : ''; ?>>
					<?php foreach ( $sample as list( $version, $when, $changes ) ) : ?>
						<div class="releaso-mini__release">
							<div class="releaso-mini__head">
								<span class="releaso-mini__version">v<?php echo esc_html( $version ); ?></span>
								<span class="releaso-mini__date"><?php echo esc_html( $when ); ?></span>
							</div>
							<ul class="releaso-mini__changes">
								<?php foreach ( $changes as list( $type, $text ) ) : ?>
									<li><span class="releaso-pill releaso-pill--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ChangeTypes::label( $type ) ); ?></span> <?php echo esc_html( $text ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
					<span class="releaso-mini__more"><?php esc_html_e( 'Show older', 'releaso' ); ?></span>
				</div>
			</div>

			<div class="releaso-card releaso-quickstart">
				<header class="releaso-card__head">
					<span class="releaso-card__icon dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<h2><?php esc_html_e( 'Quick start', 'releaso' ); ?></h2>
				</header>
				<ol>
					<li>
						<?php
						printf(
							/* translators: %s: link to add a product */
							esc_html__( '%s and pick where its changelog comes from.', 'releaso' ),
							'<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::PRODUCT ) ) . '">' . esc_html__( 'Add a product', 'releaso' ) . '</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Insert the Releaso Changelog block, or paste the shortcode:', 'releaso' ); ?>
						<div class="releaso-copy">
							<input type="text" class="widefat code" readonly value="[releaso products=&quot;your-product&quot;]" onfocus="this.select()" aria-label="<?php esc_attr_e( 'Shortcode', 'releaso' ); ?>">
							<button type="button" class="button releaso-copy__button" data-releaso-copy><?php esc_html_e( 'Copy', 'releaso' ); ?></button>
						</div>
					</li>
					<li><?php esc_html_e( 'Releaso keeps it up to date in the background.', 'releaso' ); ?></li>
				</ol>
			</div>
		</aside>
		<?php
	}

	/**
	 * Dashicon for a settings section.
	 *
	 * @param string $id Section ID.
	 * @return string
	 */
	private static function section_icon( $id ) {
		$icons = array(
			'display'  => 'art',
			'types'    => 'tag',
			'sync'     => 'update',
			'advanced' => 'admin-tools',
		);
		return $icons[ $id ] ?? 'admin-generic';
	}

	/**
	 * Prints each settings section as a card (do_settings_sections() without the bare markup).
	 *
	 * @return void
	 */
	private function sections() {
		global $wp_settings_sections;
		foreach ( (array) ( $wp_settings_sections[ self::SLUG ] ?? array() ) as $section ) {
			?>
			<section class="releaso-card releaso-settings__section" id="releaso-section-<?php echo esc_attr( $section['id'] ); ?>">
				<header class="releaso-card__head">
					<span class="releaso-card__icon dashicons dashicons-<?php echo esc_attr( self::section_icon( $section['id'] ) ); ?>" aria-hidden="true"></span>
					<div>
						<h2><?php echo esc_html( $section['title'] ); ?></h2>
						<?php
						if ( $section['callback'] ) {
							call_user_func( $section['callback'], $section );
						}
						?>
					</div>
				</header>
				<?php if ( 'types' === $section['id'] ) : ?>
					<?php $this->types_editor(); ?>
				<?php else : ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( self::SLUG, $section['id'] ); ?>
					</table>
				<?php endif; ?>
			</section>
			<?php
		}
	}

	/**
	 * Settings changed: drop cached changelogs; when parsing changed, re-sync in the background.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 * @return void
	 */
	public function updated( $old, $new ) {
		$this->settings->reset();
		ChangeTypes::reset();
		$this->changelog->flush();
		// Parsing depends on these: re-read every source in the background.
		$words = static function ( $types ) {
			$out = array();
			foreach ( (array) $types as $key => $type ) {
				$out[ $key ] = is_array( $type ) ? (array) ( $type['aliases'] ?? array() ) : array();
			}
			return wp_json_encode( $out );
		};
		if ( ! empty( $old['include_unreleased'] ) !== ! empty( $new['include_unreleased'] ) || $words( $old['types'] ?? array() ) !== $words( $new['types'] ?? array() ) ) {
			Scheduler::resync();
		}
	}
}
