<?php
/**
 * REST API: releaso/v1.
 *
 *   GET  /releaso/v1/products                      Published products with their latest version.
 *   GET  /releaso/v1/products/{slug}/releases      A product's releases (?limit=10&types=new,fixed).
 *   POST /releaso/v1/products/{id}/sync            Sync a product's source now.          (manage)
 *   POST /releaso/v1/products/{id}/import          Import a changelog as releases.       (manage)
 *
 * Reading is public, like the changelog on the page; turn it off with
 * add_filter( 'releaso_rest_public', '__return_false' ).
 *
 * @package Releaso
 */

namespace Releaso\Rest;

use Releaso\Data\ChangelogService;
use Releaso\Data\Importer;
use Releaso\Data\ProductRepository;
use Releaso\Data\ReleaseRepository;
use Releaso\Model\Product;
use Releaso\Parser\ParserFactory;
use Releaso\Plugin;
use Releaso\Support\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller.
 */
class RestController {

	const NAMESPACE = 'releaso/v1';

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
	 * Releases.
	 *
	 * @var ReleaseRepository
	 */
	private $releases;

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
	 * @param ReleaseRepository $releases  Releases.
	 * @param Settings          $settings  Settings.
	 */
	public function __construct( ProductRepository $products, ChangelogService $changelog, ReleaseRepository $releases, Settings $settings ) {
		$this->products  = $products;
		$this->changelog = $changelog;
		$this->releases  = $releases;
		$this->settings  = $settings;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_products' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<product>[\w-]+)/releases',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_releases' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'maximum'           => 500,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'types' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'force' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'content'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'format'    => array(
						'type'    => 'string',
						'enum'    => ParserFactory::FORMATS,
						'default' => 'auto',
					),
					'overwrite' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'status'    => array(
						'type'    => 'string',
						'enum'    => array( 'publish', 'draft' ),
						'default' => 'publish',
					),
					'dry_run'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Public read access (filterable); editors always.
	 *
	 * @return bool
	 */
	public function can_read() {
		/**
		 * Filters whether changelogs are readable through the REST API without logging in.
		 *
		 * @param bool $public Public.
		 */
		return (bool) apply_filters( 'releaso_rest_public', true ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Manage access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_manage( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return current_user_can( Plugin::capability() ) || ( $id && current_user_can( 'edit_post', $id ) );
	}

	/**
	 * GET /products.
	 *
	 * @return WP_REST_Response
	 */
	public function list_products() {
		$out = array();
		foreach ( $this->products->all() as $product ) {
			$releases = $this->changelog->releases( $product );
			$latest   = $releases->latest();
			$out[]    = array_merge(
				$product->to_public_array(),
				array(
					'latest'   => $latest ? $latest->version : null,
					'released' => $latest && $latest->date ? $latest->date : null,
					'releases' => count( $releases ),
				)
			);
		}
		return $this->cached( new WP_REST_Response( $out ) );
	}

	/**
	 * GET /products/{slug}/releases.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_releases( WP_REST_Request $request ) {
		$product = $this->products->find_by_slug( (string) $request['product'] );
		if ( ! $product ) {
			return new WP_Error( 'releaso_not_found', __( 'Product not found.', 'releaso' ), array( 'status' => 404 ) );
		}
		$types    = array_filter( array_map( 'sanitize_key', explode( ',', (string) $request['types'] ) ) );
		$releases = $this->changelog->releases( $product )->only_types( $types )->slice( (int) $request['limit'] );

		$rows = array_map(
			static function ( $row ) {
				unset( $row['origin'] );
				return $row;
			},
			$releases->to_array()
		);

		return $this->cached(
			new WP_REST_Response(
				array(
					'product'  => $product->to_public_array(),
					'releases' => $rows,
				)
			)
		);
	}

	/**
	 * POST /products/{id}/sync.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync( WP_REST_Request $request ) {
		$product = $this->product( $request );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$result = $this->changelog->sync( $product, (bool) $request['force'] );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 502 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'state' => $this->public_state( $this->changelog->state( $product ) ) ) );
	}

	/**
	 * POST /products/{id}/import.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$product = $this->product( $request );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$report = ( new Importer( $this->releases, $this->settings ) )->import(
			$product->id,
			(string) $request['content'],
			array(
				'format'    => (string) $request['format'],
				'overwrite' => (bool) $request['overwrite'],
				'status'    => (string) $request['status'],
				'dry_run'   => (bool) $request['dry_run'],
			)
		);
		if ( is_wp_error( $report ) ) {
			$report->add_data( array( 'status' => 422 ) );
			return $report;
		}
		$report['releases'] = count( $report['releases'] );
		return new WP_REST_Response( $report );
	}

	/**
	 * The product of a request by numeric ID.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return Product|WP_Error
	 */
	private function product( WP_REST_Request $request ) {
		$product = $this->products->find( (int) $request['id'] );
		return $product ? $product : new WP_Error( 'releaso_not_found', __( 'Product not found.', 'releaso' ), array( 'status' => 404 ) );
	}

	/**
	 * Sync state without validators.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private function public_state( array $state ) {
		return array_intersect_key( $state, array_flip( array( 'checked_at', 'synced_at', 'changed', 'error', 'count', 'latest', 'format' ) ) );
	}

	/**
	 * Lets browsers and CDNs cache public reads for five minutes.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private function cached( WP_REST_Response $response ) {
		if ( ! is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'public, max-age=300' );
		}
		return $response;
	}
}
