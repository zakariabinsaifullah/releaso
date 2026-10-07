<?php
/**
 * JSON changelogs. Accepts the Releaso export format and the common shapes around it:
 *
 *   { "releases": [ { "version": "1.2.0", "date": "2026-09-29", "changes": [ { "type": "fixed", "text": "…", "tag": "Pro" } ] } ] }
 *   [ { "version": "1.2.0", "changes": { "added": [ "…" ], "fixed": [ "…" ] } } ]
 *   { "1.2.0": { "date": "…", "changes": [ "Fixed: …" ] } }
 *   GitHub's /releases API response (tag_name, published_at, body, html_url)
 *
 * @package Releaso
 */

namespace Releaso\Parser;

use Releaso\Model\Change;
use Releaso\Model\Release;
use Releaso\Model\ReleaseCollection;

/**
 * JSON parser.
 */
class JsonParser implements ParserInterface {

	/**
	 * Classifier.
	 *
	 * @var Classifier
	 */
	private $classifier;

	/**
	 * For release bodies written in Markdown.
	 *
	 * @var MarkdownParser
	 */
	private $markdown;

	/**
	 * Constructor.
	 *
	 * @param Classifier|null $classifier Classifier.
	 */
	public function __construct( $classifier = null ) {
		$this->classifier = $classifier ? $classifier : new Classifier();
		$this->markdown   = new MarkdownParser( $this->classifier );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function format() {
		return 'json';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $content JSON.
	 * @return ReleaseCollection
	 * @throws ParseException On invalid JSON.
	 */
	public function parse( $content ) {
		$data = json_decode( AbstractLineParser::normalize( $content ), true );
		if ( ! is_array( $data ) ) {
			throw new ParseException( 'Invalid JSON: ' . json_last_error_msg() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- never printed raw; admin output escapes it.
		}

		foreach ( array( 'releases', 'versions', 'changelog', 'data' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$data = $data[ $key ];
				break;
			}
		}

		$out = new ReleaseCollection();
		foreach ( $data as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// A map keyed by version: { "1.2.0": { … } }.
			if ( is_string( $key ) && ! isset( $row['version'] ) && preg_match( '/^v?\d/', $key ) ) {
				$row['version'] = $key;
			}
			$release = $this->release( $row );
			if ( $release ) {
				$out->add( $release );
			}
		}
		return $out;
	}

	/**
	 * One release row.
	 *
	 * @param array $row Row.
	 * @return Release|null
	 */
	private function release( array $row ) {
		$version = self::first( $row, array( 'version', 'tag_name', 'tag', 'name' ) );
		if ( ! is_scalar( $version ) || ! preg_match( '/\d/', (string) $version ) ) {
			return null;
		}
		if ( ! empty( $row['draft'] ) ) {
			return null;
		}

		$release        = new Release( (string) $version );
		$date           = self::first( $row, array( 'date', 'released', 'release_date', 'released_at', 'published_at', 'created_at' ) );
		$release->date  = is_scalar( $date ) ? AbstractLineParser::parse_date( (string) $date ) : '';
		$title          = self::first( $row, array( 'title', 'headline' ) );
		$release->title = is_scalar( $title ) ? (string) $title : '';
		$url            = self::first( $row, array( 'url', 'html_url', 'link' ) );
		$release->url   = is_scalar( $url ) ? (string) $url : '';

		// GitHub-style: the name is a title when it is not just the tag.
		if ( '' === $release->title && isset( $row['tag_name'], $row['name'] ) && is_string( $row['name'] ) && $row['name'] !== $row['tag_name'] && ltrim( $row['name'], 'vV' ) !== $release->version ) {
			$release->title = $row['name'];
		}

		$changes = self::first( $row, array( 'changes', 'items', 'entries', 'changelog' ) );
		if ( is_array( $changes ) ) {
			$this->changes( $release, $changes );
		}

		$notes = self::first( $row, array( 'notes', 'description', 'body', 'summary' ) );
		if ( is_string( $notes ) && '' !== trim( $notes ) ) {
			if ( $release->changes ) {
				$release->notes = trim( $notes );
			} else {
				// A Markdown body with the changes in it (GitHub releases).
				$this->markdown->fill( $release, $notes );
			}
		}

		return $release;
	}

	/**
	 * Adds the changes from a list or a type-keyed map.
	 *
	 * @param Release $release Release.
	 * @param array   $changes Changes.
	 * @return void
	 */
	private function changes( Release $release, array $changes ) {
		$is_map = array_keys( $changes ) !== range( 0, count( $changes ) - 1 );

		foreach ( $changes as $key => $value ) {
			$section = $is_map ? $this->classifier->type_for( (string) $key ) : '';
			$values  = $is_map && is_array( $value ) ? $value : array( $value );

			foreach ( $values as $entry ) {
				if ( is_string( $entry ) ) {
					$release->add( $this->classifier->classify( $entry, $section ) );
					continue;
				}
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$text = self::first( $entry, array( 'text', 'description', 'message', 'title', 'change' ) );
				if ( ! is_string( $text ) ) {
					continue;
				}
				$type = isset( $entry['type'] ) ? $this->classifier->type_for( (string) $entry['type'] ) : '';
				if ( '' === $type && isset( $entry['type'] ) && in_array( $entry['type'], $this->classifier->types(), true ) ) {
					$type = $entry['type'];
				}
				if ( '' === $type ) {
					$change = $this->classifier->classify( $text, $section );
				} else {
					$change = new Change( $type, $text );
				}
				if ( isset( $entry['tag'] ) && is_string( $entry['tag'] ) ) {
					$change->tag = trim( $entry['tag'] );
				}
				$release->add( $change );
			}
		}
	}

	/**
	 * The first key present.
	 *
	 * @param array    $row  Row.
	 * @param string[] $keys Keys.
	 * @return mixed|null
	 */
	private static function first( array $row, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $row[ $key ] ) && '' !== $row[ $key ] ) {
				return $row[ $key ];
			}
		}
		return null;
	}
}
