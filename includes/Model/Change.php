<?php
/**
 * One line of a release: "Fixed (Pro): Panorama height on mobile".
 *
 * @package Releaso
 */

namespace Releaso\Model;

/**
 * Immutable change entry.
 */
final class Change {

	/**
	 * Type key, see ChangeTypes.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Optional tag, such as "Pro".
	 *
	 * @var string
	 */
	public $tag;

	/**
	 * The change, in inline Markdown.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Constructor.
	 *
	 * @param string $type Type key.
	 * @param string $text Text.
	 * @param string $tag  Tag.
	 */
	public function __construct( $type, $text, $tag = '' ) {
		$this->type = (string) $type;
		$this->text = trim( (string) $text );
		$this->tag  = trim( (string) $tag );
	}

	/**
	 * From a stored array.
	 *
	 * @param array $data type, text, tag.
	 * @return self
	 */
	public static function from_array( array $data ) {
		return new self( $data['type'] ?? 'improved', $data['text'] ?? '', $data['tag'] ?? '' );
	}

	/**
	 * To a storable array.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'type' => $this->type,
			'tag'  => $this->tag,
			'text' => $this->text,
		);
	}
}
