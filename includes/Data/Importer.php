<?php
/**
 * Imports a changelog file (readme.txt, Markdown or JSON) as releases written in the admin,
 * so they can be edited one by one afterwards. Used by Tools, the REST API and WP-CLI.
 *
 * @package Releaso
 */

namespace Releaso\Data;

use Releaso\Model\ReleaseCollection;
use Releaso\Parser\ParseException;
use Releaso\Parser\ParserFactory;
use Releaso\Support\ChangeTypes;
use Releaso\Support\Settings;
use WP_Error;

/**
 * Changelog importer.
 */
class Importer {

	const MAX_BYTES = 2097152;

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
	 * @param ReleaseRepository $releases Releases.
	 * @param Settings          $settings Settings.
	 */
	public function __construct( ReleaseRepository $releases, Settings $settings ) {
		$this->releases = $releases;
		$this->settings = $settings;
	}

	/**
	 * Parses content without saving anything.
	 *
	 * @param string $content Content.
	 * @param string $format  auto, readme, markdown or json.
	 * @param string $name    File name hint.
	 * @return ReleaseCollection|WP_Error
	 */
	public function parse( $content, $format = 'auto', $name = '' ) {
		if ( strlen( (string) $content ) > self::MAX_BYTES ) {
			return new WP_Error( 'releaso_too_big', __( 'The file is larger than 2 MB.', 'releaso' ) );
		}
		$factory = new ParserFactory( ChangeTypes::classifier(), (bool) $this->settings->get( 'include_unreleased' ) );
		try {
			$releases = $factory->parse( (string) $content, $format, $name );
		} catch ( ParseException $e ) {
			return new WP_Error( 'releaso_parse', $e->getMessage() );
		}
		if ( ! count( $releases ) ) {
			return new WP_Error( 'releaso_empty', __( 'No releases were found. Check the version headings, e.g. "= 1.2.0 =" or "## 1.2.0".', 'releaso' ) );
		}
		return $releases->sorted();
	}

	/**
	 * Imports content into a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $content    Content.
	 * @param array  $options    format, name, overwrite (bool), status (publish|draft), dry_run (bool).
	 * @return array|WP_Error Report: created, updated, skipped (versions), failed (version => message), releases.
	 */
	public function import( $product_id, $content, array $options = array() ) {
		$options  = array_merge(
			array(
				'format'    => 'auto',
				'name'      => '',
				'overwrite' => false,
				'status'    => 'publish',
				'dry_run'   => false,
			),
			$options
		);
		$releases = $this->parse( $content, $options['format'], $options['name'] );
		if ( is_wp_error( $releases ) ) {
			return $releases;
		}

		$report = array(
			'created'  => array(),
			'updated'  => array(),
			'skipped'  => array(),
			'failed'   => array(),
			'releases' => $releases,
		);

		foreach ( $releases as $release ) {
			if ( $options['dry_run'] ) {
				$exists = $this->releases->find_post( $product_id, $release->version );
				$report[ $exists ? ( $options['overwrite'] ? 'updated' : 'skipped' ) : 'created' ][] = $release->version;
				continue;
			}
			$result = $this->releases->save( $product_id, $release, (bool) $options['overwrite'], 'draft' === $options['status'] ? 'draft' : 'publish' );
			if ( in_array( $result, array( 'created', 'updated', 'skipped' ), true ) ) {
				$report[ $result ][] = $release->version;
			} else {
				$report['failed'][ $release->version ] = $result;
			}
		}

		/**
		 * Fires after a changelog import.
		 *
		 * @param array $report     Report.
		 * @param int   $product_id Product ID.
		 * @param array $options    Options.
		 */
		do_action( 'releaso_imported', $report, $product_id, $options );

		return $report;
	}
}
