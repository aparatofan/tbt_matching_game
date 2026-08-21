<?php
/**
 * Derived search index for the game library.
 *
 * level and topic live inside the serialised _tbtmg_game_data blob, which
 * meta_query cannot reach. These two flat keys are a rebuilt index of that
 * blob, written only from here. _tbtmg_game_data stays canonical: nothing may
 * read these keys as a source of truth.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Search_Index {
	public const LEVEL_META  = '_tbtmg_level';
	public const SEARCH_META = '_tbtmg_search';

	/**
	 * Option holding the index generation this install has been filled to.
	 */
	public const VERSION_OPTION = 'tbtmg_index_version';
	public const INDEX_VERSION  = '1';

	/**
	 * How much indexed text one game may contribute.
	 */
	private const TEXT_MAX = 2000;

	/**
	 * How many games one backfill pass processes.
	 */
	private const BACKFILL_BATCH = 100;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'admin_init', array( self::class, 'maybe_backfill' ) );
		add_action( 'before_delete_post', array( self::class, 'on_delete' ) );
	}

	/**
	 * Write both keys from already-validated game data.
	 *
	 * @param int   $post_id Game post ID.
	 * @param array $data Validated game data, including the title.
	 * @return void
	 */
	public static function update( int $post_id, array $data ): void {
		// normalise(), never sanitize(): a game made before the level picker
		// existed has no level, and must not start claiming B1.
		$level = Levels::normalise( $data['level'] ?? '' );

		update_post_meta( $post_id, self::LEVEL_META, $level );
		update_post_meta( $post_id, self::SEARCH_META, self::index_text( (string) ( $data['title'] ?? '' ), (string) ( $data['topic'] ?? '' ) ) );
	}

	/**
	 * Rebuild both keys from what is stored on the post.
	 *
	 * Used by the backfill and by duplication, where the new title differs from
	 * the copied blob. A missing or malformed blob is not an error: the game
	 * simply indexes as empty.
	 *
	 * @param int $post_id Game post ID.
	 * @return void
	 */
	public static function rebuild( int $post_id ): void {
		$stored = get_post_meta( $post_id, Game_Repository::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();

		self::update(
			$post_id,
			array(
				'title' => (string) get_the_title( $post_id ),
				'topic' => isset( $stored['topic'] ) && is_string( $stored['topic'] ) ? $stored['topic'] : '',
				'level' => isset( $stored['level'] ) ? $stored['level'] : '',
			)
		);
	}

	/**
	 * Remove both keys.
	 *
	 * @param int $post_id Game post ID.
	 * @return void
	 */
	public static function clear( int $post_id ): void {
		delete_post_meta( $post_id, self::LEVEL_META );
		delete_post_meta( $post_id, self::SEARCH_META );
	}

	/**
	 * Drop the index when a game is deleted for good.
	 *
	 * Trashing is not deletion: an untrashed game must still be findable, so
	 * the index survives the trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_delete( $post_id ): void {
		$post_id = absint( $post_id );
		if ( ! $post_id || Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		self::clear( $post_id );
	}

	/**
	 * Prepare a search term for comparison against the index.
	 *
	 * The index is lowercased, so the query must be lowercased identically or a
	 * capital letter would silently match nothing.
	 *
	 * @param string $term Raw search term.
	 * @return string
	 */
	public static function normalise_term( string $term ): string {
		return self::lowercase( trim( $term ) );
	}

	/**
	 * Fill the index on installs that predate it.
	 *
	 * @return void
	 */
	public static function maybe_backfill(): void {
		if ( self::INDEX_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Post_Type::POST_TYPE,
				'post_status'            => array_values( get_post_stati() ),
				'posts_per_page'         => self::BACKFILL_BATCH,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => self::SEARCH_META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			self::rebuild( (int) $post_id );
		}

		// Only a pass that finds nothing left proves the library is indexed, so
		// a big library completes over several admin page loads instead of
		// timing out on one.
		if ( empty( $query->posts ) ) {
			update_option( self::VERSION_OPTION, self::INDEX_VERSION );
		}
	}

	/**
	 * Build the indexed text for one game.
	 *
	 * Title first, then topic. Level is deliberately absent: it has its own
	 * filter, and "b2" in the search blob would collide with it.
	 *
	 * @param string $title Post title.
	 * @param string $topic Game topic.
	 * @return string
	 */
	private static function index_text( string $title, string $topic ): string {
		$text = wp_strip_all_tags( $title . ' ' . $topic );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = self::lowercase( trim( (string) $text ) );

		return self::truncate( $text );
	}

	/**
	 * Lowercase without touching the alphabet.
	 *
	 * Polish diacritics must survive: ćwiczenie stays ćwiczenie. No
	 * transliteration, no sanitize_title(), no stripping of non-ASCII.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lowercase( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Cap the indexed text, on characters rather than bytes.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function truncate( string $text ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, self::TEXT_MAX, 'UTF-8' );
		}

		return substr( $text, 0, self::TEXT_MAX );
	}
}
