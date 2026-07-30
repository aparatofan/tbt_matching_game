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
			<img
				class="tbtmg-hero__logo"
				src="https://thebluetree.pl/wp-content/uploads/2020/12/TBT-white-logo.png"
				alt="<?php esc_attr_e( 'The Blue Tree', 'tbt-matching-games' ); ?>"
				loading="lazy"
				decoding="async"
			>
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
	</section>

	<section class="tbtmg-completion" data-tbtmg-completion hidden aria-live="polite">
		<h3><?php echo esc_html( $data['completion_title'] ); ?></h3>
		<p><?php echo esc_html( $data['completion_message'] ); ?></p>
		<p class="tbtmg-completion-attempts" data-tbtmg-completion-attempts></p>
	</section>

	<script type="application/json" class="tbtmg-game-data"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
</div>
<?php do_action( 'tbt_matching_games_after_render', $post->ID, $data, $args ); ?>
