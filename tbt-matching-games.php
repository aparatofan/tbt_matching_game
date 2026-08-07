<?php
/**
 * Plugin Name:       TBT Matching Games
 * Plugin URI:        https://github.com/aparatofan/tbt_matching_game
 * Description:       Create, edit, publish, and embed AI-assisted matching games.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mariusz Mirecki
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tbt-matching-games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TBTMG_VERSION', '0.1.1' );
define( 'TBTMG_FILE', __FILE__ );
define( 'TBTMG_DIR', plugin_dir_path( __FILE__ ) );
define( 'TBTMG_URL', plugin_dir_url( __FILE__ ) );

$includes = array(
	'class-activator.php',
	'class-post-type.php',
	'class-game-validator.php',
	'class-game-repository.php',
	'class-assets.php',
	'class-renderer.php',
	'class-shortcode.php',
	'class-template-loader.php',
	'class-openai-client.php',
	'class-generation-controller.php',
	'class-admin.php',
	'class-plugin.php',
);

foreach ( $includes as $include ) {
	require_once TBTMG_DIR . 'includes/' . $include;
}

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

Plugin::instance()->boot();
