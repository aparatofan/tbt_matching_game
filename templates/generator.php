<?php
/**
 * Front-end generator tool.
 *
 * Available variables: $game_id, $data, $status, $permalink, $can_generate, $denied, $hero.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbtmg_uid = 'tbtmg-gen-' . wp_unique_id();

/*
 * Stages are numbered as they are rendered, so a teacher without generation
 * rights sees Stage 1, 2 rather than a gap where the AI stage would have been.
 * The game title belongs to whichever stage comes first, for the same reason.
 */
$tbtmg_stage = 0;
?>
<div class="tbt tbt-tool tbtmg-tool tbtmg-generator" data-tbtmg-tool="generator" data-tbtmg-game-id="<?php echo esc_attr( (string) $game_id ); ?>">

	<?php require TBTMG_DIR . 'templates/tool-hero.php'; ?>

	<?php if ( ! empty( $denied ) ) : ?>
		<p class="tbtmg-notice tbtmg-notice--error"><?php esc_html_e( 'That game belongs to another teacher, so a new game was started instead.', 'tbt-matching-games' ); ?></p>
	<?php endif; ?>

	<div class="tbtmg-notice" data-tbtmg-notice role="status" aria-live="polite" hidden></div>

	<?php if ( ! empty( $can_generate ) ) : ?>
	<?php $tbtmg_stage++; ?>
	<section class="tbtmg-panel is-open" data-tbtmg-panel>
		<h2 class="tbtmg-panel__heading">
			<button type="button" class="tbtmg-panel__toggle" aria-expanded="true" aria-controls="<?php echo esc_attr( $tbtmg_uid ); ?>-generator">
				<span class="tbtmg-panel__stage">
					<?php
					printf(
						/* translators: %d: stage number. */
						esc_html__( 'Stage %d:', 'tbt-matching-games' ),
						(int) $tbtmg_stage
					);
					?>
				</span>
				<span class="tbtmg-panel__name"><?php esc_html_e( 'Create the game', 'tbt-matching-games' ); ?></span>
				<span class="tbtmg-panel__chevron" aria-hidden="true"></span>
			</button>
		</h2>
		<div class="tbtmg-panel__body" id="<?php echo esc_attr( $tbtmg_uid ); ?>-generator">
			<p class="tbtmg-hint"><?php esc_html_e( 'Name the game, then describe what it should test.', 'tbt-matching-games' ); ?></p>

			<div class="tbtmg-field">
				<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-title"><?php esc_html_e( 'Game title', 'tbt-matching-games' ); ?></label>
				<input
					type="text"
					id="<?php echo esc_attr( $tbtmg_uid ); ?>-title"
					data-tbtmg-field="title"
					maxlength="200"
					placeholder="<?php esc_attr_e( 'Example: Reporting verbs — B2', 'tbt-matching-games' ); ?>"
					value="<?php echo esc_attr( $game_id ? $data['title'] : '' ); ?>"
				>
			</div>

			<div class="tbtmg-field">
				<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-topic"><?php esc_html_e( 'Topic', 'tbt-matching-games' ); ?></label>
				<textarea id="<?php echo esc_attr( $tbtmg_uid ); ?>-topic" data-tbtmg-generator="topic" rows="2" maxlength="500" placeholder="<?php esc_attr_e( 'Example: Reporting verbs, B2/C1', 'tbt-matching-games' ); ?>"><?php echo esc_textarea( $data['topic'] ); ?></textarea>
			</div>
			<div class="tbtmg-field tbtmg-field--narrow">
				<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-count"><?php esc_html_e( 'Number of pairs', 'tbt-matching-games' ); ?></label>
				<select id="<?php echo esc_attr( $tbtmg_uid ); ?>-count" data-tbtmg-generator="count">
					<?php
					$tbtmg_selected = count( $data['pairs'] ) >= Game_Validator::MIN_PAIRS ? count( $data['pairs'] ) : 6;
					for ( $tbtmg_count = Game_Validator::MIN_PAIRS; $tbtmg_count <= Game_Validator::MAX_PAIRS; $tbtmg_count++ ) :
						?>
						<option value="<?php echo esc_attr( (string) $tbtmg_count ); ?>" <?php selected( $tbtmg_selected, $tbtmg_count ); ?>><?php echo esc_html( (string) $tbtmg_count ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
			<div class="tbtmg-field">
				<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-instructions"><?php esc_html_e( 'Additional instructions', 'tbt-matching-games' ); ?></label>
				<textarea id="<?php echo esc_attr( $tbtmg_uid ); ?>-instructions" data-tbtmg-generator="instructions" rows="3" maxlength="1500" placeholder="<?php esc_attr_e( 'Example: Left side: direct speech. Right side: reported speech. British English.', 'tbt-matching-games' ); ?>"></textarea>
			</div>
			<div class="tbtmg-actions">
				<button type="button" class="tbtmg-button tbtmg-button--primary" data-tbtmg-generate><?php esc_html_e( 'Generate game', 'tbt-matching-games' ); ?></button>
				<span class="tbtmg-status" data-tbtmg-generate-status role="status" aria-live="polite"></span>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php $tbtmg_stage++; ?>
	<section class="tbtmg-panel" data-tbtmg-panel>
		<h2 class="tbtmg-panel__heading">
			<button type="button" class="tbtmg-panel__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $tbtmg_uid ); ?>-wording">
				<span class="tbtmg-panel__stage">
					<?php
					printf(
						/* translators: %d: stage number. */
						esc_html__( 'Stage %d:', 'tbt-matching-games' ),
						(int) $tbtmg_stage
					);
					?>
				</span>
				<span class="tbtmg-panel__name"><?php esc_html_e( 'Wording', 'tbt-matching-games' ); ?></span>
				<span class="tbtmg-panel__chevron" aria-hidden="true"></span>
			</button>
		</h2>
		<div class="tbtmg-panel__body" id="<?php echo esc_attr( $tbtmg_uid ); ?>-wording" hidden>
			<?php if ( empty( $can_generate ) ) : ?>
				<div class="tbtmg-field">
					<label for="<?php echo esc_attr( $tbtmg_uid ); ?>-title"><?php esc_html_e( 'Game title', 'tbt-matching-games' ); ?></label>
					<input
						type="text"
						id="<?php echo esc_attr( $tbtmg_uid ); ?>-title"
						data-tbtmg-field="title"
						maxlength="200"
						placeholder="<?php esc_attr_e( 'Example: Reporting verbs — B2', 'tbt-matching-games' ); ?>"
						value="<?php echo esc_attr( $game_id ? $data['title'] : '' ); ?>"
					>
				</div>
			<?php endif; ?>
			<?php
			$tbtmg_fields = array(
				'topic'              => array( __( 'Topic', 'tbt-matching-games' ), 'textarea', 500 ),
				'eyebrow'            => array( __( 'Eyebrow / category label', 'tbt-matching-games' ), 'text', 100 ),
				'instructions'       => array( __( 'Student instructions', 'tbt-matching-games' ), 'textarea', 1000 ),
				'left_column_title'  => array( __( 'Left-column title', 'tbt-matching-games' ), 'text', 100 ),
				'right_column_title' => array( __( 'Right-column title', 'tbt-matching-games' ), 'text', 100 ),
				'completion_title'   => array( __( 'Completion heading', 'tbt-matching-games' ), 'text', 300 ),
				'completion_message' => array( __( 'Completion message', 'tbt-matching-games' ), 'textarea', 300 ),
			);

			foreach ( $tbtmg_fields as $tbtmg_key => $tbtmg_field ) :
				list( $tbtmg_label, $tbtmg_type, $tbtmg_max ) = $tbtmg_field;
				$tbtmg_id = $tbtmg_uid . '-' . str_replace( '_', '-', $tbtmg_key );
				?>
				<div class="tbtmg-field">
					<label for="<?php echo esc_attr( $tbtmg_id ); ?>"><?php echo esc_html( $tbtmg_label ); ?></label>
					<?php if ( 'textarea' === $tbtmg_type ) : ?>
						<textarea id="<?php echo esc_attr( $tbtmg_id ); ?>" data-tbtmg-field="<?php echo esc_attr( $tbtmg_key ); ?>" rows="2" maxlength="<?php echo esc_attr( (string) $tbtmg_max ); ?>"><?php echo esc_textarea( $data[ $tbtmg_key ] ); ?></textarea>
					<?php else : ?>
						<input type="text" id="<?php echo esc_attr( $tbtmg_id ); ?>" data-tbtmg-field="<?php echo esc_attr( $tbtmg_key ); ?>" maxlength="<?php echo esc_attr( (string) $tbtmg_max ); ?>" value="<?php echo esc_attr( $data[ $tbtmg_key ] ); ?>">
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<?php $tbtmg_stage++; ?>
	<section class="tbtmg-panel" data-tbtmg-panel>
		<h2 class="tbtmg-panel__heading">
			<button type="button" class="tbtmg-panel__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $tbtmg_uid ); ?>-pairs">
				<span class="tbtmg-panel__stage">
					<?php
					printf(
						/* translators: %d: stage number. */
						esc_html__( 'Stage %d:', 'tbt-matching-games' ),
						(int) $tbtmg_stage
					);
					?>
				</span>
				<span class="tbtmg-panel__name"><?php esc_html_e( 'Pairs', 'tbt-matching-games' ); ?></span>
				<span class="tbtmg-panel__count" data-tbtmg-pair-count></span>
				<span class="tbtmg-panel__chevron" aria-hidden="true"></span>
			</button>
		</h2>
		<div class="tbtmg-panel__body" id="<?php echo esc_attr( $tbtmg_uid ); ?>-pairs" hidden>
			<p class="tbtmg-hint"><?php esc_html_e( 'Every item needs one unambiguous partner. You can edit all generated text before saving.', 'tbt-matching-games' ); ?></p>
			<div class="tbtmg-pair-validation" data-tbtmg-pair-validation role="status" aria-live="polite"></div>
			<ol class="tbtmg-pairs" data-tbtmg-pairs></ol>
			<button type="button" class="tbtmg-button" data-tbtmg-add-pair><?php esc_html_e( 'Add pair', 'tbt-matching-games' ); ?></button>
		</div>
	</section>

	<div class="tbtmg-actions tbtmg-actions--save">
		<button type="button" class="tbtmg-button tbtmg-button--primary tbtmg-button--large" data-tbtmg-save><?php esc_html_e( 'Save game', 'tbt-matching-games' ); ?></button>
		<span class="tbtmg-status" data-tbtmg-save-status role="status" aria-live="polite"></span>
	</div>

	<div class="tbtmg-share" data-tbtmg-share hidden></div>

	<script type="application/json" data-tbtmg-initial-pairs><?php echo wp_json_encode( $game_id ? array_values( $data['pairs'] ) : array(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
</div>
