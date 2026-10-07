<?php
/**
 * Background sync: an hourly WP-Cron event syncs the products that are due.
 * Each product's own interval is checked in ChangelogService::is_due().
 *
 * @package Releaso
 */

namespace Releaso\Cron;

use Releaso\Data\ChangelogService;

/**
 * Cron scheduler.
 */
class Scheduler {

	const HOOK        = 'releaso_sync';
	const RESYNC_HOOK = 'releaso_resync';

	/**
	 * Changelog service.
	 *
	 * @var ChangelogService
	 */
	private $changelog;

	/**
	 * Constructor.
	 *
	 * @param ChangelogService $changelog Changelog service.
	 */
	public function __construct( ChangelogService $changelog ) {
		$this->changelog = $changelog;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( self::RESYNC_HOOK, array( $this, 'run_forced' ) );
		// Self-heal if the event went missing (e.g. a cron table reset).
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Syncs due products.
	 *
	 * @return void
	 */
	public function run() {
		$this->changelog->sync_all();
	}

	/**
	 * Syncs every product, ignoring intervals and validators.
	 *
	 * @return void
	 */
	public function run_forced() {
		$this->changelog->sync_all( true );
	}

	/**
	 * Queues a one-off forced sync of every product (after a setting that changes parsing).
	 *
	 * @return void
	 */
	public static function resync() {
		if ( ! wp_next_scheduled( self::RESYNC_HOOK ) ) {
			wp_schedule_single_event( time(), self::RESYNC_HOOK );
		}
	}

	/**
	 * Schedules the event when missing.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Removes the event.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::RESYNC_HOOK );
	}
}
