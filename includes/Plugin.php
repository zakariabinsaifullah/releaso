<?php
/**
 * Plugin container: builds the services once and wires their hooks.
 *
 * @package Releaso
 */

namespace Releaso;

use Releaso\Admin\Admin;
use Releaso\Cli\Command;
use Releaso\Core\Installer;
use Releaso\Core\Lifecycle;
use Releaso\Cron\Scheduler;
use Releaso\Data\ChangelogService;
use Releaso\Data\PostTypes;
use Releaso\Data\ProductRepository;
use Releaso\Data\ReleaseRepository;
use Releaso\Frontend\Assets;
use Releaso\Frontend\Block;
use Releaso\Frontend\Renderer;
use Releaso\Frontend\Shortcode;
use Releaso\Rest\RestController;
use Releaso\Source\SourceRegistry;
use Releaso\Support\Http;
use Releaso\Support\Settings;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * HTTP client.
	 *
	 * @var Http
	 */
	public $http;

	/**
	 * Sources.
	 *
	 * @var SourceRegistry
	 */
	public $sources;

	/**
	 * Products.
	 *
	 * @var ProductRepository
	 */
	public $products;

	/**
	 * Manual releases.
	 *
	 * @var ReleaseRepository
	 */
	public $releases;

	/**
	 * Changelog service.
	 *
	 * @var ChangelogService
	 */
	public $changelog;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	public $renderer;

	/**
	 * The instance, booted on first call.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Builds services and registers hooks.
	 *
	 * @return void
	 */
	private function boot() {
		$this->settings  = new Settings();
		$this->http      = new Http( $this->settings );
		$this->sources   = new SourceRegistry( $this->http, $this->settings );
		$this->products  = new ProductRepository();
		$this->releases  = new ReleaseRepository();
		$this->changelog = new ChangelogService( $this->sources, $this->products, $this->releases, $this->settings );
		$this->renderer  = new Renderer( $this->products, $this->changelog, $this->settings );

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		( new Installer() )->register();
		( new PostTypes() )->register();
		( new Lifecycle( $this->products, $this->changelog, $this->sources ) )->register();
		( new Scheduler( $this->changelog ) )->register();
		( new Assets( $this->settings ) )->register();
		( new Shortcode( $this->renderer ) )->register();
		( new Block( $this->renderer, $this->settings ) )->register();
		( new RestController( $this->products, $this->changelog, $this->releases, $this->settings ) )->register();

		if ( is_admin() ) {
			( new Admin( $this ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'releaso', new Command( $this ) );
		}

		/**
		 * Fires when Releaso has loaded; add-ons hook in here.
		 *
		 * @param Plugin $plugin Plugin.
		 */
		do_action( 'releaso_loaded', $this );
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'releaso', false, dirname( plugin_basename( RELEASO_FILE ) ) . '/languages' );
	}

	/**
	 * The capability needed to manage products, settings and imports.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability that manages Releaso.
		 *
		 * @param string $capability Capability.
		 */
		return (string) apply_filters( 'releaso_manage_capability', 'manage_options' );
	}
}
