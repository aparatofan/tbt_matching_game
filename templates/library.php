<?php
/**
 * Front-end game library.
 *
 * Rows are rendered by tools.js from GET /games so search, pagination and the
 * row actions all read from one owner-scoped source of truth.
 *
 * Available variables: $hero, $generator_url, $total.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbtmg_uid = 'tbtmg-lib-' . wp_unique_id();

/*
 * The resolved generator URL travels on the markup rather than in the localised
 * config: the bundle is localised once, before any shortcode has run, and two
 * libraries on one page may point at different generators.
 */
$tbtmg_generator_url = isset( $generator_url ) ? (string) $generator_url : '';

/*
 * The owner's game count decides the first paint: an empty library shows its
 * title and the Create button alone, rather than flashing a search bar that has
 * nothing to search.
 */
$tbtmg_total = isset( $total ) ? (int) $total : 0;
$tbtmg_empty = 0 === $tbtmg_total;
?>
<div class="tbt tbt-tool tbtmg-tool tbtmg-library" data-tbtmg-tool="library" data-tbtmg-generator-url="<?php echo esc_url( $tbtmg_generator_url ); ?>">

	<?php require TBTMG_DIR . 'templates/tool-hero.php'; ?>

	<div class="tbtmg-libbar<?php echo $tbtmg_empty ? ' is-empty' : ''; ?>" data-tbtmg-libbar data-tbtmg-total="<?php echo esc_attr( (string) $tbtmg_total ); ?>">
		<div class="tbtmg-libbar__title">
			<h2 class="tbtmg-section-title"><?php esc_html_e( 'Your games', 'tbt-matching-games' ); ?></h2>
			<span class="tbtmg-section-rule" data-tbtmg-libbar-rule aria-hidden="true"<?php echo $tbtmg_empty ? '' : ' hidden'; ?>></span>
		</div>

		<div class="tbtmg-libbar__filter" role="search" data-tbtmg-libbar-filter<?php echo $tbtmg_empty ? ' hidden' : ''; ?>>
			<div class="tbtmg-libbar__search">
				<label class="tbtmg-sr" for="<?php echo esc_attr( $tbtmg_uid ); ?>-search"><?php esc_html_e( 'Search your games', 'tbt-matching-games' ); ?></label>
				<svg class="tbtmg-libbar__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
					<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2.2"/>
					<path d="m20 20-3.6-3.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
				<input type="search" id="<?php echo esc_attr( $tbtmg_uid ); ?>-search" class="tbtmg-libbar__input" data-tbtmg-search
					placeholder="<?php esc_attr_e( 'Search by game or topic', 'tbt-matching-games' ); ?>"
					autocomplete="off" spellcheck="false">
				<button type="button" class="tbtmg-libbar__clear" data-tbtmg-search-clear
					aria-label="<?php esc_attr_e( 'Clear search', 'tbt-matching-games' ); ?>" hidden>&times;</button>
			</div>

			<label class="tbtmg-sr" for="<?php echo esc_attr( $tbtmg_uid ); ?>-level"><?php esc_html_e( 'Level', 'tbt-matching-games' ); ?></label>
			<select id="<?php echo esc_attr( $tbtmg_uid ); ?>-level" class="tbtmg-libbar__select" data-tbtmg-level-filter>
				<option value=""><?php esc_html_e( 'All levels', 'tbt-matching-games' ); ?></option>
				<?php foreach ( Levels::band_names() as $tbtmg_band => $tbtmg_band_name ) : ?>
					<?php
					$tbtmg_band_label = sprintf(
						/* translators: 1: CEFR band code, e.g. B2. 2: plain-English band name, e.g. upper-intermediate. */
						__( '%1$s · %2$s', 'tbt-matching-games' ),
						$tbtmg_band,
						$tbtmg_band_name
					);
					?>
					<option value="<?php echo esc_attr( $tbtmg_band ); ?>"><?php echo esc_html( $tbtmg_band_label ); ?></option>
				<?php endforeach; ?>
				<option value="none"><?php esc_html_e( 'Not set', 'tbt-matching-games' ); ?></option>
			</select>
		</div>

		<?php
		/*
		 * No generator URL, no button: creating a game would have nowhere to
		 * land. The generator's own title field still creates one wherever the
		 * generator itself is reachable.
		 */
		?>
		<?php if ( '' !== $tbtmg_generator_url ) : ?>
			<button type="button" class="tbtmg-button tbtmg-button--primary tbtmg-button--large tbtmg-libbar__cta" data-tbtmg-create>
				<?php esc_html_e( 'Create new game', 'tbt-matching-games' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<p class="tbtmg-libbar__summary" data-tbtmg-summary aria-live="polite" hidden>
		<span data-tbtmg-summary-text></span>
		<button type="button" class="tbtmg-libbar__link" data-tbtmg-reset><?php esc_html_e( 'Clear filters', 'tbt-matching-games' ); ?></button>
	</p>

	<div class="tbtmg-notice" data-tbtmg-notice role="status" aria-live="polite" hidden></div>

	<div class="tbtmg-library__list" data-tbtmg-list aria-live="polite" aria-busy="false"></div>

	<nav class="tbtmg-pagination" data-tbtmg-pagination aria-label="<?php esc_attr_e( 'Game library pages', 'tbt-matching-games' ); ?>" hidden></nav>
</div>
