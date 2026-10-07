<?php
/**
 * Activation, deactivation and versioned upgrade routines.
 *
 * @package Releaso
 */

namespace Releaso\Core;

use Releaso\Cron\Scheduler;
use Releaso\Data\PostTypes;

/**
 * Installer.
 */
class Installer {

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'releaso_db_version';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Activation: post types for rewrite safety, the cron event, the upgrade routine.
	 *
	 * @return void
	 */
	public static function activate() {
		( new PostTypes() )->register_post_types();
		Scheduler::schedule();
		( new self() )->maybe_upgrade();
		add_option( 'releaso_activated', time(), '', false );
	}

	/**
	 * Deactivation: stop the cron event. Data stays until uninstall.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Scheduler::unschedule();
	}

	/**
	 * Runs upgrade steps once per data version.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		// Version 1: the settings option exists (autoloaded) and the cron is scheduled.
		if ( $installed < 1 ) {
			add_option( \Releaso\Support\Settings::OPTION, array(), '', true );
			Scheduler::schedule();
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}
}
