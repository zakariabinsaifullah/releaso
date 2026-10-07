<?php
/**
 * Picks the parser for a format, or detects the format from the content.
 *
 * @package Releaso
 */

namespace Releaso\Parser;

/**
 * Parser factory.
 */
class ParserFactory {

	const FORMATS = array( 'auto', 'readme', 'markdown', 'json' );

	/**
	 * Classifier shared by the parsers.
	 *
	 * @var Classifier
	 */
	private $classifier;

	/**
	 * Keep "Unreleased" sections.
	 *
	 * @var bool
	 */
	private $include_unreleased;

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
	 * The classifier.
	 *
	 * @return Classifier
	 */
	public function classifier() {
		return $this->classifier;
	}

	/**
	 * A parser for a format.
	 *
	 * @param string $format  readme, markdown, json or auto.
	 * @param string $content Content, used when the format is auto.
	 * @param string $name    File name or URL, a hint when the format is auto.
	 * @return ParserInterface
	 */
	public function make( $format, $content = '', $name = '' ) {
		if ( ! in_array( $format, self::FORMATS, true ) || 'auto' === $format ) {
			$format = self::detect( $content, $name );
		}
		switch ( $format ) {
			case 'json':
				return new JsonParser( $this->classifier );
			case 'markdown':
				return new MarkdownParser( $this->classifier, $this->include_unreleased );
			default:
				return new ReadmeParser( $this->classifier, $this->include_unreleased );
		}
	}

	/**
	 * Parses content in a format (auto-detected by default).
	 *
	 * @param string $content Content.
	 * @param string $format  Format.
	 * @param string $name    File name or URL hint.
	 * @return \Releaso\Model\ReleaseCollection
	 * @throws ParseException On unreadable content.
	 */
	public function parse( $content, $format = 'auto', $name = '' ) {
		return $this->make( $format, $content, $name )->parse( $content );
	}

	/**
	 * Detects the format of changelog content.
	 *
	 * @param string $content Content.
	 * @param string $name    File name or URL.
	 * @return string readme, markdown or json.
	 */
	public static function detect( $content, $name = '' ) {
		$trimmed = ltrim( AbstractLineParser::normalize( $content ) );
		$first   = substr( $trimmed, 0, 1 );

		if ( ( '{' === $first || '[' === $first ) && null !== json_decode( $trimmed ) ) {
			return 'json';
		}

		$ext = strtolower( pathinfo( self::path( $name ), PATHINFO_EXTENSION ) );
		if ( 'json' === $ext ) {
			return 'json';
		}

		if ( preg_match( '/^==\s*changelog\s*==/im', $trimmed ) || preg_match( '/^=+\s*v?\d+(?:\.\d+)*.*=+\s*$/m', $trimmed ) ) {
			return 'readme';
		}
		if ( preg_match( '/^#{1,4}\s+\[?v?\d/m', $trimmed ) || in_array( $ext, array( 'md', 'markdown' ), true ) ) {
			return 'markdown';
		}
		return 'txt' === $ext ? 'readme' : 'markdown';
	}

	/**
	 * The path part of a URL or file name.
	 *
	 * @param string $name URL or file name.
	 * @return string
	 */
	private static function path( $name ) {
		$path = parse_url( (string) $name, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return is_string( $path ) ? $path : (string) $name;
	}
}
