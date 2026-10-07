<?php
/**
 * Shared helpers for sources: parsing with the configured parser options and
 * fetching a URL with conditional requests.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Parser\ParseException;
use Releaso\Parser\ParserFactory;
use Releaso\Support\ChangeTypes;
use Releaso\Support\Http;
use Releaso\Support\Settings;
use WP_Error;

/**
 * Base source.
 */
abstract class AbstractSource implements SourceInterface {

	/**
	 * HTTP client.
	 *
	 * @var Http
	 */
	protected $http;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param Http     $http     HTTP client.
	 * @param Settings $settings Settings.
	 */
	public function __construct( Http $http, Settings $settings ) {
		$this->http     = $http;
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_remote() {
		return true;
	}

	/**
	 * Default sanitizer: every declared field as text (textareas keep their lines).
	 *
	 * @param array $config Raw settings.
	 * @return array
	 */
	public function sanitize( array $config ) {
		$out = array();
		foreach ( $this->fields() as $key => $field ) {
			$value = $config[ $key ] ?? '';
			switch ( $field['type'] ?? 'text' ) {
				case 'textarea':
					// Changelogs keep their Markdown; tags are dropped on output, never trusted.
					$out[ $key ] = is_string( $value ) ? str_replace( "\r\n", "\n", wp_check_invalid_utf8( $value ) ) : '';
					break;
				case 'url':
					$out[ $key ] = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
					break;
				case 'checkbox':
					$out[ $key ] = ! empty( $value );
					break;
				case 'select':
					$options     = array_keys( $field['options'] ?? array() );
					$out[ $key ] = in_array( $value, $options, true ) ? $value : ( $field['default'] ?? (string) reset( $options ) );
					break;
				default:
					$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $out;
	}

	/**
	 * The format select, shared by sources that read a file.
	 *
	 * @return array
	 */
	protected function format_field() {
		return array(
			'label'   => __( 'Format', 'releaso' ),
			'type'    => 'select',
			'default' => 'auto',
			'options' => array(
				'auto'     => __( 'Detect automatically', 'releaso' ),
				'readme'   => __( 'readme.txt (= 1.0 = headings)', 'releaso' ),
				'markdown' => __( 'Markdown (CHANGELOG.md)', 'releaso' ),
				'json'     => __( 'JSON', 'releaso' ),
			),
		);
	}

	/**
	 * Parses content.
	 *
	 * @param string $content Content.
	 * @param string $format  Format.
	 * @param string $name    File name / URL hint.
	 * @param array  $state   State to keep for the next fetch.
	 * @return FetchResult|WP_Error
	 */
	protected function parse( $content, $format = 'auto', $name = '', array $state = array() ) {
		$factory = new ParserFactory( ChangeTypes::classifier(), (bool) $this->settings->get( 'include_unreleased' ) );
		try {
			$parser   = $factory->make( $format, $content, $name );
			$releases = $parser->parse( $content );
		} catch ( ParseException $e ) {
			return new WP_Error( 'releaso_parse', $e->getMessage() );
		}

		if ( ! count( $releases ) ) {
			return new WP_Error(
				'releaso_empty',
				/* translators: %s: format name */
				sprintf( __( 'No releases were found (read as %s). Check the headings, e.g. "= 1.2.0 =" or "## 1.2.0".', 'releaso' ), $parser->format() )
			);
		}

		$state['format'] = $parser->format();
		return FetchResult::releases( $releases, $state );
	}

	/**
	 * Fetches a URL (conditionally) and parses it.
	 *
	 * @param string $url     URL.
	 * @param string $format  Format.
	 * @param array  $state   Last state.
	 * @param array  $headers Headers.
	 * @return FetchResult|WP_Error
	 */
	protected function fetch_url( $url, $format, array $state, array $headers = array() ) {
		// Only reuse validators when the URL is unchanged.
		$validator = ( $state['url'] ?? '' ) === $url ? $state : array();
		$response  = $this->http->get( $url, $headers, $validator );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$next = array(
			'url'           => $url,
			'etag'          => $response['etag'],
			'last_modified' => $response['last_modified'],
		);

		if ( $response['not_modified'] ) {
			return FetchResult::not_modified( array_merge( $state, $next ) );
		}
		return $this->parse( $response['body'], $format, $url, $next );
	}
}
