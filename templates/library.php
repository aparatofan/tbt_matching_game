<?php
/**
 * Front-end game library.
 *
 * Rows are rendered by tools.js from GET /games so search, pagination and the
 * row actions all read from one owner-scoped source of truth.
 *
 * Available variables: $hero, $generator_url.
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
?>
<div class="tbt tbt-tool tbtmg-tool tbtmg-library" data-tbtmg-tool="library" data-tbtmg-generator-url="<?php echo esc_url( $tbtmg_generator_url ); ?>">

	<?php require TBTMG_DIR . 'templates/tool-hero.php'; ?>

	<div class="tbtmg-section-head">
		<h2 class="tbtmg-section-title"><?php esc_html_e( 'Your games', 'tbt-matching-games' ); ?></h2>
		<span class="tbtmg-section-rule" aria-hidden="true"></span>
	</div>

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

		<div class="tbtmg-field tbtmg-field--level">
			<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-level"><?php esc_html_e( 'Level', 'tbt-matching-games' ); ?></label>
			<select id="<?php echo esc_attr( $tbtmg_uid ); ?>-level" data-tbtmg-level-filter>
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
			<button type="button" class="tbtmg-button tbtmg-button--primary tbtmg-library__create" data-tbtmg-create>
				<?php esc_html_e( 'Create new', 'tbt-matching-games' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<div class="tbtmg-notice" data-tbtmg-notice role="status" aria-live="polite" hidden></div>

	<div class="tbtmg-library__list" data-tbtmg-list aria-live="polite" aria-busy="false"></div>

	<nav class="tbtmg-pagination" data-tbtmg-pagination aria-label="<?php esc_attr_e( 'Game library pages', 'tbt-matching-games' ); ?>" hidden></nav>
</div>
