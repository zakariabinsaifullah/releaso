<?php
/**
 * Inline Markdown to safe HTML for change text and release notes.
 *
 * Supports `code`, **bold**, *italic*, [links](https://…), bare URLs and #123 / @user on GitHub
 * products. Everything is escaped first, so nothing from a remote changelog can inject markup.
 *
 * @package Releaso
 */

namespace Releaso\Support;

/**
 * Text formatter.
 */
class Formatter {

	/**
	 * One line of inline Markdown to HTML.
	 *
	 * @param string $text Text.
	 * @return string Safe HTML.
	 */
	public static function inline( $text ) {
		$codes = array();

		// Code spans first, so nothing inside them is formatted.
		$text = preg_replace_callback(
			'/`([^`]+)`/',
			static function ( $m ) use ( &$codes ) {
				$codes[] = '<code>' . esc_html( $m[1] ) . '</code>';
				return "\x1A" . ( count( $codes ) - 1 ) . "\x1A";
			},
			(string) $text
		);

		$links = array();
		$text  = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/',
			static function ( $m ) use ( &$links ) {
				$links[] = '<a href="' . esc_url( $m[2] ) . '" rel="nofollow noopener">' . self::emphasis( esc_html( $m[1] ) ) . '</a>';
				return "\x1B" . ( count( $links ) - 1 ) . "\x1B";
			},
			$text
		);

		$html = self::emphasis( esc_html( $text ) );

		// Bare URLs.
		$html = preg_replace_callback(
			'/(?<![="\'>])\bhttps?:\/\/[^\s<]+[^\s<.,;:!?)\]]/',
			static function ( $m ) {
				$url   = html_entity_decode( $m[0], ENT_QUOTES );
				$label = preg_replace( '#^https?://(www\.)?#', '', $url );
				if ( preg_match( '#github\.com/[^/]+/[^/]+/(?:pull|issues)/(\d+)#', $url, $gh ) ) {
					$label = '#' . $gh[1];
				}
				return '<a href="' . esc_url( $url ) . '" rel="nofollow noopener">' . esc_html( $label ) . '</a>';
			},
			$html
		);

		$html = preg_replace_callback( "/\x1B(\d+)\x1B/", static fn( $m ) => $links[ (int) $m[1] ] ?? '', $html );
		$html = preg_replace_callback( "/\x1A(\d+)\x1A/", static fn( $m ) => $codes[ (int) $m[1] ] ?? '', $html );

		/**
		 * Filters a formatted line of change text.
		 *
		 * @param string $html Safe HTML.
		 * @param string $text Original text.
		 */
		return apply_filters( 'releaso_format_inline', $html, $text );
	}

	/**
	 * Notes (paragraphs split on blank lines) to HTML.
	 *
	 * @param string $notes Notes.
	 * @return string
	 */
	public static function notes( $notes ) {
		$out = '';
		foreach ( preg_split( "/\n\s*\n/", trim( (string) $notes ) ) as $para ) {
			if ( '' !== trim( $para ) ) {
				$out .= '<p>' . self::inline( preg_replace( '/\s+/', ' ', $para ) ) . '</p>';
			}
		}
		return $out;
	}

	/**
	 * **bold**, __bold__, *italic*, _italic_ on already escaped text.
	 *
	 * @param string $html Escaped text.
	 * @return string
	 */
	private static function emphasis( $html ) {
		$html = preg_replace( '/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/', '<strong>$2</strong>', $html );
		return preg_replace( '/(?<![\w*])([*_])(?=\S)([^*_]+?)(?<=\S)\1(?![\w*])/', '<em>$2</em>', $html );
	}
}
