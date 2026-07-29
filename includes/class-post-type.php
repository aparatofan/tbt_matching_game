<?php
/**
 * Custom post type registration.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Type {
	public const POST_TYPE = 'tbt_match_game';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'                  => __( 'Matching Games', 'tbt-matching-games' ),
			'singular_name'         => __( 'Matching Game', 'tbt-matching-games' ),
			'menu_name'             => __( 'Matching Games', 'tbt-matching-games' ),
			'name_admin_bar'        => __( 'Matching Game', 'tbt-matching-games' ),
			'add_new'               => __( 'Add New', 'tbt-matching-games' ),
			'add_new_item'          => __( 'Add New Matching Game', 'tbt-matching-games' ),
			'new_item'              => __( 'New Matching Game', 'tbt-matching-games' ),
			'edit_item'             => __( 'Edit Matching Game', 'tbt-matching-games' ),
			'view_item'             => __( 'View Matching Game', 'tbt-matching-games' ),
			'all_items'             => __( 'All Matching Games', 'tbt-matching-games' ),
			'search_items'          => __( 'Search Matching Games', 'tbt-matching-games' ),
			'not_found'             => __( 'No matching games found.', 'tbt-matching-games' ),
			'not_found_in_trash'    => __( 'No matching games found in Trash.', 'tbt-matching-games' ),
			'featured_image'        => __( 'Game image', 'tbt-matching-games' ),
			'set_featured_image'    => __( 'Set game image', 'tbt-matching-games' ),
			'remove_featured_image' => __( 'Remove game image', 'tbt-matching-games' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => 'matching-game',
					'with_front' => false,
				),
				'supports'            => array( 'title', 'author', 'revisions' ),
				'menu_icon'           => 'dashicons-screenoptions',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Disable the block editor for matching games.
	 *
	 * @param bool   $use_block_editor Current choice.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : $use_block_editor;
	}

	/**
	 * Change the title placeholder.
	 *
	 * @param string   $placeholder Existing placeholder.
	 * @param \WP_Post $post Current post.
	 * @return string
	 */
	public function title_placeholder( string $placeholder, \WP_Post $post ): string {
		if ( self::POST_TYPE === $post->post_type ) {
			return __( 'Game title', 'tbt-matching-games' );
		}

		return $placeholder;
	}
}
