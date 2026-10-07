<?php
/**
 * The change types (New, Improved, Fixed, Removed, Security): labels, colours and the words
 * that mark them. Releaso → Settings → Change types renames and recolours them and adds custom
 * types; add-ons can add or rename types with the `releaso_change_types` filter, which runs last.
 *
 * Example:
 *
 *     add_filter( 'releaso_change_types', function ( $types ) {
 *         $types['breaking'] = array( 'label' => 'Breaking', 'color' => '#c2185b', 'aliases' => array( 'breaking' ) );
 *         return $types;
 *     } );
 *
 * @package Releaso
 */

namespace Releaso\Support;

use Releaso\Parser\Classifier;

/**
 * Change type registry.
 */
class ChangeTypes {

	/**
	 * Resolved types.
	 *
	 * @var array|null
	 */
	private static $types = null;

	/**
	 * Types: key => [ label, color, aliases ], in display order.
	 *
	 * @return array<string,array{label:string,color:string,aliases:string[]}>
	 */
	public static function all() {
		if ( null === self::$types ) {
			$types = self::with_saved( self::builtin(), self::saved() );

			/**
			 * Filters the change types.
			 *
			 * @param array $types key => [ label, color, aliases ].
			 */
			$filtered = apply_filters( 'releaso_change_types', $types );

			self::$types = array();
			foreach ( (array) $filtered as $key => $type ) {
				$key = sanitize_key( $key );
				if ( '' === $key || ! is_array( $type ) ) {
					continue;
				}
				self::$types[ $key ] = array(
					'label'   => (string) ( $type['label'] ?? ucfirst( $key ) ),
					'color'   => (string) ( $type['color'] ?? '#6a6477' ),
					'aliases' => array_map( 'strtolower', (array) ( $type['aliases'] ?? array( $key ) ) ),
				);
			}
		}
		return self::$types;
	}

	/**
	 * The built-in types, before settings and filters.
	 *
	 * @return array<string,array{label:string,color:string,aliases:string[]}>
	 */
	public static function builtin() {
		$aliases = Classifier::DEFAULT_ALIASES;
		return array(
			'new'      => array(
				'label'   => __( 'New', 'releaso' ),
				'color'   => '#13a57c',
				'aliases' => $aliases['new'],
			),
			'improved' => array(
				'label'   => __( 'Improved', 'releaso' ),
				'color'   => '#2f7cf0',
				'aliases' => $aliases['improved'],
			),
			'fixed'    => array(
				'label'   => __( 'Fixed', 'releaso' ),
				'color'   => '#e2742a',
				'aliases' => $aliases['fixed'],
			),
			'removed'  => array(
				'label'   => __( 'Removed', 'releaso' ),
				'color'   => '#d64545',
				'aliases' => $aliases['removed'],
			),
			'security' => array(
				'label'   => __( 'Security', 'releaso' ),
				'color'   => '#8b3fe8',
				'aliases' => $aliases['security'],
			),
		);
	}

	/**
	 * Type customisations saved in Releaso → Settings: key => [ label?, color?, aliases?, custom? ].
	 *
	 * @return array
	 */
	public static function saved() {
		$settings = get_option( Settings::OPTION, array() );
		return is_array( $settings ) && ! empty( $settings['types'] ) && is_array( $settings['types'] ) ? $settings['types'] : array();
	}

	/**
	 * Applies saved customisations: new labels and colours for built-in types, then custom types.
	 *
	 * @param array $types Built-in types.
	 * @param array $saved Saved customisations.
	 * @return array
	 */
	public static function with_saved( array $types, array $saved ) {
		foreach ( $saved as $key => $type ) {
			if ( ! is_array( $type ) ) {
				continue;
			}
			if ( isset( $types[ $key ] ) ) {
				foreach ( array( 'label', 'color' ) as $field ) {
					if ( ! empty( $type[ $field ] ) ) {
						$types[ $key ][ $field ] = (string) $type[ $field ];
					}
				}
			} elseif ( ! empty( $type['custom'] ) && ! empty( $type['label'] ) ) {
				$types[ $key ] = array(
					'label'   => (string) $type['label'],
					'color'   => (string) ( $type['color'] ?? '#6a6477' ),
					'aliases' => array_values( array_unique( array_merge( array( strtolower( (string) $key ) ), (array) ( $type['aliases'] ?? array() ) ) ) ),
				);
			}
		}
		return $types;
	}

