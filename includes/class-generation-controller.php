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
		$capability = (string) apply_filters( 'tbt_matching_games_generation_capability', 'manage_options' );
		if ( ! current_user_can( $capability ) ) {
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

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'game'       => $validated,
				'generation' => $validated['generation'],
			),
			200
		);
	}

	/**
	 * Enforce a simple per-user generation throttle.
	 *
	 * @return true|\WP_Error
	 */
	private function check_rate_limit() {
		$user_id = get_current_user_id();
		$limit   = max( 1, absint( apply_filters( 'tbt_matching_games_generation_limit', 10 ) ) );
		$window  = max( 60, absint( apply_filters( 'tbt_matching_games_generation_window', 300 ) ) );
		$key     = 'tbtmg_gen_' . $user_id;
		$state   = get_transient( $key );
		$state   = is_array( $state ) ? $state : array( 'count' => 0, 'started' => time() );

		if ( time() - absint( $state['started'] ?? 0 ) >= $window ) {
			$state = array( 'count' => 0, 'started' => time() );
		}

		if ( absint( $state['count'] ?? 0 ) >= $limit ) {
			return new \WP_Error(
				'tbtmg_local_rate_limit',
				__( 'Too many generation requests were made. Please try again shortly.', 'tbt-matching-games' ),
				array( 'status' => 429 )
			);
		}

		$state['count'] = absint( $state['count'] ?? 0 ) + 1;
		set_transient( $key, $state, $window );
		return true;
	}
}
