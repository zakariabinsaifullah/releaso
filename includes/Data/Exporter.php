<?php
/**
 * Exports releases as Releaso JSON (re-importable), Keep-a-Changelog Markdown or a
 * readme.txt changelog section.
 *
 * @package Releaso
 */

namespace Releaso\Data;

use Releaso\Model\Product;
use Releaso\Model\Release;
use Releaso\Model\ReleaseCollection;
use Releaso\Support\ChangeTypes;

/**
 * Changelog exporter.
 */
class Exporter {

	const FORMATS = array( 'json', 'markdown', 'readme' );

	/**
	 * Exports one or more products.
	 *
	 * @param array  $items  Each: [ Product, ReleaseCollection ].
	 * @param string $format json, markdown or readme.
	 * @return string
	 */
	public function export( array $items, $format ) {
		if ( 'json' === $format ) {
			$products = array();
			foreach ( $items as list( $product, $releases ) ) {
				$products[] = array_merge( $product->to_public_array(), array( 'releases' => $this->strip( $releases ) ) );
			}
			$doc = array(
				'format'    => 'releaso/v1',
				'generator' => 'Releaso ' . RELEASO_VERSION,
				'exported'  => gmdate( 'c' ),
			);
			// One product exports flat, so the file imports straight back.
			$doc = 1 === count( $products ) ? array_merge( $doc, $products[0] ) : array_merge( $doc, array( 'products' => $products ) );
			return (string) wp_json_encode( $doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		$out = array();
		foreach ( $items as list( $product, $releases ) ) {
			$out[] = 'readme' === $format ? $this->readme( $product, $releases ) : $this->markdown( $product, $releases );
		}
		return implode( "\n\n", $out ) . "\n";
	}

	/**
	 * Releases without the internal origin field.
	 *
	 * @param ReleaseCollection $releases Releases.
	 * @return array
	 */
	private function strip( ReleaseCollection $releases ) {
		return array_map(
			static function ( $row ) {
				unset( $row['origin'] );
				return array_filter(
					$row,
					static function ( $value ) {
						return '' !== $value && array() !== $value;
					}
				);
			},
			$releases->to_array()
		);
	}

	/**
	 * Keep a Changelog Markdown.
	 *
	 * @param Product           $product  Product.
	 * @param ReleaseCollection $releases Releases.
	 * @return string
	 */
	private function markdown( Product $product, ReleaseCollection $releases ) {
		/* translators: %s: product name */
		$lines = array( '# ' . sprintf( __( '%s changelog', 'releaso' ), $product->name ), '' );
		foreach ( $releases as $release ) {
			$lines[] = '## [' . $release->version . ']' . ( $release->date ? ' - ' . $release->date : '' ) . ( $release->title ? ' - ' . $release->title : '' );
			$lines[] = '';
			if ( '' !== $release->notes ) {
				$lines[] = $release->notes;
				$lines[] = '';
			}
			foreach ( $this->grouped( $release ) as $type => $changes ) {
				$lines[] = '### ' . ChangeTypes::label( $type );
				foreach ( $changes as $change ) {
					$lines[] = '- ' . ( $change->tag ? '[' . $change->tag . '] ' : '' ) . $change->text;
				}
				$lines[] = '';
			}
		}
		return rtrim( implode( "\n", $lines ) );
	}

	/**
	 * The readme.txt "== Changelog ==" section.
	 *
	 * @param Product           $product  Product.
	 * @param ReleaseCollection $releases Releases.
	 * @return string
	 */
	private function readme( Product $product, ReleaseCollection $releases ) {
		$lines = array( '== Changelog ==', '' );
		foreach ( $releases as $release ) {
			$lines[] = '= ' . $release->version . ( $release->date ? ' - ' . $release->date : '' ) . ' =';
			foreach ( $release->changes as $change ) {
				$lines[] = '* ' . ChangeTypes::label( $change->type ) . ( $change->tag ? ' (' . $change->tag . ')' : '' ) . ': ' . $change->text;
			}
			$lines[] = '';
		}
		return rtrim( implode( "\n", $lines ) );
	}

	/**
	 * Changes grouped by type in registered order.
	 *
	 * @param Release $release Release.
	 * @return array
	 */
	private function grouped( Release $release ) {
		$groups = array();
		foreach ( $release->changes as $change ) {
			$groups[ $change->type ][] = $change;
		}
		return array_merge( array_intersect_key( array_fill_keys( array_keys( ChangeTypes::all() ), null ), $groups ), $groups );
	}
}
