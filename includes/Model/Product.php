<?php
/**
 * A product whose changelog is shown: a releaso_product post plus its source settings.
 *
 * @package Releaso
 */

namespace Releaso\Model;

/**
 * Product value object.
 */
final class Product {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Slug used in the shortcode, block and REST API.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Display name (tab label).
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Source type key, see SourceRegistry.
	 *
	 * @var string
	 */
	public $source;

	/**
	 * Source settings (slug, URL, repository, format …).
	 *
	 * @var array
	 */
	public $config;

	/**
	 * Optional product page link ("Get it").
	 *
	 * @var string
	 */
	public $link;

	/**
	 * Post status.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Constructor.
	 *
	 * @param array $data Fields.
	 */
	public function __construct( array $data ) {
		$this->id     = (int) ( $data['id'] ?? 0 );
		$this->slug   = (string) ( $data['slug'] ?? '' );
		$this->name   = (string) ( $data['name'] ?? '' );
		$this->source = (string) ( $data['source'] ?? 'manual' );
		$this->config = (array) ( $data['config'] ?? array() );
		$this->link   = (string) ( $data['link'] ?? '' );
		$this->status = (string) ( $data['status'] ?? 'publish' );
	}

	/**
	 * A source setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = '' ) {
		return $this->config[ $key ] ?? $default;
	}

	/**
	 * Public representation (no secrets: source settings stay private).
	 *
	 * @return array
	 */
	public function to_public_array() {
		return array(
			'id'     => $this->id,
			'slug'   => $this->slug,
			'name'   => $this->name,
			'source' => $this->source,
			'link'   => $this->link,
		);
	}
}
