<?php
/**
 * Plugin uninstall routine.
 *
 * @package TBT_Matching_Games
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'TBT_MATCHING_GAMES_DELETE_DATA' ) || true !== TBT_MATCHING_GAMES_DELETE_DATA ) {
	return;
}

$game_ids = get_posts(
	array(
		'post_type'      => 'tbt_match_game',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $game_ids as $game_id ) {
	wp_delete_post( $game_id, true );
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tbtmg_%' OR option_name LIKE '_transient_timeout_tbtmg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
