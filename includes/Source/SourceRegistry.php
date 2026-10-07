<?php
/**
 * The available changelog sources.
 *
 * @package Releaso
 */

namespace Releaso\Source;

use Releaso\Support\Http;
use Releaso\Support\Settings;

/**
 * Source registry.
 */
class SourceRegistry {

	/**
	 * Sources by key.
	 *
	 * @var SourceInterface[]|null
	 */
	private $sources = null;

	/**
	 * HTTP client.
	 *
	 * @var Http
	 */
	private $http;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Http     $http     HTTP client.
	 * @param Settings $settings Settings.
	 */
	public function __construct( Http $http, Settings $settings ) {
		$this->http     = $http;
		$this->settings = $settings;
	}

	/**
	 * All sources, keyed.
	 *
	 * @return SourceInterface[]
	 */
	public function all() {
		if ( null === $this->sources ) {
			$builtin = array(
				new WporgSource( $this->http, $this->settings ),
				new InstalledPluginSource( $this->http, $this->settings ),
				new GithubSource( $this->http, $this->settings ),
				new UrlSource( $this->http, $this->settings ),
				new TextSource( $this->http, $this->settings ),
				new ManualSource( $this->http, $this->settings ),
			);

			/**
			 * Filters the changelog sources. Add instances of SourceInterface.
			 *
			 * @param SourceInterface[] $sources  Sources.
			 * @param Http              $http     HTTP client, for custom sources.
			 * @param Settings          $settings Settings.
			 */
			$list = apply_filters( 'releaso_sources', $builtin, $this->http, $this->settings );

			$this->sources = array();
			foreach ( (array) $list as $source ) {
				if ( $source instanceof SourceInterface ) {
					$this->sources[ $source->key() ] = $source;
				}
			}
		}
		return $this->sources;
	}

	/**
	 * A source by key.
	 *
	 * @param string $key Key.
	 * @return SourceInterface|null
	 */
	public function get( $key ) {
		return $this->all()[ $key ] ?? null;
	}
}
