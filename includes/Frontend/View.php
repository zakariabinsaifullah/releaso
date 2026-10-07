<?php
/**
 * Helpers available to templates as $view.
 *
 * @package Releaso
 */

namespace Releaso\Frontend;

use Releaso\Model\Change;
use Releaso\Model\Product;
use Releaso\Model\Release;
use Releaso\Support\ChangeTypes;
use Releaso\Support\Formatter;
use Releaso\Support\Settings;

/**
 * Template helpers.
 */
class View {

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
	 * Release date in the display format, '' when unknown.
	 *
	 * @param Release $release Release.
	 * @return string
	 */
	public function date( Release $release ) {
		if ( '' === $release->date ) {
			return '';
		}
		$format = (string) $this->settings->get( 'date_format', '' );
		return date_i18n( '' !== $format ? $format : get_option( 'date_format' ), strtotime( $release->date . ' 12:00:00' ) );
	}

	/**
	 * Anchor ID of a release: "slider-blocks-1-6-0".
	 *
	 * @param Product $product Product.
	 * @param Release $release Release.
	 * @return string
	 */
	public function anchor( Product $product, Release $release ) {
		return $product->slug . '-' . sanitize_title( str_replace( '.', '-', $release->version ) );
	}

	/**
	 * A release's changes grouped by type, in the registered type order.
	 *
	 * @param Release $release Release.
	 * @return array<string,Change[]>
	 */
	public function groups( Release $release ) {
		$groups = array();
		foreach ( $release->changes as $change ) {
			$groups[ $change->type ][] = $change;
		}
		$order = array_intersect_key( array_fill_keys( array_keys( ChangeTypes::all() ), null ), $groups );
		return array_merge( $order, $groups );
	}

	/**
	 * Change text as safe HTML.
	 *
	 * @param Change $change Change.
	 * @return string
	 */
	public function text( Change $change ) {
		return Formatter::inline( $change->text );
	}

	/**
	 * Release notes as safe HTML.
	 *
	 * @param Release $release Release.
	 * @return string
	 */
	public function notes( Release $release ) {
		return Formatter::notes( $release->notes );
	}

	/**
	 * A type's label.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public function label( $type ) {
		return ChangeTypes::label( $type );
	}

	/**
	 * "3 fixed" style count label.
	 *
	 * @param string $type  Type key.
	 * @param int    $count Count.
	 * @return string
	 */
	public function count_label( $type, $count ) {
		return $count . ' ' . strtolower( $this->label( $type ) );
	}
}
