<?php
/**
 * A GitHub repository: its Releases, or a changelog file in the repo. Private repositories
 * (typical for Pro plugins) work with a token in Settings or RELEASO_GITHUB_TOKEN.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\Product;
use Releaso\Model\Release;
use Releaso\Model\ReleaseCollection;
use Releaso\Parser\AbstractLineParser;
use Releaso\Parser\MarkdownParser;
use Releaso\Support\ChangeTypes;
use WP_Error;

/**
 * GitHub source.
 */
class GithubSource extends AbstractSource {

	const API = 'https://api.github.com';

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function key() {
		return 'github';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'GitHub repository', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Uses the repository\'s Releases, or a CHANGELOG.md / readme.txt in it. Private repositories need a token in Releaso → Settings.', 'releaso' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function fields() {
		return array(
			'repo'        => array(
				'label'       => __( 'Repository', 'releaso' ),
				'type'        => 'text',
				'placeholder' => 'owner/repository',
				'required'    => true,
			),
			'mode'        => array(
				'label'   => __( 'Read', 'releaso' ),
				'type'    => 'select',
				'default' => 'releases',
				'options' => array(
					'releases' => __( 'GitHub Releases', 'releaso' ),
					'file'     => __( 'A changelog file in the repository', 'releaso' ),
				),
			),
			'path'        => array(
				'label'       => __( 'File path', 'releaso' ),
				'type'        => 'text',
				'placeholder' => 'CHANGELOG.md',
				'help'        => __( 'For "A changelog file". Defaults to CHANGELOG.md.', 'releaso' ),
				'show_if'     => array( 'mode' => 'file' ),
			),
			'ref'         => array(
				'label'       => __( 'Branch or tag', 'releaso' ),
				'type'        => 'text',
				'placeholder' => 'main',
				'help'        => __( 'For "A changelog file". Empty uses the default branch.', 'releaso' ),
				'show_if'     => array( 'mode' => 'file' ),
			),
			'prereleases' => array(
				'label'   => __( 'Include pre-releases', 'releaso' ),
				'type'    => 'checkbox',
				'show_if' => array( 'mode' => 'releases' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $config Raw.
	 * @return array
	 */
	public function sanitize( array $config ) {
		$out = parent::sanitize( $config );
		// Accept https://github.com/owner/repo(.git).
		if ( preg_match( '#github\.com[/:]([\w.-]+/[\w.-]+?)(?:\.git)?(?:/|$)#i', $out['repo'], $m ) ) {
			$out['repo'] = $m[1];
		}
		$out['repo'] = preg_match( '#^[\w.-]+/[\w.-]+$#', $out['repo'] ) ? $out['repo'] : '';
		$out['path'] = ltrim( str_replace( '..', '', $out['path'] ), '/' );
		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Product $product Product.
	 * @param array   $state   State.
	 * @return FetchResult|WP_Error
	 */
	public function fetch( Product $product, array $state ) {
		$repo = (string) $product->get( 'repo' );
		if ( '' === $repo ) {
			return new WP_Error( 'releaso_config', __( 'Enter the repository as owner/repository.', 'releaso' ) );
		}

		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		$token   = $this->settings->github_token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		if ( 'file' === $product->get( 'mode' ) ) {
			$path = '' !== $product->get( 'path' ) ? (string) $product->get( 'path' ) : 'CHANGELOG.md';
			$url  = self::API . '/repos/' . $repo . '/contents/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
			if ( '' !== $product->get( 'ref' ) ) {
				$url = add_query_arg( 'ref', rawurlencode( (string) $product->get( 'ref' ) ), $url );
			}
			$headers['Accept'] = 'application/vnd.github.raw+json';
			return $this->explain( $this->fetch_url( $url, 'auto', $state, $headers ), $repo );
		}

		$url       = self::API . '/repos/' . $repo . '/releases?per_page=100';
		$validator = ( $state['url'] ?? '' ) === $url ? $state : array();
		$response  = $this->http->get( $url, $headers, $validator );
		if ( is_wp_error( $response ) ) {
			return $this->explain( $response, $repo );
		}
		$next = array(
			'url'           => $url,
			'etag'          => $response['etag'],
			'last_modified' => $response['last_modified'],
			'format'        => 'github-releases',
		);
		if ( $response['not_modified'] ) {
			return FetchResult::not_modified( array_merge( $state, $next ) );
		}

		$rows = json_decode( $response['body'], true );
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'releaso_parse', __( 'GitHub returned an unexpected response.', 'releaso' ) );
		}

		$releases = $this->releases( $rows, (bool) $product->get( 'prereleases' ) );
		if ( ! count( $releases ) ) {
			return new WP_Error( 'releaso_empty', __( 'The repository has no published releases yet.', 'releaso' ) );
		}
		return FetchResult::releases( $releases, $next );
	}

	/**
	 * GitHub release rows to releases.
	 *
	 * @param array $rows         API rows.
	 * @param bool  $prereleases  Keep pre-releases.
	 * @return ReleaseCollection
	 */
	public function releases( array $rows, $prereleases = false ) {
		$markdown = new MarkdownParser( ChangeTypes::classifier() );
		$out      = new ReleaseCollection();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['draft'] ) || ( ! $prereleases && ! empty( $row['prerelease'] ) ) ) {
				continue;
			}
			$tag = (string) ( $row['tag_name'] ?? '' );
			if ( ! preg_match( '/\d/', $tag ) ) {
				continue;
			}
			$release       = new Release( preg_replace( '/^[^\d]*(?=\d)/', '', $tag ) );
			$release->date = AbstractLineParser::parse_date( (string) ( $row['published_at'] ?? $row['created_at'] ?? '' ) );
			$release->url  = esc_url_raw( (string) ( $row['html_url'] ?? '' ) );
			$name          = trim( (string) ( $row['name'] ?? '' ) );
			if ( '' !== $name && $name !== $tag && ltrim( $name, 'vV' ) !== $release->version ) {
				$release->title = $name;
			}
			$markdown->fill( $release, self::tidy( (string) ( $row['body'] ?? '' ) ) );
			$out->add( $release );
		}
		return $out;
	}

	/**
	 * Tidies auto-generated release notes: "Fix X by @me in https://github.com/o/r/pull/12" →
	 * "Fix X (#12)", and drops the "Full Changelog" and "New Contributors" parts.
	 *
	 * @param string $body Body.
	 * @return string
	 */
	public static function tidy( $body ) {
		$body = preg_replace( '/^\*\*Full Changelog\*\*:.*$/mi', '', $body );
		$body = preg_replace( '/^#{1,4}\s*New Contributors.*?(?=^#{1,4}\s|\z)/msi', '', $body );
		return preg_replace( '#\s+by @[\w-]+ in (https://github\.com/[^\s]+/pull/(\d+))#', ' ([#$2]($1))', $body );
	}

	/**
	 * Friendlier errors for the usual GitHub problems.
	 *
	 * @param FetchResult|WP_Error $result Result.
	 * @param string               $repo   Repository.
	 * @return FetchResult|WP_Error
	 */
	private function explain( $result, $repo ) {
		if ( ! is_wp_error( $result ) ) {
			return $result;
		}
		$status = (int) ( $result->get_error_data()['status'] ?? 0 );
		if ( 404 === $status ) {
			/* translators: %s: repository */
			return new WP_Error( 'releaso_not_found', sprintf( __( '"%s" was not found. For a private repository, add a GitHub token in Releaso → Settings.', 'releaso' ), $repo ) );
		}
		if ( 401 === $status || 403 === $status ) {
			return new WP_Error( 'releaso_denied', __( 'GitHub refused the request: the token is missing, invalid or rate-limited.', 'releaso' ) );
		}
		return $result;
	}
}
