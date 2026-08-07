<?php
/**
 * Plugin bootstrap.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;
	private bool $booted = false;
	private Game_Repository $repository;
	private Renderer $renderer;

	/**
	 * Return the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin services.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$validator        = new Game_Validator();
		$this->repository = new Game_Repository( $validator );
		$assets           = new Assets();
		$this->renderer   = new Renderer( $this->repository, $assets );
		$openai           = new OpenAI_Client();

		( new Post_Type() )->hooks();
		$assets->hooks();
		( new Shortcode( $this->renderer ) )->hooks();
		( new Template_Loader() )->hooks();
		( new Generation_Controller( $openai, $validator ) )->hooks();
		( new Games_Controller( $this->repository, $validator ) )->hooks();
		( new Admin( $this->repository, $validator, $openai ) )->hooks();

		// Activation does not fire for an already-active plugin, so an existing
		// install picks the capability up here instead.
		add_action( 'init', array( Access::class, 'maybe_add_caps' ), 5 );
	}

	/**
	 * Get renderer service.
	 *
	 * @return Renderer
	 */
	public function renderer(): Renderer {
		return $this->renderer;
	}

	/**
	 * Get repository service.
	 *
	 * @return Game_Repository
	 */
	public function repository(): Game_Repository {
		return $this->repository;
	}
}
