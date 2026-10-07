<?php
/**
 * WP-CLI: wp releaso …
 *
 * @package Releaso
 */

namespace Releaso\Cli;

use Releaso\Data\Exporter;
use Releaso\Data\Importer;
use Releaso\Model\Product;
use Releaso\Plugin;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manage Releaso changelogs.
 */
class Command {

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
	 * Lists products and their sync state.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp releaso list
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		$rows = array();
		foreach ( $this->plugin->products->all() as $product ) {
			$state    = $this->plugin->changelog->state( $product );
			$releases = $this->plugin->changelog->releases( $product );
			$rows[]   = array(
				'id'       => $product->id,
				'slug'     => $product->slug,
				'name'     => $product->name,
				'source'   => $product->source,
				'latest'   => $releases->latest() ? $releases->latest()->version : '',
				'releases' => count( $releases ),
				'checked'  => ! empty( $state['checked_at'] ) ? human_time_diff( (int) $state['checked_at'] ) . ' ago' : 'never',
				'error'    => $state['error'] ?? '',
			);
		}
		Utils\format_items( Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array( 'id', 'slug', 'name', 'source', 'latest', 'releases', 'checked', 'error' ) );
	}

	/**
	 * Syncs products from their sources.
	 *
	 * ## OPTIONS
	 *
	 * [<product>...]
	 * : Product slugs or IDs. Default: every product.
	 *
	 * [--due]
	 * : Only products whose sync interval has passed (what the cron does).
	 *
	 * ## EXAMPLES
	 *
	 *     wp releaso sync
	 *     wp releaso sync slider-blocks slider-blocks-pro
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function sync( $args, $assoc_args ) {
		$products = $this->products( $args );
		$due      = (bool) Utils\get_flag_value( $assoc_args, 'due', false );
		$failed   = 0;

		foreach ( $products as $product ) {
			if ( $due && ! $this->plugin->changelog->is_due( $product ) ) {
				WP_CLI::log( sprintf( '%s: not due', $product->slug ) );
				continue;
			}
			$result = $this->plugin->changelog->sync( $product, ! $due );
			if ( is_wp_error( $result ) ) {
				++$failed;
				WP_CLI::warning( sprintf( '%s: %s', $product->slug, $result->get_error_message() ) );
				continue;
			}
			$state = $this->plugin->changelog->state( $product );
			WP_CLI::log( sprintf( '%s: %d releases, latest %s (%s)', $product->slug, (int) ( $state['count'] ?? 0 ), $state['latest'] ?? '–', $state['format'] ?? '–' ) );
		}

		$failed ? WP_CLI::error( sprintf( '%d product(s) failed.', $failed ) ) : WP_CLI::success( 'Done.' );
	}

	/**
	 * Imports a changelog file into a product as editable releases.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a readme.txt, CHANGELOG.md or JSON file. Use - for STDIN.
	 *
	 * --product=<product>
	 * : Product slug or ID.
	 *
	 * [--format=<format>]
	 * : auto, readme, markdown or json.
	 * ---
	 * default: auto
	 * ---
	 *
	 * [--overwrite]
	 * : Replace releases that already exist for the same version.
	 *
	 * [--draft]
	 * : Import as drafts.
	 *
	 * [--dry-run]
	 * : Show what would be imported without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp releaso import CHANGELOG.md --product=slider-blocks-pro --dry-run
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		$product = $this->products( array( $assoc_args['product'] ) )[0];
		$file    = $args[0];
		$content = '-' === $file ? stream_get_contents( STDIN ) : ( is_readable( $file ) ? file_get_contents( $file ) : false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			WP_CLI::error( "Cannot read {$file}." );
		}

		$dry    = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$report = ( new Importer( $this->plugin->releases, $this->plugin->settings ) )->import(
			$product->id,
			(string) $content,
			array(
				'format'    => Utils\get_flag_value( $assoc_args, 'format', 'auto' ),
				'name'      => '-' === $file ? '' : basename( $file ),
				'overwrite' => (bool) Utils\get_flag_value( $assoc_args, 'overwrite', false ),
				'status'    => Utils\get_flag_value( $assoc_args, 'draft', false ) ? 'draft' : 'publish',
				'dry_run'   => $dry,
			)
		);
		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
		}

		foreach ( array( 'created', 'updated', 'skipped' ) as $key ) {
			if ( $report[ $key ] ) {
				WP_CLI::log( sprintf( '%s%s: %s', $dry ? 'would be ' : '', $key, implode( ', ', $report[ $key ] ) ) );
			}
		}
		foreach ( $report['failed'] as $version => $message ) {
			WP_CLI::warning( "{$version}: {$message}" );
		}
		WP_CLI::success( sprintf( '%d releases read.', count( $report['releases'] ) ) );
	}

	/**
	 * Exports changelogs.
	 *
	 * ## OPTIONS
	 *
	 * [<product>...]
	 * : Product slugs or IDs. Default: every product.
	 *
	 * [--format=<format>]
	 * : json, markdown or readme.
	 * ---
	 * default: json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp releaso export slider-blocks --format=markdown > CHANGELOG.md
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'json' );
		if ( ! in_array( $format, Exporter::FORMATS, true ) ) {
			WP_CLI::error( 'Format must be json, markdown or readme.' );
		}
		$items = array();
		foreach ( $this->products( $args ) as $product ) {
			$items[] = array( $product, $this->plugin->changelog->releases( $product ) );
		}
		WP_CLI::line( ( new Exporter() )->export( $items, $format ) );
	}

	/**
	 * Clears the cached changelogs.
	 *
	 * @return void
	 */
	public function flush() {
		$this->plugin->changelog->flush();
		WP_CLI::success( 'Cache flushed.' );
	}

	/**
	 * Products from slugs/IDs, or all.
	 *
	 * @param string[] $refs Slugs or IDs.
	 * @return Product[]
	 */
	private function products( array $refs ) {
		if ( ! $refs ) {
			$all = $this->plugin->products->all();
			if ( ! $all ) {
				WP_CLI::error( 'No published products.' );
			}
			return $all;
		}
		$out = array();
		foreach ( $refs as $ref ) {
			$product = is_numeric( $ref ) ? $this->plugin->products->find( (int) $ref ) : $this->plugin->products->find_by_slug( $ref );
			if ( ! $product ) {
				WP_CLI::error( "Product not found: {$ref}" );
			}
			$out[] = $product;
		}
		return $out;
	}
}
