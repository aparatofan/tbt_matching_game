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
	private bool $tools_localised = false;

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
		wp_register_style(
			'tbtmg-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@700&family=Roboto+Slab:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_register_style( 'tbtmg-game', TBTMG_URL . 'assets/css/game.css', array( 'tbtmg-fonts' ), TBTMG_VERSION );
		wp_register_script( 'tbtmg-game', TBTMG_URL . 'assets/js/game.js', array(), TBTMG_VERSION, true );

		// The teaching tools are a separate surface from the playable game and
		// never load admin.css / admin.js, which are styled for wp-admin.
		wp_register_style( 'tbtmg-tools', TBTMG_URL . 'assets/css/tools.css', array( 'tbtmg-fonts' ), TBTMG_VERSION );
		wp_register_script( 'tbtmg-qrcode', TBTMG_URL . 'assets/js/lib/qrcode.min.js', array(), TBTMG_VERSION, true );
		wp_register_script( 'tbtmg-tools', TBTMG_URL . 'assets/js/tools.js', array( 'tbtmg-qrcode' ), TBTMG_VERSION, true );
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

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'tbt_matching_game' ) ) {
			$this->enqueue_game();
		}

		$has_generator = has_shortcode( $post->post_content, Tools_Shortcode::GENERATOR_SHORTCODE );
		$has_library   = has_shortcode( $post->post_content, Tools_Shortcode::LIBRARY_SHORTCODE );

		// Nothing to load for a visitor who will only be shown the gate.
		if ( ( $has_generator || $has_library ) && Access::can_use_tools() ) {
			$this->enqueue_tools();
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

	/**
	 * Enqueue the front-end teaching tools bundle.
	 *
	 * Idempotent: both shortcodes call it, and a page builder can render them
	 * after wp_enqueue_scripts has already run.
	 *
	 * @param int $game_id Game being edited, when the generator knows one.
	 * @return void
	 */
	public function enqueue_tools( int $game_id = 0 ): void {
		$this->register();
		wp_enqueue_style( 'tbtmg-tools' );
		wp_enqueue_script( 'tbtmg-tools' );

		if ( ! $this->tools_localised ) {
			$this->tools_localised = true;
			wp_localize_script( 'tbtmg-tools', 'TBTMGTools', $this->tools_data( $game_id ) );
		}

		if ( did_action( 'wp_head' ) && ! wp_style_is( 'tbtmg-tools', 'done' ) ) {
			wp_print_styles( 'tbtmg-tools' );
		}
	}

	/**
	 * Data handed to tools.js.
	 *
	 * @param int $game_id Game being edited.
	 * @return array
	 */
	private function tools_data( int $game_id ): array {
		return array(
			'restBase'     => esc_url_raw( rest_url( Games_Controller::REST_NAMESPACE . '/games' ) ),
			'generateUrl'  => esc_url_raw( rest_url( Games_Controller::REST_NAMESPACE . '/generate' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'minPairs'     => Game_Validator::MIN_PAIRS,
			'maxPairs'     => Game_Validator::MAX_PAIRS,
			'canGenerate'  => Access::can_generate(),
			'gameId'       => $game_id,
			'generatorUrl' => esc_url_raw( Tools_Shortcode::generator_url() ),
			'strings'      => array(
				'generating'      => __( 'Generating — this can take up to 30 seconds.', 'tbt-matching-games' ),
				'generated'       => __( 'Game generated. Review the pairs, then save.', 'tbt-matching-games' ),
				'generateFailed'  => __( 'The game could not be generated. Please try again.', 'tbt-matching-games' ),
				'needTopic'       => __( 'Add a topic first.', 'tbt-matching-games' ),
				'needTitle'       => __( 'Give the game a title.', 'tbt-matching-games' ),
				'saving'          => __( 'Saving…', 'tbt-matching-games' ),
				'savedPublished'  => __( 'Saved and published.', 'tbt-matching-games' ),
				'savedDraft'      => __( 'Saved as a draft.', 'tbt-matching-games' ),
				'saveFailed'      => __( 'The game could not be saved.', 'tbt-matching-games' ),
				'loading'         => __( 'Loading…', 'tbt-matching-games' ),
				'empty'           => __( 'You have not built any games yet.', 'tbt-matching-games' ),
				'emptySearch'     => __( 'No games match that search.', 'tbt-matching-games' ),
				'published'       => __( 'Published', 'tbt-matching-games' ),
				'draft'           => __( 'Draft', 'tbt-matching-games' ),
				'edit'            => __( 'Edit', 'tbt-matching-games' ),
				'share'           => __( 'Share', 'tbt-matching-games' ),
				'duplicate'       => __( 'Duplicate', 'tbt-matching-games' ),
				'delete'          => __( 'Delete', 'tbt-matching-games' ),
				'confirmDelete'   => __( 'Move this game to the trash?', 'tbt-matching-games' ),
				'deleted'         => __( 'Game moved to the trash.', 'tbt-matching-games' ),
				'duplicated'      => __( 'A draft copy was created.', 'tbt-matching-games' ),
				'copy'            => __( 'Copy', 'tbt-matching-games' ),
				'copied'          => __( 'Copied', 'tbt-matching-games' ),
				'gameLink'        => __( 'Game link', 'tbt-matching-games' ),
				'shortcodeLabel'  => __( 'Shortcode for a lesson page', 'tbt-matching-games' ),
				'scanToPlay'      => __( 'Students scan this to play', 'tbt-matching-games' ),
				'draftNoShare'    => __( 'This game is a draft, so it has no public link yet. Complete the pairs and save to publish it.', 'tbt-matching-games' ),
				'pairCount'       => __( '%1$d of %2$d–%3$d pairs', 'tbt-matching-games' ),
				'pairsIncomplete' => __( 'Fill in both sides of every pair.', 'tbt-matching-games' ),
				'left'            => __( 'Left', 'tbt-matching-games' ),
				'right'           => __( 'Right', 'tbt-matching-games' ),
				'moveUp'          => __( 'Move up', 'tbt-matching-games' ),
				'moveDown'        => __( 'Move down', 'tbt-matching-games' ),
				'removePair'      => __( 'Remove pair', 'tbt-matching-games' ),
				'pairLabel'       => __( 'Pair %d', 'tbt-matching-games' ),
				'modified'        => __( 'Edited %s', 'tbt-matching-games' ),
				'networkError'    => __( 'Something went wrong. Check your connection and try again.', 'tbt-matching-games' ),
				'sessionExpired'  => __( 'Your session has expired. Refresh the page and try again.', 'tbt-matching-games' ),
				'quota'           => __( 'You have used this period\'s generation limit.', 'tbt-matching-games' ),
				'prevPage'        => __( 'Previous', 'tbt-matching-games' ),
				'nextPage'        => __( 'Next', 'tbt-matching-games' ),
				'pageOf'          => __( 'Page %1$d of %2$d', 'tbt-matching-games' ),
			),
		);
	}
}
