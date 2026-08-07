<?php
/**
 * Activation and deactivation hooks.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Post_Type::register();
		Access::add_caps();
		update_option( 'tbtmg_caps_version', Access::CAPS_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
