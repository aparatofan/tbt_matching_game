<?php
/**
 * REST CRUD for the front-end teaching tools.
 *
 * Every route is gated on Access: the capability decides who may use the tools
 * at all, and post_author decides which games they may touch. The post type
 * capability model is deliberately not involved — wp_insert_post() performs no
 * capability check of its own, so the checks here are the whole story for the
 * front end, and wp-admin keeps behaving exactly as before.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Games_Controller {
	public const REST_NAMESPACE = 'tbt-matching-games/v1';

	private const MAX_PER_PAGE = 50;

	private Game_Repository $repository;
	private Game_Validator $validator;

	public function __construct( Game_Repository $repository, Game_Validator $validator ) {
		$this->repository = $repository;
		$this->validator  = $validator;
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
	 * Register the CRUD routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/games',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'check_tools_access' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'status'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'author'   => array(
							'type'              => 'integer',
							'default'           => -1,
							'sanitize_callback' => static function ( $value ): int {
								return (int) $value;
							},
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_tools_access' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/games/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_item_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_item_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_item_access' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/games/(?P<id>[\d]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'check_item_access' ),
			)
		);
	}

	/**
	 * Gate collection routes on the teaching tools capability.
	 *
	 * @return true|\WP_Error
	 */
	public function check_tools_access() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'tbtmg_not_logged_in', __( 'You must be logged in to manage matching games.', 'tbt-matching-games' ), array( 'status' => 401 ) );
		}

		if ( ! Access::can_use_tools() ) {
			return new \WP_Error( 'tbtmg_forbidden', __( 'Your account does not include the teaching tools.', 'tbt-matching-games' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Gate single-game routes on capability plus ownership.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_item_access( \WP_REST_Request $request ) {
		$access = $this->check_tools_access();
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$post = $this->get_game_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! Access::can_edit( $post->ID ) ) {
			return new \WP_Error( 'tbtmg_not_owner', __( 'This game belongs to another teacher.', 'tbt-matching-games' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * List the current user's games.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_items( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = min( self::MAX_PER_PAGE, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$status   = (string) $request->get_param( 'status' );
		$statuses = in_array( $status, array( 'publish', 'draft' ), true ) ? array( $status ) : array( 'publish', 'draft' );

		$args = array(
			'post_type'      => Post_Type::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			's'              => (string) $request->get_param( 'search' ),
			'author'         => get_current_user_id(),
		);

		// Only an administrator may widen the scope, and only by asking for it.
		if ( 0 === (int) $request->get_param( 'author' ) && Access::can_view_all() ) {
			unset( $args['author'] );
		}

		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->prepare_summary( $post );
		}

		$response = new \WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => $page,
			),
			200
		);
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Read one game.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$post = $this->get_game_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'game'    => $this->prepare_game( $post ),
			),
			200
		);
	}

	/**
	 * Create a game owned by the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( \WP_REST_Request $request ) {
		$payload    = $this->payload_from_request( $request );
		$validation = $this->validator->validate( $payload );
		$publishing = ! is_wp_error( $validation );

		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_title'  => $payload['title'],
				'post_status' => $publishing ? 'publish' : 'draft',
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$post_id->add_data( array( 'status' => 500 ) );
			return $post_id;
		}

		return $this->persist( (int) $post_id, $payload, $validation );
	}

	/**
	 * Update a game the current user owns.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( \WP_REST_Request $request ) {
		$post = $this->get_game_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$payload    = $this->payload_from_request( $request, $post->ID );
		$validation = $this->validator->validate( $payload );

		// post_author is never taken from the request: ownership cannot move.
		$updated = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_title'  => $payload['title'],
				'post_status' => is_wp_error( $validation ) ? 'draft' : 'publish',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			$updated->add_data( array( 'status' => 500 ) );
			return $updated;
		}

		return $this->persist( $post->ID, $payload, $validation );
	}

	/**
	 * Duplicate a game as a draft owned by the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_item( \WP_REST_Request $request ) {
		$post = $this->get_game_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_title'  => sprintf(
					/* translators: %s: original game title. */
					__( '%s (copy)', 'tbt-matching-games' ),
					$post->post_title
				),
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$post_id->add_data( array( 'status' => 500 ) );
			return $post_id;
		}

		$stored = get_post_meta( $post->ID, Game_Repository::META_KEY, true );
		if ( is_array( $stored ) ) {
			update_post_meta( (int) $post_id, Game_Repository::META_KEY, $stored );
		}

		$copy = get_post( (int) $post_id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'game'    => $copy instanceof \WP_Post ? $this->prepare_summary( $copy ) : null,
			),
			201
		);
	}

	/**
	 * Trash a game the current user owns.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( \WP_REST_Request $request ) {
		$post = $this->get_game_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Trash, never force delete: a mis-tap in a live lesson is recoverable.
		$trashed = wp_trash_post( $post->ID );
		if ( ! $trashed ) {
			return new \WP_Error( 'tbtmg_delete_failed', __( 'The game could not be moved to the trash.', 'tbt-matching-games' ), array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'id'      => $post->ID,
			),
			200
		);
	}

	/**
	 * Write the payload and build the save response.
	 *
	 * A payload that fails validation is still stored, as a draft, with the
	 * validation message attached. Losing a teacher's typing would be a worse
	 * outcome than an unpublishable game.
	 *
	 * @param int             $post_id Game post ID.
	 * @param array           $payload Raw payload.
	 * @param array|\WP_Error $validation Validation result.
	 * @return \WP_REST_Response
	 */
	private function persist( int $post_id, array $payload, $validation ): \WP_REST_Response {
		if ( is_wp_error( $validation ) ) {
			$this->repository->save_partial( $post_id, $payload );
			$post = get_post( $post_id );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'status'  => 'draft',
					'message' => $validation->get_error_message(),
					'game'    => $post instanceof \WP_Post ? $this->prepare_game( $post ) : null,
				),
				200
			);
		}

		$this->repository->save( $post_id, $payload );
		$post = get_post( $post_id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'status'  => 'publish',
				'message' => '',
				'game'    => $post instanceof \WP_Post ? $this->prepare_game( $post ) : null,
			),
			200
		);
	}

	/**
	 * Build a canonical payload from the request body.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param int              $post_id Existing game, when updating.
	 * @return array
	 */
	private function payload_from_request( \WP_REST_Request $request, int $post_id = 0 ): array {
		$existing = $post_id ? $this->repository->get( $post_id ) : Game_Repository::default_data();
		$body     = $request->get_json_params();
		$body     = is_array( $body ) ? $body : (array) $request->get_body_params();

		$payload = array_merge( $existing, array_intersect_key( $body, $existing ) );

		$title = isset( $body['title'] ) ? sanitize_text_field( (string) $body['title'] ) : (string) $existing['title'];
		if ( '' === trim( $title ) ) {
			$title = __( 'Untitled matching game', 'tbt-matching-games' );
		}
		$payload['title'] = $title;

		$payload['pairs'] = isset( $body['pairs'] ) && is_array( $body['pairs'] ) ? array_values( $body['pairs'] ) : $existing['pairs'];

		$generation            = isset( $body['generation'] ) && is_array( $body['generation'] ) ? $body['generation'] : array();
		$payload['generation'] = array_merge( $existing['generation'], $generation );

		return $payload;
	}

	/**
	 * Load a game post, or an error explaining why not.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function get_game_post( int $post_id ) {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new \WP_Error( 'tbtmg_not_found', __( 'That matching game does not exist.', 'tbt-matching-games' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Shape one game for a list response.
	 *
	 * @param \WP_Post $post Game post.
	 * @return array
	 */
	private function prepare_summary( \WP_Post $post ): array {
		$data = $this->repository->get( $post->ID );

		return array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'pair_count' => count( $data['pairs'] ),
			'topic'      => $data['topic'],
			'modified'   => get_post_modified_time( 'c', true, $post ),
			'permalink'  => get_permalink( $post ),
			'shortcode'  => sprintf( '[tbt_matching_game id="%d"]', $post->ID ),
		);
	}

	/**
	 * Shape one game for a read or save response.
	 *
	 * @param \WP_Post $post Game post.
	 * @return array
	 */
	private function prepare_game( \WP_Post $post ): array {
		$data = $this->repository->get( $post->ID );

		return array_merge( $data, $this->prepare_summary( $post ) );
	}
}
