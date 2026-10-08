<?php
/**
 * Fetches every post of a query in pages, so no single query asks for an unbounded number of rows.
 *
 * @package Releaso
 */

namespace Releaso\Support;

/**
 * Paged post fetching.
 */
class Posts {

	const PAGE = 100;

	/**
	 * All posts matching the arguments, read PAGE at a time.
	 *
	 * @param array $args get_posts() arguments (posts_per_page and paged are set here).
	 * @param int   $max  Safety limit on the total.
	 * @return \WP_Post[]
	 */
	public static function all( array $args, $max = 5000 ) {
		$args['posts_per_page'] = self::PAGE;
		$args['no_found_rows']  = true;
		$out                    = array();
		$total                  = 0;
		$page                   = 1;

		while ( $total < $max ) {
			$args['paged'] = $page++;
			$posts         = get_posts( $args );
			$found         = count( $posts );
			$out           = array_merge( $out, $posts );
			$total        += $found;
			if ( $found < self::PAGE ) {
				break;
			}
		}
		return array_slice( $out, 0, $max );
	}
}
