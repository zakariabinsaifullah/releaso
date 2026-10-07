<?php
/**
 * The outcome of a source fetch.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Model\ReleaseCollection;

/**
 * Fetch result.
 */
final class FetchResult {

	/**
	 * Parsed releases (null when not modified).
	 *
	 * @var ReleaseCollection|null
	 */
	public $releases;

	/**
	 * The content did not change since the last fetch.
	 *
	 * @var bool
	 */
	public $not_modified = false;

	/**
	 * State to keep for the next fetch (etag, last_modified, format …).
	 *
	 * @var array
	 */
	public $state = array();

	/**
	 * Releases fetched.
	 *
	 * @param ReleaseCollection $releases Releases.
	 * @param array             $state    State.
	 * @return self
	 */
	public static function releases( ReleaseCollection $releases, array $state = array() ) {
		$result           = new self();
		$result->releases = $releases;
		$result->state    = $state;
		return $result;
	}

	/**
	 * Nothing changed.
	 *
	 * @param array $state State.
	 * @return self
	 */
	public static function not_modified( array $state = array() ) {
		$result               = new self();
		$result->not_modified = true;
		$result->state        = $state;
		return $result;
	}
}
