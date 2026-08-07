<?php
/**
 * Front-end teaching tool shortcodes.
 *
 * [tbt_matching_generator] builds and edits a game, [tbt_matching_games] lists
 * the teacher's own games. Both are gated the same way and share one asset
 * bundle, so Mariusz can put them on one Divi page or two.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tools_Shortcode {
	public const GENERATOR_SHORTCODE = 'tbt_matching_generator';
	public const LIBRARY_SHORTCODE   = 'tbt_matching_games';

	/**
	 * The white TBT mark used in the hero, shared with the player's hero.
	 */
	public const LOGO_URL = 'https://thebluetree.pl/wp-content/uploads/2020/12/TBT-white-logo.png';

	private Game_Repository $repository;
	private Assets $assets;

	public function __construct( Game_Repository $repository, Assets $assets ) {
		$this->repository = $repository;
		$this->assets     = $assets;
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_shortcode( self::GENERATOR_SHORTCODE, array( $this, 'render_generator' ) );
		add_shortcode( self::LIBRARY_SHORTCODE, array( $this, 'render_library' ) );
	}

	/**
	 * Render the generator tool.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_generator( $atts = array() ): string {
		$gate = $this->gate();
		if ( null !== $gate ) {
			return $gate;
		}

		$game_id = $this->requested_game_id();
		$data    = $game_id ? $this->repository->get( $game_id ) : Game_Repository::default_data();
		$post    = $game_id ? get_post( $game_id ) : null;

		$this->assets->enqueue_tools( $game_id );

		return $this->template(
			'generator.php',
			array(
				'game_id'      => $game_id,
				'data'         => $data,
				'status'       => $post instanceof \WP_Post ? $post->post_status : '',
				'permalink'    => $game_id ? (string) get_permalink( $game_id ) : '',
				'can_generate' => Access::can_generate(),
				'denied'       => $this->requested_but_denied(),
				'hero'         => $this->hero( 'generator', $atts ),
			)
		);
	}

	/**
	 * Render the library tool.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_library( $atts = array() ): string {
		$gate = $this->gate();
		if ( null !== $gate ) {
			return $gate;
		}

		$this->assets->enqueue_tools();

		return $this->template( 'library.php', array( 'hero' => $this->hero( 'library', $atts ) ) );
	}

	/**
	 * Hero copy for a tool page, or null when the page suppresses it.
	 *
	 * @param string       $context Either 'generator' or 'library'.
	 * @param array|string $atts Shortcode attributes.
	 * @return array|null
	 */
	private function hero( string $context, $atts ): ?array {
		$atts = shortcode_atts(
			array( 'hero' => 'yes' ),
			is_array( $atts ) ? $atts : array(),
			'generator' === $context ? self::GENERATOR_SHORTCODE : self::LIBRARY_SHORTCODE
		);

		if ( 'no' === strtolower( (string) $atts['hero'] ) ) {
			return null;
		}

		$defaults = 'library' === $context
			? array(
				'eyebrow' => __( 'The Blue Tree Teacher Tools', 'tbt-matching-games' ),
				'title'   => __( 'MY MATCHING GAMES', 'tbt-matching-games' ),
				'support' => __( 'Everything you have created', 'tbt-matching-games' ),
				'logo'    => self::LOGO_URL,
			)
			: array(
				'eyebrow' => __( 'The Blue Tree Teacher Tools', 'tbt-matching-games' ),
				'title'   => __( 'MATCHING GAME', 'tbt-matching-games' ),
				'support' => __( 'Create a matching game for your class', 'tbt-matching-games' ),
				'logo'    => self::LOGO_URL,
			);

		/**
		 * Filter the Tool Hero copy.
		 *
		 * @param array  $hero    Eyebrow, title, support line and logo URL.
		 * @param string $context Either 'generator' or 'library'.
		 */
		$hero = apply_filters( 'tbt_matching_games_hero', $defaults, $context );
		$hero = is_array( $hero ) ? array_merge( $defaults, $hero ) : $defaults;

		return array(
			'eyebrow' => (string) $hero['eyebrow'],
			'title'   => (string) $hero['title'],
			'support' => (string) $hero['support'],
			'logo'    => (string) $hero['logo'],
		);
	}

	/**
	 * The generator page URL, used by the library's edit links.
	 *
	 * @return string
	 */
	public static function generator_url(): string {
		$default = '';
		$post    = get_post();
		if ( $post instanceof \WP_Post ) {
			$default = (string) get_permalink( $post );
		}

		/**
		 * Filter the URL of the page holding [tbt_matching_generator].
		 *
		 * Defaults to the current page, which is right when both shortcodes
		 * share one page and wrong when they do not — hence the filter.
		 *
		 * @param string $url Generator page URL.
		 */
		return (string) apply_filters( 'tbt_matching_games_generator_url', $default );
	}

	/**
	 * Access gate. Returns markup to show instead of the tool, or null to proceed.
	 *
	 * @return string|null
	 */
	private function gate(): ?string {
		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( $this->current_url() );

			return sprintf(
				'<div class="tbt tbt-tool tbtmg-tool tbtmg-tool--gate"><p>%1$s</p><p><a class="tbtmg-button tbtmg-button--primary" href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Log in to your teacher account to build matching games.', 'tbt-matching-games' ),
				esc_url( $login ),
				esc_html__( 'Log in', 'tbt-matching-games' )
			);
		}

		if ( ! Access::can_use_tools() ) {
			$default = sprintf(
				'<div class="tbt tbt-tool tbtmg-tool tbtmg-tool--gate"><p>%s</p></div>',
				esc_html__( 'The TBT Teaching Tools are part of a teacher subscription. Your account does not include them yet.', 'tbt-matching-games' )
			);

			/**
			 * Filter the upsell shown to a logged-in user without access.
			 *
			 * Returns raw HTML so the Polish copy and the call to action can be
			 * set from a snippet without editing the plugin.
			 *
			 * @param string $html    Default upsell markup.
			 * @param int    $user_id Current user ID.
			 */
			return (string) apply_filters( 'tbt_matching_games_upsell_html', $default, get_current_user_id() );
		}

		return null;
	}

	/**
	 * The game requested for editing, when the current user may edit it.
	 *
	 * @return int
	 */
	private function requested_game_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
		$game_id = isset( $_GET['game_id'] ) ? absint( wp_unslash( $_GET['game_id'] ) ) : 0;
		if ( ! $game_id ) {
			return 0;
		}

		$post = get_post( $game_id );
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type ) {
			return 0;
		}

		return Access::can_edit( $game_id ) ? $game_id : 0;
	}

	/**
	 * Was a game requested that the current user may not edit?
	 *
	 * @return bool
	 */
	private function requested_but_denied(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
		$requested = isset( $_GET['game_id'] ) ? absint( wp_unslash( $_GET['game_id'] ) ) : 0;

		return $requested > 0 && 0 === $this->requested_game_id();
	}

	/**
	 * Current front-end URL, for the login redirect.
	 *
	 * @return string
	 */
	private function current_url(): string {
		$post = get_post();

		return $post instanceof \WP_Post ? (string) get_permalink( $post ) : home_url( '/' );
	}

	/**
	 * Render a tool template.
	 *
	 * @param string $file Template file name.
	 * @param array  $vars Template variables.
	 * @return string
	 */
	private function template( string $file, array $vars ): string {
		$path = TBTMG_DIR . 'templates/' . $file;
		if ( ! file_exists( $path ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template variables.
		extract( $vars, EXTR_SKIP );
		include $path;

		return (string) ob_get_clean();
	}
}
