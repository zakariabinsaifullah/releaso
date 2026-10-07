<?php
/**
 * Fetches remote changelogs: safe URLs only, size-capped, with conditional requests
 * (ETag / Last-Modified) so an unchanged changelog costs a 304 and no parsing.
 *
 * @package Releaso
 */

namespace Releaso\Support;

use WP_Error;

/**
 * HTTP client.
 */
class Http {

	const MAX_BYTES = 2097152; // 2 MB: far more than any changelog.

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * GET a URL.
	 *
	 * @param string $url       URL.
	 * @param array  $headers   Extra headers.
	 * @param array  $validator Previous response's [ etag, last_modified ] for a conditional request.
	 * @return array|WP_Error [ status, body, etag, last_modified, not_modified ].
	 */
	public function get( $url, array $headers = array(), array $validator = array() ) {
		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'releaso_bad_url', __( 'The URL is not valid or points to a private address.', 'releaso' ) );
		}

		if ( ! empty( $validator['etag'] ) ) {
			$headers['If-None-Match'] = $validator['etag'];
		}
		if ( ! empty( $validator['last_modified'] ) ) {
			$headers['If-Modified-Since'] = $validator['last_modified'];
		}

		$args = array(
			'timeout'             => (int) $this->settings->get( 'http_timeout', 10 ),
			'redirection'         => 3,
			'limit_response_size' => self::MAX_BYTES,
			'user-agent'          => 'Releaso/' . RELEASO_VERSION . '; ' . home_url( '/' ),
			'headers'             => $headers,
		);

		/**
		 * Filters the request arguments for a changelog fetch.
		 *
		 * @param array  $args Request args.
		 * @param string $url  URL.
		 */
		$args = apply_filters( 'releaso_http_args', $args, $url );

		$response = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 304 === $status ) {
			return array(
				'status'        => 304,
				'body'          => '',
				'etag'          => $validator['etag'] ?? '',
				'last_modified' => $validator['last_modified'] ?? '',
				'not_modified'  => true,
			);
		}
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'releaso_http_' . $status,
				/* translators: 1: HTTP status code, 2: URL */
				sprintf( __( 'HTTP %1$d from %2$s', 'releaso' ), $status, $url ),
				array( 'status' => $status )
			);
		}

		return array(
			'status'        => $status,
			'body'          => (string) wp_remote_retrieve_body( $response ),
			'etag'          => (string) wp_remote_retrieve_header( $response, 'etag' ),
			'last_modified' => (string) wp_remote_retrieve_header( $response, 'last-modified' ),
			'not_modified'  => false,
		);
	}
}
