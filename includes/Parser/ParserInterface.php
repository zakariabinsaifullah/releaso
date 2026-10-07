<?php
/**
 * Turns changelog text in some format into releases.
 *
 * @package Releaso
 */

namespace Releaso\Parser;

use Releaso\Model\ReleaseCollection;

/**
 * Changelog parser.
 */
interface ParserInterface {

	/**
	 * Format key: readme, markdown, json.
	 *
	 * @return string
	 */
	public function format();

	/**
	 * Parses a whole changelog.
	 *
	 * @param string $content Raw content.
	 * @return ReleaseCollection Releases in document order.
	 * @throws ParseException When the content cannot be read at all.
	 */
	public function parse( $content );
}
