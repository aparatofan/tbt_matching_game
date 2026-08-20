<?php
/**
 * Shared matching game markup.
 *
 * Available variables: $post, $data, $args, $instance_id, $config.
 *
 * @package TBT_Matching_Games
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$root_classes = array( 'tbtmg-game' );
if ( ! empty( $args['compact'] ) ) {
	$root_classes[] = 'tbtmg-game--compact';
}
?>
<div
	class="<?php echo esc_attr( implode( ' ', $root_classes ) ); ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-tbtmg-instance="<?php echo esc_attr( $instance_id ); ?>"
	aria-label="<?php echo esc_attr( sprintf( /* translators: %s: game title. */ __( '%s matching game', 'tbt-matching-games' ), $data['title'] ) ); ?>"
>
	<?php if ( ! empty( $args['show_title'] ) || ! empty( $args['show_instructions'] ) ) : ?>
		<header class="tbtmg-hero">
			<div class="tbtmg-hero__content">
				<?php if ( ! empty( $data['eyebrow'] ) ) : ?>
					<p class="tbtmg-eyebrow"><?php echo esc_html( $data['eyebrow'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $args['show_title'] ) ) : ?>
					<h2 class="tbtmg-title"><?php echo esc_html( $data['title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( ! empty( $args['show_instructions'] ) ) : ?>
					<p class="tbtmg-subtitle" id="<?php echo esc_attr( $instance_id ); ?>-instructions"><?php echo esc_html( $data['instructions'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
			/*
			 * The mark comes from TBT Hub's [tbt_tree] shortcode, which inlines the
			 * SVG so its leaves can animate individually. Hub is not a hard
			 * dependency: if it is inactive the shortcode does not exist and the
			 * player falls back to the flat white PNG it used before, so a game
			 * page never renders without a mark.
			 *
			 * Embedded games do not bloom. A lesson page has its own reading
			 * order and a tree unfurling inside it competes with the lesson.
			 */
			if ( shortcode_exists( 'tbt_tree' ) ) {
				$tbtmg_animate = empty( $args['compact'] ) ? 'yes' : 'no';
				echo do_shortcode( sprintf( '[tbt_tree width="190px" animate="%s"]', esc_attr( $tbtmg_animate ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				printf(
					'<img class="tbtmg-hero__logo" src="%1$s" alt="%2$s" loading="lazy" decoding="async">',
					esc_url( 'https://thebluetree.pl/wp-content/uploads/2020/12/TBT-white-logo.png' ),
					esc_attr__( 'The Blue Tree', 'tbt-matching-games' )
				);
			}
			?>
		</header>
	<?php endif; ?>

	<section class="tbtmg-toolbar" aria-label="<?php esc_attr_e( 'Game controls', 'tbt-matching-games' ); ?>">
		<div class="tbtmg-status">
			<span class="tbtmg-pill"><span data-tbtmg-matched>0</span>&nbsp;/&nbsp;<?php echo esc_html( count( $data['pairs'] ) ); ?> <span><?php esc_html_e( 'matched', 'tbt-matching-games' ); ?></span></span>
			<?php if ( ! empty( $data['settings']['show_attempts'] ) ) : ?>
				<span class="tbtmg-pill"><?php esc_html_e( 'Attempts', 'tbt-matching-games' ); ?>: <span data-tbtmg-attempts>0</span></span>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $data['settings']['show_restart'] ) ) : ?>
			<button class="tbtmg-button tbtmg-button--primary" type="button" data-tbtmg-reset><?php esc_html_e( 'Shuffle & restart', 'tbt-matching-games' ); ?></button>
		<?php endif; ?>
	</section>

	<p class="tbtmg-sr-only" aria-live="polite" data-tbtmg-live></p>

	<section class="tbtmg-board" aria-describedby="<?php echo esc_attr( $instance_id ); ?>-instructions">
		<svg
			class="tbtmg-connections"
			data-tbtmg-connections
			aria-hidden="true"
			focusable="false"
			preserveAspectRatio="none"
		></svg>
		<div class="tbtmg-column">
			<div class="tbtmg-column-heading">
				<h3><?php echo esc_html( $data['left_column_title'] ); ?></h3>
				<span><?php esc_html_e( 'Drag or click', 'tbt-matching-games' ); ?></span>
			</div>
			<div class="tbtmg-card-list" data-tbtmg-list="left"></div>
		</div>
		<div class="tbtmg-column">
			<div class="tbtmg-column-heading">
				<h3><?php echo esc_html( $data['right_column_title'] ); ?></h3>
				<span><?php esc_html_e( 'Drag or click', 'tbt-matching-games' ); ?></span>
			</div>
			<div class="tbtmg-card-list" data-tbtmg-list="right"></div>
		</div>
		<div class="tbtmg-result" data-tbtmg-result data-state="success" hidden role="status" aria-live="polite">
			<div class="tbtmg-result__panel">
				<div class="tbtmg-result__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<h3 class="tbtmg-result__title"><?php echo esc_html( $data['completion_title'] ); ?></h3>
				<p class="tbtmg-result__message"><?php echo esc_html( $data['completion_message'] ); ?></p>
				<p class="tbtmg-result__attempts" data-tbtmg-result-attempts></p>
				<p class="tbtmg-result__hint"><?php esc_html_e( 'Tap anywhere to continue', 'tbt-matching-games' ); ?></p>
				<div class="tbtmg-result__timer" aria-hidden="true"><i></i></div>
			</div>
		</div>
	</section>

	<script type="application/json" class="tbtmg-game-data"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
</div>
<?php do_action( 'tbt_matching_games_after_render', $post->ID, $data, $args ); ?>
