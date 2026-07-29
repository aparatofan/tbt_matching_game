<?php
/**
 * Matching game shortcode.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	private Renderer $renderer;

	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_shortcode( 'tbt_matching_game', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'                => 0,
				'show_title'        => 'yes',
				'show_instructions' => 'yes',
				'compact'           => 'no',
			),
			is_array( $atts ) ? $atts : array(),
			'tbt_matching_game'
		);

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return '';
		}

		return $this->renderer->render(
			$post_id,
			array(
				'show_title'        => 'no' !== strtolower( (string) $atts['show_title'] ),
				'show_instructions' => 'no' !== strtolower( (string) $atts['show_instructions'] ),
				'compact'           => 'yes' === strtolower( (string) $atts['compact'] ),
			)
		);
	}
}
