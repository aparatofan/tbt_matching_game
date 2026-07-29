<?php
/**
 * Asset registration and loading.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	private bool $registered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_early' ), 20 );
	}

	/**
	 * Register front-end assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;
		wp_register_style( 'tbtmg-game', TBTMG_URL . 'assets/css/game.css', array(), TBTMG_VERSION );
		wp_register_script( 'tbtmg-game', TBTMG_URL . 'assets/js/game.js', array(), TBTMG_VERSION, true );
	}

	/**
	 * Enqueue before wp_head for predictable standalone and standard-shortcode use.
	 *
	 * @return void
	 */
	public function maybe_enqueue_early(): void {
		if ( is_singular( Post_Type::POST_TYPE ) ) {
			$this->enqueue_game();
			return;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, 'tbt_matching_game' ) ) {
				$this->enqueue_game();
			}
		}
	}

	/**
	 * Enqueue front-end assets when a game is rendered.
	 *
	 * @return void
	 */
	public function enqueue_game(): void {
		$this->register();
		wp_enqueue_style( 'tbtmg-game' );
		wp_enqueue_script( 'tbtmg-game' );

		// Shortcodes inserted by page builders may be discovered after wp_head.
		if ( did_action( 'wp_head' ) && ! wp_style_is( 'tbtmg-game', 'done' ) ) {
			wp_print_styles( 'tbtmg-game' );
		}
	}
}
