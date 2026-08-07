<?php
/**
 * Plugin Name:       TBT Matching Games
 * Plugin URI:        https://github.com/aparatofan/tbt_matching_game
 * Description:       Create, edit, publish, and embed AI-assisted matching games.
 * Version:           0.3.1
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

define( 'TBTMG_VERSION', '0.3.1' );
define( 'TBTMG_FILE', __FILE__ );
define( 'TBTMG_DIR', plugin_dir_path( __FILE__ ) );
define( 'TBTMG_URL', plugin_dir_url( __FILE__ ) );

$includes = array(
	'class-activator.php',
	'class-access.php',
	'class-post-type.php',
	'class-game-validator.php',
	'class-game-repository.php',
	'class-assets.php',
	'class-renderer.php',
	'class-shortcode.php',
	'class-tools-shortcode.php',
	'class-template-loader.php',
	'class-openai-client.php',
	'class-generation-controller.php',
	'class-games-controller.php',
	'class-admin.php',
	'class-plugin.php',
);

foreach ( $includes as $include ) {
	require_once TBTMG_DIR . 'includes/' . $include;
}

/**
 * Register this plugin on the TBT Hub overview page, mirroring TBT Swipe.
 *
 * Registered at file scope so it loads regardless of admin context.
 *
 * @param array $items Existing hub items.
 * @return array
 */
function register_hub_item( $items ) {
	$items[] = array(
		'slug'        => 'tbt-matching-games',
		'title'       => 'TBT Matching Games',
		'description' => 'AI-assisted matching games for live lessons; teachers build them on the front end and students play from a link or QR code.',
		'capability'  => Access::CAPABILITY,
	);

	return $items;
}
add_filter( 'tbt_hub_items', __NAMESPACE__ . '\\register_hub_item' );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

Plugin::instance()->boot();
