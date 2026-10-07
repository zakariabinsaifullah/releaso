<?php
/**
 * Markdown changelogs: CHANGELOG.md in "Keep a Changelog" style, conventional-changelog output,
 * readme.md files and GitHub release bodies.
 *
 * Headings: "## [1.2.0] - 2026-09-29", "## 1.2.0 (2026-09-29)", "# v1.2.0", "#### 1.2.0 ####",
 * "## [1.2.0](https://…/compare/…) (2026-09-29)". "## [Unreleased]" is skipped by default.
 * Sections: "### Added", "### Bug Fixes", "**Fixed**".
 *
 * @package Releaso
 */

namespace Releaso\Parser;

/**
 * Markdown parser.
 */
class MarkdownParser extends AbstractLineParser {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function format() {
		return 'markdown';
	}

	/**
	 * When the document has a "Changelog" heading (a README.md), only what is under it.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	protected function extract( $content ) {
		if ( preg_match( '/^(#{1,3})\s*(?:change\s*log|release\s*notes|history)\s*#*\s*$/im', $content, $m, PREG_OFFSET_CAPTURE ) ) {
			$level = strlen( $m[1][0] );
			$rest  = substr( $content, $m[0][1] + strlen( $m[0][0] ) );
			// Stop at the next heading of the same or a higher level that is not a version.
			if ( preg_match( '/^#{1,' . $level . '}\s+(?!\[?v?\d)(?!\[?unreleased)[^\n]+$/im', $rest, $end, PREG_OFFSET_CAPTURE ) ) {
				$rest = substr( $rest, 0, $end[0][1] );
			}
			return $rest;
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
		if ( ! preg_match( '/^#{1,4}\s+(.+?)\s*#*$/', $line, $h ) ) {
			return null;
		}
		$text = $h[1];

		if ( preg_match( '/^\[?unreleased\]?/i', $text ) ) {
			return array( 'Unreleased', '' );
		}

		// "[1.2.0](url) (date)", "[1.2.0] - date", "Version 1.2.0 - date", "v1.2.0".
		if ( preg_match( '/^(?:version\s+|release\s+)?\[?' . self::VERSION . '\]?(?:\([^)\s]*:\/\/[^)]*\))?(.*)$/i', $text, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return null;
	}
}