	/**
	 * Sanitizes the types submitted from the settings screen. Built-in types keep only what
	 * differs from their defaults; custom types get a key made from their label on first save.
	 *
	 * @param mixed $input key => [ label, color, aliases (comma-separated), custom ].
	 * @return array
	 */
	public static function sanitize( $input ) {
		$builtin = self::builtin();
		$out     = array();
		$input   = is_array( $input ) ? $input : array();
		// Saved custom types first, so a new type never takes an existing type's key.
		uksort(
			$input,
			static function ( $a, $b ) use ( $input ) {
				return (int) empty( $input[ $a ]['key'] ) - (int) empty( $input[ $b ]['key'] );
			}
		);
		foreach ( $input as $key => $type ) {
			if ( ! is_array( $type ) ) {
				continue;
			}
			$label = sanitize_text_field( $type['label'] ?? '' );
			$color = (string) sanitize_hex_color( $type['color'] ?? '' );
			$key   = sanitize_key( $key );

			if ( isset( $builtin[ $key ] ) ) {
				$changes = array_filter(
					array(
						'label' => $label !== $builtin[ $key ]['label'] ? $label : '',
						'color' => strtolower( $color ) !== strtolower( $builtin[ $key ]['color'] ) ? $color : '',
					)
				);
				if ( $changes ) {
					$out[ $key ] = $changes;
				}
				continue;
			}

			if ( '' === $label ) {
				continue;
			}
			// New rows arrive as "new-1", "new-2"…: the key comes from the label, and never changes after.
			if ( empty( $type['key'] ) ) {
				$key  = substr( sanitize_key( str_replace( ' ', '-', remove_accents( strtolower( $label ) ) ) ), 0, 32 );
				$key  = '' === $key ? 'type' : $key;
				$base = $key;
				for ( $i = 2; isset( $builtin[ $key ] ) || isset( $out[ $key ] ); $i++ ) {
					$key = $base . '-' . $i;
				}
			} else {
				$key = substr( sanitize_key( $type['key'] ), 0, 32 );
				if ( '' === $key || isset( $builtin[ $key ] ) || isset( $out[ $key ] ) ) {
					continue;
				}
			}

			$aliases     = array_filter( array_map( 'trim', explode( ',', strtolower( sanitize_text_field( $type['aliases'] ?? '' ) ) ) ) );
			$out[ $key ] = array(
				'label'   => $label,
				'color'   => $color ? $color : '#6a6477',
				'aliases' => array_values( array_unique( array_merge( array( strtolower( $label ) ), $aliases ) ) ),
				'custom'  => true,
			);
		}
		return $out;
	}

	/**
	 * A type's label.
	 *
	 * @param string $key Type key.
	 * @return string
	 */
	public static function label( $key ) {
		$types = self::all();
		return $types[ $key ]['label'] ?? ucfirst( (string) $key );
	}

	/**
	 * Whether a type exists.
	 *
	 * @param string $key Type key.
	 * @return bool
	 */
	public static function exists( $key ) {
		return isset( self::all()[ $key ] );
	}

	/**
	 * Key => label.
	 *
	 * @return array<string,string>
	 */
	public static function options() {
		return wp_list_pluck( self::all(), 'label' );
	}

	/**
	 * A classifier that knows the registered types.
	 *
	 * @return Classifier
	 */
	public static function classifier() {
		return new Classifier( wp_list_pluck( self::all(), 'aliases' ), self::exists( 'improved' ) ? 'improved' : '' );
	}

	/**
	 * Forgets resolved types (tests, late filters).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$types = null;
	}
}
