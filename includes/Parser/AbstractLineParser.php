<?php
/**
 * Shared engine for line-based changelogs (readme.txt and Markdown).
 *
 * Inside a release it understands:
 *  - list items ("* …", "- …", "1. …"), with wrapped lines joined to the item before;
 *  - section headings that set the type of the items below them ("### Fixed", "**Added**", "Fixed:");
 *  - other text, which becomes the release notes (or items, for formats where that is the habit).
 *
 * Subclasses only decide what a version heading looks like.
 *
 * @package Releaso
 */

namespace Releaso\Parser;

use Releaso\Model\Release;
use Releaso\Model\ReleaseCollection;

/**
 * Line-based parser.
 */
abstract class AbstractLineParser implements ParserInterface {

	/**
	 * Version token: 1.2, 1.2.0, 2.0.0-beta.1, 1.0.0+build.5.
	 */
	const VERSION = 'v?(\d+(?:\.\d+)*(?:[-+][0-9A-Za-z][0-9A-Za-z.\-+]*)?)';

	/**
	 * Classifier.
	 *
	 * @var Classifier
	 */
	protected $classifier;

	/**
	 * Keep "Unreleased" sections.
	 *
	 * @var bool
	 */
	protected $include_unreleased = false;

	/**
	 * Whether text lines that are not list items count as changes (readme habit) or as notes.
	 *
	 * @var bool
	 */
	protected $plain_lines_are_items = false;

	/**
	 * Constructor.
	 *
	 * @param Classifier|null $classifier         Classifier.
	 * @param bool            $include_unreleased Keep "Unreleased" sections.
	 */
	public function __construct( $classifier = null, $include_unreleased = false ) {
		$this->classifier         = $classifier ? $classifier : new Classifier();
		$this->include_unreleased = (bool) $include_unreleased;
	}

	/**
	 * Matches a version heading.
	 *
	 * @param string $line Trimmed line.
	 * @return array|null [ version, rest-of-heading ] or null. Version 'Unreleased' marks an unreleased section.
	 */
	abstract protected function match_version( $line );

