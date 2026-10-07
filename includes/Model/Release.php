<?php
/**
 * A released version and its changes.
 *
 * @package Releaso
 */

namespace Releaso\Model;

/**
 * Release value object.
 */
final class Release {

	const ORIGIN_SOURCE = 'source';
	const ORIGIN_MANUAL = 'manual';

	/**
	 * Version without a leading "v", e.g. "1.6.0".
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Release date as Y-m-d, or '' when unknown.
	 *
	 * @var string
	 */
	public $date;

	/**
	 * Optional headline, e.g. "The Panorama release".
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Optional notes shown above the changes (inline Markdown, paragraphs split on blank lines).
	 *
	 * @var string
	 */
	public $notes;

	/**
	 * Optional link to the full release (GitHub release page, blog post).
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Where it came from: ORIGIN_SOURCE (synced) or ORIGIN_MANUAL (written in the admin).
	 *
	 * @var string
	 */
	public $origin;

	/**
	 * Changes.
	 *
	 * @var Change[]
	 */
	public $changes = array();

	/**
	 * Constructor.
	 *
	 * @param string   $version Version.
	 * @param string   $date    Y-m-d date.
	 * @param Change[] $changes Changes.
	 */
	public function __construct( $version, $date = '', array $changes = array() ) {
		$this->version = self::normalize_version( $version );
		$this->date    = (string) $date;
		$this->changes = $changes;
		$this->title   = '';
		$this->notes   = '';
		$this->url     = '';
		$this->origin  = self::ORIGIN_SOURCE;
	}

	/**
	 * "v1.6.0 " → "1.6.0".
	 *
	 * @param string $version Raw version.
	 * @return string
	 */
	public static function normalize_version( $version ) {
		$version = trim( (string) $version );
		return preg_replace( '/^(?:v(?:ersion)?\.?\s*)/i', '', $version );
	}

	/**
	 * Adds a change.
	 *
	 * @param Change $change Change.
	 * @return void
	 */
	public function add( Change $change ) {
		if ( '' !== $change->text ) {
			$this->changes[] = $change;
		}
	}

	/**
	 * Whether there is anything to show.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return ! $this->changes && '' === trim( $this->notes );
	}

	/**
	 * Count of changes per type.
	 *
	 * @return array<string,int>
	 */
	public function counts() {
		$counts = array();
		foreach ( $this->changes as $change ) {
			$counts[ $change->type ] = ( $counts[ $change->type ] ?? 0 ) + 1;
		}
		return $counts;
	}

	/**
	 * From a stored array.
	 *
	 * @param array $data Data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		$release         = new self( $data['version'] ?? '', $data['date'] ?? '' );
		$release->title  = (string) ( $data['title'] ?? '' );
		$release->notes  = (string) ( $data['notes'] ?? '' );
		$release->url    = (string) ( $data['url'] ?? '' );
		$release->origin = (string) ( $data['origin'] ?? self::ORIGIN_SOURCE );
		foreach ( (array) ( $data['changes'] ?? array() ) as $change ) {
			if ( is_array( $change ) ) {
				$release->add( Change::from_array( $change ) );
			}
		}
		return $release;
	}

	/**
	 * To a storable / JSON-ready array (the Releaso JSON format).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'version' => $this->version,
			'date'    => $this->date,
			'title'   => $this->title,
			'notes'   => $this->notes,
			'url'     => $this->url,
			'origin'  => $this->origin,
			'changes' => array_map(
				static function ( Change $change ) {
					return $change->to_array();
				},
				$this->changes
			),
		);
	}
}
