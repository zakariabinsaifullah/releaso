<?php
/**
 * WordPress readme.txt: the "== Changelog ==" section with "= 1.2.0 =" headings.
 *
 * Headings: "= 1.2.0 =", "= v1.2.0 - 2026-09-29 =", "= 1.2.0 (September 29, 2026) =",
 * "= Version 1.2.0 =", "= 1.2.0 – The Panorama release =".
 *
 * @package Releaso
 */

namespace Releaso\Parser;

/**
 * Parser for readme.txt.
 */
class ReadmeParser extends AbstractLineParser {

	/**
	 * Readme authors often write one change per line without a bullet.
	 *
	 * @var bool
	 */
	protected $plain_lines_are_items = true;

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function format() {
		return 'readme';
	}

	/**
	 * Only the "== Changelog ==" section, when the text is a whole readme.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	protected function extract( $content ) {
		if ( preg_match( '/^==\s*Changelog\s*==\s*$(.*?)(?=^==\s*[^=\n]+==\s*$|\z)/ims', $content, $m ) ) {
			return $m[1];
		}
		return $content;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $line Line.
	 * @return array|null
	 */
	protected function match_version( $line ) {
		if ( preg_match( '/^=+\s*(?:version\s*)?' . self::VERSION . '(.*?)\s*=+$/i', $line, $m ) ) {
			return array( $m[1], $m[2] );
		}
		if ( preg_match( '/^=+\s*\[?(unreleased)\]?.*=+$/i', $line, $m ) ) {
			return array( 'Unreleased', '' );
		}
		return null;
	}
}
