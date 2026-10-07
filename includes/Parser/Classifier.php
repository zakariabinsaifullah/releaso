<?php
/**
 * Works out a change's type and tag from its wording.
 *
 * "Added: …", "Fixed (Pro): …", "[Fixed] …", "FIX - …", "feat(blocks): …", "**Improved:** …" use the
 * prefix. Without one, the wording decides ("… is fixed" is a fix, "Added …" is new), and the
 * section the line sits in ("### Fixed") is the fallback.
 *
 * Pure PHP: no WordPress functions, so it can be unit-tested on its own.
 *
 * @package Releaso
 */

namespace Releaso\Parser;

use Releaso\Model\Change;

/**
 * Change classifier.
 */
class Classifier {

	/**
	 * The built-in type keys and the words that mark them.
	 */
	const DEFAULT_ALIASES = array(
		'new'      => array( 'new', 'added', 'add', 'adds', 'feature', 'features', 'feat', 'introduced', 'introduce' ),
		'improved' => array( 'improved', 'improvement', 'improvements', 'improve', 'enhanced', 'enhancement', 'enhancements', 'updated', 'update', 'updates', 'changed', 'change', 'changes', 'tweak', 'tweaked', 'tweaks', 'optimized', 'optimised', 'perf', 'performance', 'refactor', 'refactored', 'compatibility', 'compat', 'dev', 'docs', 'chore', 'style', 'i18n', 'l10n', 'misc' ),
		'fixed'    => array( 'fixed', 'fix', 'fixes', 'bugfix', 'bugfixes', 'bug', 'bugs', 'hotfix', 'patch', 'patched' ),
		'removed'  => array( 'removed', 'remove', 'deprecated', 'deprecate', 'dropped', 'drop', 'deleted' ),
		'security' => array( 'security', 'sec', 'vulnerability', 'cve' ),
	);

	/**
	 * Type key => alias words.
	 *
	 * @var array<string,string[]>
	 */
	private $aliases;

	/**
	 * The type used when nothing else says.
	 *
	 * @var string
	 */
	private $fallback;

	/**
	 * Constructor.
	 *
	 * @param array<string,string[]>|null $aliases  Type key => words; null for the defaults.
	 * @param string                      $fallback Fallback type.
	 */
	public function __construct( $aliases = null, $fallback = 'improved' ) {
		$this->aliases  = is_array( $aliases ) ? $aliases : self::DEFAULT_ALIASES;
		$this->fallback = isset( $this->aliases[ $fallback ] ) ? $fallback : (string) array_key_first( $this->aliases );
	}

	/**
	 * Known type keys.
	 *
	 * @return string[]
	 */
	public function types() {
		return array_keys( $this->aliases );
	}

	/**
	 * The type a single word or heading stands for ("Added", "Bug fixes", "🐛 Fixes"), or ''.
	 *
	 * @param string $word Word or short heading.
	 * @return string
	 */
	public function type_for( $word ) {
		$word = strtolower( trim( preg_replace( '/[^\p{L}\p{N}\s-]+/u', ' ', (string) $word ) ) );
		if ( '' === $word ) {
			return '';
		}
		foreach ( $this->aliases as $type => $words ) {
			if ( in_array( $word, $words, true ) ) {
				return $type;
			}
		}
		// "Bug fixes", "New features", "Performance improvements": try each word, the last one first.
		// Generic words ("What's changed", "Other updates") say nothing about the type.
		$generic = array( 'change', 'changes', 'changed', 'update', 'updates', 'updated', 'misc' );
		$parts   = array_reverse( preg_split( '/[\s-]+/', $word ) );
		if ( count( $parts ) > 1 && count( $parts ) <= 4 ) {
			foreach ( array_diff( $parts, $generic ) as $part ) {
				foreach ( $this->aliases as $type => $words ) {
					if ( in_array( $part, $words, true ) ) {
						return $type;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Classifies one change line.
	 *
	 * @param string $text         Line text without the list marker.
	 * @param string $section_type Type of the section the line is in, '' when none.
	 * @return Change
	 */
	public function classify( $text, $section_type = '' ) {
		$text = trim( (string) $text );
		$tag  = '';
		$type = '';

		// Leading tag in brackets that is not a type: "[Pro] Fixed: …".
		if ( preg_match( '/^\[([^\]]{1,24})\]\s*(.+)$/u', $text, $m ) && '' === $this->type_for( $m[1] ) ) {
			$tag  = trim( $m[1] );
			$text = trim( $m[2] );
		}

		// Conventional-changelog scope: "**blocks:** new carousel".
		if ( '' === $tag && preg_match( '/^\*\*([^*:]{1,24}):\*\*\s*(.+)$/u', $text, $m ) && '' === $this->type_for( $m[1] ) ) {
			$tag  = trim( $m[1] );
			$text = trim( $m[2] );
		}

		// "Added:", "Added (Pro):", "**Fixed:**", "[Fixed]", "FIX -", "feat(blocks):", "Fixed —".
		$prefix = '/^(?:\*\*|__)?\[?([\p{L}]+)(?:\s*\(([^)]{1,40})\))?\]?(?:\*\*|__)?(?:\s*:|\s+[-–—]|(?<=\])\s)\s*(?:\*\*|__)?\s*(.+)$/u';
		if ( preg_match( $prefix, $text, $m ) ) {
			$found = $this->type_for( $m[1] );
			if ( '' !== $found ) {
				$type = $found;
				$text = trim( $m[3] );
				if ( isset( $m[2] ) && '' !== trim( $m[2] ) && '' === $tag ) {
					$tag = trim( $m[2] );
				}
			}
		}

		if ( '' === $type && '' !== $section_type ) {
			$type = $section_type;
		}

		if ( '' === $type ) {
			$type = $this->from_wording( $text );
		}

		return new Change( $type, $text, $tag );
	}

	/**
	 * Reads the type from the wording of a line without a prefix.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function from_wording( $text ) {
		$lower = strtolower( $text );
		$rules = array(
			'security' => '/\bsecurity\b|\bvulnerab|\bxss\b|\bcsrf\b|\bsql injection\b|\bcve-\d/',
			'fixed'    => '/\bfix(ed|es|ing)?\b|\bbug\b|\bissue\b|\bresolved?\b|\berror\b|\bwarning\b|\bcrash/',
			'removed'  => '/\bremoved?\b|\bdeprecat|\bdropped\b/',
			'new'      => '/\b(is|are|was|were) added\b|^add(ed|s)?\b|\bnew\b|\bintroduc|\bnow supports?\b|\b(initial|first) (public )?release\b/',
		);
		foreach ( $rules as $type => $pattern ) {
			if ( isset( $this->aliases[ $type ] ) && preg_match( $pattern, $lower ) ) {
				return $type;
			}
		}
		return $this->fallback;
	}
}
