<?php
/**
 * Game persistence.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Game_Repository {
	public const META_KEY = '_tbtmg_game_data';
	private Game_Validator $validator;
	private array $request_cache = array();

	public function __construct( Game_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Default game data.
	 *
	 * @return array
	 */
	public static function default_data(): array {
		$settings = array(
			'shuffle_on_load' => true,
			'show_attempts'   => true,
			'allow_drag'      => true,
			'allow_click'     => true,
			'show_restart'    => true,
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'tbt_matching_games_default_settings', $settings );
			if ( is_array( $filtered ) ) {
				$settings = array_merge( $settings, array_intersect_key( $filtered, $settings ) );
			}
		}

		return array(
			'schema_version'     => 1,
			'title'              => '',
			'topic'              => '',
			'eyebrow'            => __( 'Matching Challenge', 'tbt-matching-games' ),
			'instructions'       => __( 'Match each item on the left with its partner on the right. Drag a card onto its partner or select the cards by clicking them.', 'tbt-matching-games' ),
			'left_column_title'  => __( 'Left side', 'tbt-matching-games' ),
			'right_column_title' => __( 'Right side', 'tbt-matching-games' ),
			'completion_title'   => __( 'Excellent work!', 'tbt-matching-games' ),
			'completion_message' => __( 'You matched all the pairs.', 'tbt-matching-games' ),
			'pairs'              => array(),
			'settings'           => $settings,
			'generation'         => array(
				'generated_by_ai' => false,
				'model'           => '',
				'prompt_version'  => 1,
				'generated_at'    => '',
				'request_id'      => '',
			),
		);
	}

	/**
	 * Read canonical game data.
	 *
	 * @param int $post_id Game post ID.
	 * @return array
	 */
	public function get( int $post_id ): array {
		if ( isset( $this->request_cache[ $post_id ] ) ) {
			return $this->request_cache[ $post_id ];
		}

		$stored = get_post_meta( $post_id, self::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();
		$data   = array_replace_recursive( self::default_data(), $stored );
		$data['title'] = get_the_title( $post_id );

		$this->request_cache[ $post_id ] = $data;
		return $data;
	}

	/**
	 * Save validated game data.
	 *
	 * @param int   $post_id Game post ID.
	 * @param array $raw Raw payload.
	 * @return array|\WP_Error
	 */
	public function save( int $post_id, array $raw ) {
		$validated = $this->validator->validate( $raw );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$title = $validated['title'];
		unset( $validated['title'] );

		update_post_meta( $post_id, self::META_KEY, $validated );
		$this->request_cache[ $post_id ] = array_merge( $validated, array( 'title' => $title ) );

		return $this->request_cache[ $post_id ];
	}

	/**
	 * Save a sanitised but incomplete payload.
	 *
	 * Draft saves from the front-end tools take this path: the data is cleaned
	 * exactly as a full save would clean it, but the completeness rules are not
	 * enforced, so a teacher never loses work by saving early.
	 *
	 * @param int   $post_id Game post ID.
	 * @param array $raw Raw payload.
	 * @return array
	 */
	public function save_partial( int $post_id, array $raw ): array {
		$sanitised = $this->validator->sanitise( $raw );
		$title     = $sanitised['title'];
		unset( $sanitised['title'] );

		update_post_meta( $post_id, self::META_KEY, $sanitised );
		$this->request_cache[ $post_id ] = array_merge( $sanitised, array( 'title' => $title ) );

		return $this->request_cache[ $post_id ];
	}

	/**
	 * Determine whether the current visitor may view a game.
	 *
	 * @param \WP_Post $post Game post.
	 * @return bool
	 */
	public function can_view( \WP_Post $post ): bool {
		if ( 'publish' === $post->post_status ) {
			return true;
		}

		return current_user_can( 'read_post', $post->ID );
	}
}