	/**
	 * Narrows the content to the changelog part of a longer document.
	 *
	 * @param string $content Content with \n line endings.
	 * @return string
	 */
	protected function extract( $content ) {
		return $content;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $content Raw content.
	 * @return ReleaseCollection
	 */
	public function parse( $content ) {
		$content = $this->extract( self::normalize( $content ) );
		$out     = new ReleaseCollection();
		$current = null;
		$lines   = array();
		$skip    = false;

		foreach ( explode( "\n", $content ) as $line ) {
			$heading = $this->match_version( trim( $line ) );
			if ( null !== $heading ) {
				if ( $current ) {
					$this->fill( $current, $lines );
					$out->add( $current );
				}
				$lines   = array();
				$current = null;
				$skip    = 'unreleased' === strtolower( $heading[0] ) && ! $this->include_unreleased;
				if ( ! $skip ) {
					$current = new Release( $heading[0] );
					$this->apply_heading_rest( $current, $heading[1] );
				}
				continue;
			}
			if ( $current && ! $skip ) {
				$lines[] = $line;
			}
		}

		if ( $current ) {
			$this->fill( $current, $lines );
			$out->add( $current );
		}

		return $out;
	}

	/**
	 * Fills a release from the body lines below its heading. Public so sources such as GitHub
	 * releases (where the version comes from elsewhere) can reuse the body rules.
	 *
	 * @param Release         $release Release to fill.
	 * @param string[]|string $lines   Body lines or text.
	 * @return Release
	 */
	public function fill( Release $release, $lines ) {
		if ( is_string( $lines ) ) {
			$lines = explode( "\n", self::normalize( $lines ) );
		}

		$section  = '';
		$notes    = array();
		$para     = '';
		$last     = null; // index of the item a wrapped line belongs to.
		$in_fence = false;

		foreach ( $lines as $raw ) {
			$line = trim( $raw );

			if ( 0 === strpos( $line, '```' ) || 0 === strpos( $line, '~~~' ) ) {
				$in_fence = ! $in_fence;
				continue;
			}
			if ( $in_fence || '' === $line || $this->is_noise( $line ) ) {
				if ( '' === $line ) {
					$last = null;
					if ( '' !== $para ) {
						$notes[] = $para;
						$para    = '';
					}
				}
				continue;
			}

			$type = $this->section_type( $line );
			if ( null !== $type ) {
				$section = $type;
				$last    = null;
				continue;
			}

			if ( preg_match( '/^(?:[*\-+•]|\d+[.)])\s+(.*)$/u', $line, $m ) ) {
				$change = $this->classifier->classify( $this->clean( $m[1] ), $section );
				$release->add( $change );
				$last = '' !== $change->text ? count( $release->changes ) - 1 : null;
				continue;
			}

			// Markdown "lazy continuation": a line right under an item belongs to it.
			if ( null !== $last && isset( $release->changes[ $last ] ) ) {
				$release->changes[ $last ]->text .= ' ' . $this->clean( $line );
				continue;
			}

			if ( $this->plain_lines_are_items || '' !== $section ) {
				$release->add( $this->classifier->classify( $this->clean( $line ), $section ) );
				continue;
			}

			$para = '' === $para ? $this->clean( $line ) : $para . ' ' . $this->clean( $line );
		}

		if ( '' !== $para ) {
			$notes[] = $para;
		}
		if ( $notes ) {
			$release->notes = trim( $release->notes . "\n\n" . implode( "\n\n", $notes ) );
		}

		return $release;
	}

	/**
	 * Reads the date (and a title) from what follows the version in a heading.
	 *
	 * @param Release $release Release.
	 * @param string  $rest    Heading remainder, e.g. " - 2026-09-29" or "(September 29, 2026)".
	 * @return void
	 */
	protected function apply_heading_rest( Release $release, $rest ) {
		$rest = trim( (string) $rest, " \t-–—:|()[]" );
		if ( '' === $rest ) {
			return;
		}
		// A date somewhere in the remainder; whatever is left is a title.
		$patterns = array(
			'/\d{4}-\d{2}-\d{2}/',
			'/\d{4}\/\d{2}\/\d{2}/',
			'/\d{1,2}(?:st|nd|rd|th)?\s+\p{L}+,?\s+\d{4}/u',
			'/\p{L}+\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4}/u',
			'/\d{1,2}[.\/]\d{1,2}[.\/]\d{4}/',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $rest, $m ) ) {
				$date = self::parse_date( $m[0] );
				if ( '' !== $date ) {
					$release->date  = $date;
					$release->title = trim( str_replace( $m[0], '', $rest ), " \t-–—:|()[]" );
					return;
				}
			}
		}
		$release->title = $rest;
	}

	/**
	 * The type a section-heading line stands for, '' for a non-type heading, null if not a heading.
	 *
	 * @param string $line Trimmed line.
	 * @return string|null
	 */
	protected function section_type( $line ) {
		if ( preg_match( '/^#{2,6}\s+(.+?)\s*#*$/', $line, $m )
			|| preg_match( '/^(?:\*\*|__)([^*_]{2,40})(?:\*\*|__):?$/', $line, $m )
			|| preg_match( '/^([\p{L} ]{2,30}):$/u', $line, $m ) ) {
			// "### Fixed" sets the type below it; a non-type heading ("**WooCommerce**", "Notes:")
			// is a sub-heading that clears it, never a change of its own.
			return $this->classifier->type_for( $m[1] );
		}
		return null;
	}

	/**
	 * Lines to ignore: HTML comments, link reference definitions, horizontal rules.
	 *
	 * @param string $line Trimmed line.
	 * @return bool
	 */
	protected function is_noise( $line ) {
		return (bool) preg_match( '/^(?:<!--.*-->|\[[^\]]+\]:\s*\S+.*|[-*_]{3,}|<\/?details>|<\/?summary>.*)$/', $line );
	}

	/**
	 * Cleans item text: trailing two-space breaks, HTML tags.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected function clean( $text ) {
		$text = preg_replace( '/<\/?[a-z][^>]*>/i', '', (string) $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Line endings to \n, BOM removed.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function normalize( $content ) {
		$content = (string) $content;
		if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}
		return str_replace( array( "\r\n", "\r" ), "\n", $content );
	}

	/**
	 * A date string to Y-m-d, or '' when it is not a date.
	 *
	 * @param string $date Date text.
	 * @return string
	 */
	public static function parse_date( $date ) {
		$date = trim( preg_replace( '/(\d)(st|nd|rd|th)\b/', '$1', (string) $date ) );
		if ( '' === $date ) {
			return '';
		}
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $m ) ) {
			$date = "{$m[3]}-{$m[2]}-{$m[1]}"; // 29.09.2026 is day-first.
		}
		$time = strtotime( $date );
		if ( false === $time || $time < 631152000 ) { // before 1990: not a real release date.
			return '';
		}
		return gmdate( 'Y-m-d', $time );
	}
}
