<?php
/**
 * Shared game renderer.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Renderer {
	private Game_Repository $repository;
	private Assets $assets;

	public function __construct( Game_Repository $repository, Assets $assets ) {
		$this->repository = $repository;
		$this->assets     = $assets;
	}

	/**
	 * Render a game.
	 *
	 * @param int   $post_id Game post ID.
	 * @param array $args Display arguments.
	 * @return string
	 */
	public function render( int $post_id, array $args = array() ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type || ! $this->repository->can_view( $post ) ) {
			return current_user_can( 'edit_posts' ) ? '<p class="tbtmg-admin-error">' . esc_html__( 'This matching game is unavailable.', 'tbt-matching-games' ) . '</p>' : '';
		}

		$data = apply_filters( 'tbt_matching_games_game_data', $this->repository->get( $post_id ), $post_id );
		if ( empty( $data['pairs'] ) || ! is_array( $data['pairs'] ) ) {
			return current_user_can( 'edit_post', $post_id ) ? '<p class="tbtmg-admin-error">' . esc_html__( 'This game has no valid pairs yet.', 'tbt-matching-games' ) . '</p>' : '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'show_title'        => true,
				'show_instructions' => true,
				'compact'           => false,
			)
		);

		$this->assets->enqueue_game();
		$instance_id = wp_unique_id( 'tbtmg-instance-' );
		$config      = array(
			'instanceId'        => $instance_id,
			'title'             => $data['title'],
			'instructions'      => $data['instructions'],
			'leftColumnTitle'   => $data['left_column_title'],
			'rightColumnTitle'  => $data['right_column_title'],
			'completionTitle'   => $data['completion_title'],
			'completionMessage' => $data['completion_message'],
			'pairs'             => array_values( $data['pairs'] ),
			'settings'          => $data['settings'],
			'labels'            => array(
				'matched'          => __( 'matched', 'tbt-matching-games' ),
				'attempts'         => __( 'Attempts', 'tbt-matching-games' ),
				'restart'          => __( 'Shuffle & restart', 'tbt-matching-games' ),
				'dragOrClick'      => __( 'Drag or click', 'tbt-matching-games' ),
				'firstSelected'    => __( 'First card selected. Choose its matching card.', 'tbt-matching-games' ),
				'selectionChanged' => __( 'Selection changed. Choose a card from the other column.', 'tbt-matching-games' ),
				'correct'          => __( 'Correct match.', 'tbt-matching-games' ),
				'incorrect'        => __( 'Not a match. Try again.', 'tbt-matching-games' ),
				'complete'         => __( 'Game complete in %d attempts.', 'tbt-matching-games' ),
				'leftCard'         => __( 'Left item', 'tbt-matching-games' ),
				'rightCard'        => __( 'Right item', 'tbt-matching-games' ),
			),
		);

		do_action( 'tbt_matching_games_before_render', $post_id, $data, $args );

		ob_start();
		include TBTMG_DIR . 'templates/game.php';
		$html = (string) ob_get_clean();

		return $html;
	}
}
