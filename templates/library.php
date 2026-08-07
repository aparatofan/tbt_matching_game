<?php
/**
 * Front-end game library.
 *
 * Rows are rendered by tools.js from GET /games so search, pagination and the
 * row actions all read from one owner-scoped source of truth.
 *
 * Available variables: $hero.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbtmg_uid = 'tbtmg-lib-' . wp_unique_id();
?>
<div class="tbt tbt-tool tbtmg-tool tbtmg-library" data-tbtmg-tool="library">

	<?php require TBTMG_DIR . 'templates/tool-hero.php'; ?>

	<div class="tbtmg-library__head">
		<div class="tbtmg-field tbtmg-field--search">
			<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-search"><?php esc_html_e( 'Search your games', 'tbt-matching-games' ); ?></label>
			<input
				type="search"
				id="<?php echo esc_attr( $tbtmg_uid ); ?>-search"
				data-tbtmg-search
				placeholder="<?php esc_attr_e( 'Title or topic', 'tbt-matching-games' ); ?>"
				autocomplete="off"
			>
		</div>
	</div>

	<div class="tbtmg-notice" data-tbtmg-notice role="status" aria-live="polite" hidden></div>

	<div class="tbtmg-library__list" data-tbtmg-list aria-live="polite" aria-busy="false"></div>

	<nav class="tbtmg-pagination" data-tbtmg-pagination aria-label="<?php esc_attr_e( 'Game library pages', 'tbt-matching-games' ); ?>" hidden></nav>
</div>
