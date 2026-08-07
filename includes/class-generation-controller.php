<?php
/**
 * REST controller for AI generation.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Generation_Controller {
	/**
	 * The default number of generations one user gets per window.
	 */
	public const DEFAULT_LIMIT = 20;

	/**
	 * Site-wide limit option, matching the TBT Swipe naming.
	 */
	public const LIMIT_OPTION = 'tbtmg_max_generations_per_day';

	/**
	 * Per-user override meta. A numeric value wins over the site option, so a
	 * subscription tier can raise one teacher's limit without a schema change.
	 */
	public const LIMIT_META = 'tbtmg_max_generations_per_day';

	/**
	 * Prefix for the per-window counter meta.
	 */
	public const COUNT_META_PREFIX = 'tbtmg_gen_count_';

	private OpenAI_Client $openai;
	private Game_Validator $validator;

	public function __construct( OpenAI_Client $openai, Game_Validator $validator ) {
		$this->openai    = $openai;
		$this->validator = $validator;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the generation route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'tbt-matching-games/v1',
			'/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'topic'                   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => static function ( $value ): bool {
							$length = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
							return '' !== trim( (string) $value ) && $length <= 500;
						},
					),
					'pair_count'              => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => array( Game_Validator::class, 'valid_pair_count' ),
					),
					'additional_instructions' => array(
						'required'          => false,
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => static function ( $value ): bool {
							$length = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
							return $length <= 1500;
						},
					),
				),
			)
		);
	}

	/**
	 * Check generation permission.
	 *
	 * @return bool|\WP_Error
	 */
	public function permissions_check() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'tbtmg_not_logged_in', __( 'You must be logged in to generate matching games.', 'tbt-matching-games' ), array( 'status' => 401 ) );
		}

		if ( ! Access::can_generate() ) {
			return new \WP_Error( 'tbtmg_forbidden', __( 'You are not allowed to generate matching games.', 'tbt-matching-games' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Generate content without saving or publishing it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate( \WP_REST_Request $request ) {
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$topic       = (string) $request->get_param( 'topic' );
		$pair_count  = absint( $request->get_param( 'pair_count' ) );
		$instructions = (string) $request->get_param( 'additional_instructions' );

		do_action( 'tbt_matching_games_before_generate', $topic, $pair_count, $instructions, get_current_user_id() );
		$result = $this->openai->generate( $topic, $pair_count, $instructions );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$raw_game = array_merge(
			$result['game'],
			array(
				'topic'      => $topic,
				'generation' => array_merge( $result['generation'], array( 'generated_by_ai' => true ) ),
			)
		);
		$validated = $this->validator->validate_generated( $raw_game, $pair_count );
		if ( is_wp_error( $validated ) ) {
			$validated->add_data( array( 'status' => 422, 'request_id' => $result['generation']['request_id'] ?? '' ) );
			return $validated;
		}

		do_action( 'tbt_matching_games_after_generate', $validated, get_current_user_id() );

		// Only a successful generation consumes quota: a failed API call is not
		// the teacher's fault and must not cost them a lesson's worth of tries.
		self::record_generation( get_current_user_id() );

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'game'       => $validated,
				'generation' => $validated['generation'],
				'remaining'  => self::generations_remaining( get_current_user_id() ),
			),
			200
		);
	}

	/**
	 * The counter window in seconds. A day by default.
	 *
	 * @return int
	 */
	public static function window(): int {
		return max( 60, absint( apply_filters( 'tbt_matching_games_generation_window', DAY_IN_SECONDS ) ) );
	}

	/**
	 * The generation limit for one user. 0 means unlimited.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function limit_for_user( int $user_id ): int {
		$override = get_user_meta( $user_id, self::LIMIT_META, true );
		if ( '' !== $override && is_numeric( $override ) ) {
			$limit = max( 0, (int) $override );
		} else {
			$limit = max( 0, (int) get_option( self::LIMIT_OPTION, self::DEFAULT_LIMIT ) );
		}

		return max( 0, absint( apply_filters( 'tbt_matching_games_generation_limit', $limit, $user_id ) ) );
	}

	/**
	 * Meta key holding the current window's counter.
	 *
	 * Keyed by site-local date in the default daily case so a teacher's day
	 * matches their own timezone rather than UTC.
	 *
	 * @return string
	 */
	public static function count_meta_key(): string {
		$window = self::window();
		if ( DAY_IN_SECONDS === $window ) {
			return self::COUNT_META_PREFIX . current_time( 'Y-m-d' );
		}

		return self::COUNT_META_PREFIX . (int) floor( time() / $window );
	}

	/**
	 * Generations this user has made in the current window.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function generations_used( int $user_id ): int {
		return (int) get_user_meta( $user_id, self::count_meta_key(), true );
	}

	/**
	 * Generations left in the current window. Null means unlimited.
	 *
	 * @param int $user_id User ID.
	 * @return int|null
	 */
	public static function generations_remaining( int $user_id ): ?int {
		$limit = self::limit_for_user( $user_id );
		if ( 0 === $limit ) {
			return null;
		}

		return max( 0, $limit - self::generations_used( $user_id ) );
	}

	/**
	 * Record one successful generation.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function record_generation( int $user_id ): void {
		update_user_meta( $user_id, self::count_meta_key(), self::generations_used( $user_id ) + 1 );
	}

	/**
	 * Enforce the per-user generation quota.
	 *
	 * @return true|\WP_Error
	 */
	private function check_rate_limit() {
		$remaining = self::generations_remaining( get_current_user_id() );
		if ( null === $remaining || $remaining > 0 ) {
			return true;
		}

		return new \WP_Error(
			'tbtmg_local_rate_limit',
			__( 'You have used this period\'s generation limit. Please try again later.', 'tbt-matching-games' ),
			array( 'status' => 429 )
		);
	}
}
