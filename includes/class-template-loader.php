<?php
/**
 * Front-end template loading.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Template_Loader {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'template_include', array( $this, 'template_include' ) );
	}

	/**
	 * Load the standalone game template.
	 *
	 * @param string $template Existing template.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return $template;
		}

		$theme_template = locate_template( 'tbt-matching-games/single-game.php' );
		if ( $theme_template ) {
			return $theme_template;
		}

		return TBTMG_DIR . 'templates/single-game.php';
	}
}
