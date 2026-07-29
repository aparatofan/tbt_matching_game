<?php
/**
 * WordPress admin interface.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private Game_Repository $repository;
	private Game_Validator $validator;
	private OpenAI_Client $openai;

	public function __construct( Game_Repository $repository, Game_Validator $validator, OpenAI_Client $openai ) {
		$this->repository = $repository;
		$this->validator  = $validator;
		$this->openai     = $openai;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save_post' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'guard_publish' ), 20, 2 );
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Register editor meta boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'tbtmg-generator',
			__( 'Generate game content', 'tbt-matching-games' ),
			array( $this, 'render_generator_box' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'tbtmg-details',
			__( 'Game text', 'tbt-matching-games' ),
			array( $this, 'render_details_box' ),
			Post_Type::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'tbtmg-pairs',
			__( 'Matching pairs', 'tbt-matching-games' ),
			array( $this, 'render_pairs_box' ),
			Post_Type::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'tbtmg-share',
			__( 'Share this game', 'tbt-matching-games' ),
			array( $this, 'render_share_box' ),
			Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Load admin assets only on matching-game edit screens.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'tbtmg-admin', TBTMG_URL . 'assets/css/admin.css', array(), TBTMG_VERSION );
		wp_enqueue_script( 'tbtmg-admin', TBTMG_URL . 'assets/js/admin.js', array(), TBTMG_VERSION, true );
		wp_localize_script(
			'tbtmg-admin',
			'TBTMGAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'tbt-matching-games/v1/generate' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'minPairs'=> Game_Validator::MIN_PAIRS,
				'maxPairs'=> Game_Validator::MAX_PAIRS,
				'strings' => array(
					'generating'       => __( 'Generating your matching game…', 'tbt-matching-games' ),
					'generate'         => __( 'Generate game', 'tbt-matching-games' ),
					'generated'        => __( 'The content is ready. Review and edit it before publishing.', 'tbt-matching-games' ),
					'networkError'     => __( 'The generation request failed. Please try again.', 'tbt-matching-games' ),
					'confirmRegenerate'=> __( 'Replace the current editable content with newly generated content?', 'tbt-matching-games' ),
					'leftItem'         => __( 'Left item', 'tbt-matching-games' ),
					'rightItem'        => __( 'Right item', 'tbt-matching-games' ),
					'moveUp'           => __( 'Move up', 'tbt-matching-games' ),
					'moveDown'         => __( 'Move down', 'tbt-matching-games' ),
					'delete'           => __( 'Delete', 'tbt-matching-games' ),
					'pairRange'        => sprintf(
						/* translators: 1: minimum pairs, 2: maximum pairs. */
						__( 'Use between %1$d and %2$d complete pairs before publishing.', 'tbt-matching-games' ),
						Game_Validator::MIN_PAIRS,
						Game_Validator::MAX_PAIRS
					),
					'copied'           => __( 'Copied.', 'tbt-matching-games' ),
				)
			)
		);
	}

	/**
	 * Generator meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_generator_box( \WP_Post $post ): void {
		$data       = $this->repository->get( $post->ID );
		$pair_count = count( $data['pairs'] );
		if ( $pair_count < Game_Validator::MIN_PAIRS ) {
			$pair_count = 4;
		}
		?>
		<div class="tbtmg-generator-grid">
			<p class="tbtmg-field tbtmg-field--wide">
				<label for="tbtmg-generator-topic"><strong><?php esc_html_e( 'Topic', 'tbt-matching-games' ); ?></strong></label>
				<textarea id="tbtmg-generator-topic" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Example: Reporting verbs, B2/C1', 'tbt-matching-games' ); ?>"><?php echo esc_textarea( $data['topic'] ); ?></textarea>
			</p>
			<p class="tbtmg-field">
				<label for="tbtmg-generator-count"><strong><?php esc_html_e( 'Number of pairs', 'tbt-matching-games' ); ?></strong></label>
				<select id="tbtmg-generator-count">
					<?php for ( $count = Game_Validator::MIN_PAIRS; $count <= Game_Validator::MAX_PAIRS; $count++ ) : ?>
						<option value="<?php echo esc_attr( $count ); ?>" <?php selected( $pair_count, $count ); ?>><?php echo esc_html( $count ); ?></option>
					<?php endfor; ?>
				</select>
			</p>
			<p class="tbtmg-field tbtmg-field--wide">
				<label for="tbtmg-generator-instructions"><strong><?php esc_html_e( 'Additional instructions', 'tbt-matching-games' ); ?></strong></label>
				<textarea id="tbtmg-generator-instructions" rows="4" maxlength="1500" placeholder="<?php esc_attr_e( 'Example: Left side: direct speech. Right side: reported speech. Use British English.', 'tbt-matching-games' ); ?>"></textarea>
			</p>
		</div>
		<div class="tbtmg-generator-actions">
			<button type="button" class="button button-primary button-hero" id="tbtmg-generate"><?php esc_html_e( 'Generate game', 'tbt-matching-games' ); ?></button>
			<span class="spinner" id="tbtmg-generator-spinner" aria-hidden="true"></span>
			<span class="tbtmg-api-status <?php echo $this->openai->has_api_key() ? 'is-ready' : 'is-missing'; ?>">
				<?php echo $this->openai->has_api_key() ? esc_html__( 'OpenAI API key detected', 'tbt-matching-games' ) : esc_html__( 'OpenAI API key not detected', 'tbt-matching-games' ); ?>
			</span>
		</div>
		<div class="tbtmg-generator-message" id="tbtmg-generator-message" role="status" aria-live="polite"></div>
		<?php
	}

	/**
	 * Main text fields.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_details_box( \WP_Post $post ): void {
		$data = $this->repository->get( $post->ID );
		wp_nonce_field( 'tbtmg_save_game', 'tbtmg_nonce' );
		?>
		<div class="tbtmg-details-grid">
			<?php $this->textarea_field( 'topic', __( 'Topic', 'tbt-matching-games' ), $data['topic'], 3, 500 ); ?>
			<?php $this->text_field( 'eyebrow', __( 'Eyebrow / category label', 'tbt-matching-games' ), $data['eyebrow'], 100 ); ?>
			<?php $this->textarea_field( 'instructions', __( 'Student instructions', 'tbt-matching-games' ), $data['instructions'], 4, 1000 ); ?>
			<?php $this->text_field( 'left_column_title', __( 'Left-column title', 'tbt-matching-games' ), $data['left_column_title'], 100 ); ?>
			<?php $this->text_field( 'right_column_title', __( 'Right-column title', 'tbt-matching-games' ), $data['right_column_title'], 100 ); ?>
			<?php $this->text_field( 'completion_title', __( 'Completion heading', 'tbt-matching-games' ), $data['completion_title'], 300 ); ?>
			<?php $this->textarea_field( 'completion_message', __( 'Completion message', 'tbt-matching-games' ), $data['completion_message'], 3, 300 ); ?>
		</div>
		<input type="hidden" name="tbtmg_generation[generated_by_ai]" id="tbtmg-generation-ai" value="<?php echo esc_attr( ! empty( $data['generation']['generated_by_ai'] ) ? '1' : '0' ); ?>">
		<input type="hidden" name="tbtmg_generation[model]" id="tbtmg-generation-model" value="<?php echo esc_attr( $data['generation']['model'] ); ?>">
		<input type="hidden" name="tbtmg_generation[prompt_version]" id="tbtmg-generation-prompt" value="<?php echo esc_attr( $data['generation']['prompt_version'] ); ?>">
		<input type="hidden" name="tbtmg_generation[generated_at]" id="tbtmg-generation-time" value="<?php echo esc_attr( $data['generation']['generated_at'] ); ?>">
		<input type="hidden" name="tbtmg_generation[request_id]" id="tbtmg-generation-request" value="<?php echo esc_attr( $data['generation']['request_id'] ); ?>">
		<?php
	}

	/**
	 * Pair editor.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_pairs_box( \WP_Post $post ): void {
		$data  = $this->repository->get( $post->ID );
		$pairs = $data['pairs'];
		if ( empty( $pairs ) ) {
			$pairs = array_fill( 0, Game_Validator::MIN_PAIRS, array( 'left' => '', 'right' => '' ) );
		}
		?>
		<p class="description"><?php esc_html_e( 'Every item should have one unambiguous partner. You can edit all generated text before saving.', 'tbt-matching-games' ); ?></p>
		<div class="tbtmg-pair-validation" id="tbtmg-pair-validation" role="status" aria-live="polite"></div>
		<div class="tbtmg-pairs" id="tbtmg-pairs">
			<?php foreach ( $pairs as $index => $pair ) : ?>
				<?php $this->render_pair_row( $index, $pair ); ?>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button" id="tbtmg-add-pair"><?php esc_html_e( 'Add pair', 'tbt-matching-games' ); ?></button></p>
		<?php
	}

	/**
	 * Sharing box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_share_box( \WP_Post $post ): void {
		$shortcode = sprintf( '[tbt_matching_game id="%d"]', $post->ID );
		?>
		<p><strong><?php esc_html_e( 'Shortcode', 'tbt-matching-games' ); ?></strong></p>
		<div class="tbtmg-copy-row">
			<input type="text" readonly class="widefat" value="<?php echo esc_attr( $shortcode ); ?>" data-tbtmg-copy-source>
			<button type="button" class="button" data-tbtmg-copy><?php esc_html_e( 'Copy', 'tbt-matching-games' ); ?></button>
		</div>
		<?php if ( 'auto-draft' !== $post->post_status ) : ?>
			<p><a class="button button-secondary" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View standalone game', 'tbt-matching-games' ); ?></a></p>
		<?php endif; ?>
		<hr>
		<p class="description"><?php esc_html_e( 'Save as a draft to review privately, or publish when the content is approved.', 'tbt-matching-games' ); ?></p>
		<p><button type="button" class="button" id="tbtmg-save-draft"><?php esc_html_e( 'Approve & save draft', 'tbt-matching-games' ); ?></button></p>
		<p><button type="button" class="button button-primary" id="tbtmg-publish-game"><?php esc_html_e( 'Approve & publish / update', 'tbt-matching-games' ); ?></button></p>
		<?php
	}

	/**
	 * Save game meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is an update.
	 * @return void
	 */
	public function save_post( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		$payload = $this->payload_from_request_values( $post_id, $post->post_title );
		$result  = $this->repository->save( $post_id, $payload );
		if ( is_wp_error( $result ) ) {
			$this->set_notice( $result->get_error_message(), 'error' );
			return;
		}

		do_action( 'tbt_matching_games_after_save', $post_id, $result );
	}

	/**
	 * Keep invalid games from being published.
	 *
	 * @param array $data Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public function guard_publish( array $data, array $postarr ): array {
		if ( Post_Type::POST_TYPE !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		$post_id = absint( $postarr['ID'] ?? 0 );
		if ( isset( $_POST['tbtmg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbtmg_nonce'] ) ), 'tbtmg_save_game' ) ) {
			$payload = $this->payload_from_request_values( $post_id, (string) ( $data['post_title'] ?? '' ) );
		} elseif ( $post_id ) {
			$payload = $this->repository->get( $post_id );
		} else {
			$payload = array();
		}

		$result = is_array( $payload ) ? $this->validator->validate( $payload ) : new \WP_Error( 'tbtmg_invalid_payload', __( 'The game data is invalid.', 'tbt-matching-games' ) );
		if ( is_wp_error( $result ) ) {
			$data['post_status'] = 'draft';
			$this->set_notice(
				sprintf( /* translators: %s: validation message. */ __( 'The game was kept as a draft: %s', 'tbt-matching-games' ), $result->get_error_message() ),
				'error'
			);
		}

		return $data;
	}

	/**
	 * Display deferred validation notice.
	 *
	 * @return void
	 */
	public function admin_notice(): void {
		$key    = 'tbtmg_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$type = 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $notice['message'] ) );
	}

	/**
	 * Custom list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'        => $columns['cb'] ?? '<input type="checkbox" />',
			'title'     => __( 'Title', 'tbt-matching-games' ),
			'tbtmg_pairs' => __( 'Pairs', 'tbt-matching-games' ),
			'tbtmg_shortcode' => __( 'Shortcode', 'tbt-matching-games' ),
			'tbtmg_status' => __( 'Status', 'tbt-matching-games' ),
			'date'      => __( 'Modified', 'tbt-matching-games' ),
		);
	}

	/**
	 * Render custom column data.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'tbtmg_pairs' === $column ) {
			echo esc_html( count( $this->repository->get( $post_id )['pairs'] ) );
		} elseif ( 'tbtmg_shortcode' === $column ) {
			$shortcode = sprintf( '[tbt_matching_game id="%d"]', $post_id );
			echo '<code>' . esc_html( $shortcode ) . '</code> <button type="button" class="button-link" data-tbtmg-copy data-tbtmg-copy-value="' . esc_attr( $shortcode ) . '">' . esc_html__( 'Copy', 'tbt-matching-games' ) . '</button>';
		} elseif ( 'tbtmg_status' === $column ) {
			$status = get_post_status_object( get_post_status( $post_id ) );
			echo esc_html( $status ? $status->label : '' );
		}
	}

	/**
	 * Render one pair row.
	 *
	 * @param int   $index Row index.
	 * @param array $pair Pair data.
	 * @return void
	 */
	private function render_pair_row( int $index, array $pair ): void {
		?>
		<div class="tbtmg-pair-row" data-tbtmg-pair>
			<div class="tbtmg-pair-number" data-tbtmg-number><?php echo esc_html( $index + 1 ); ?></div>
			<p class="tbtmg-field">
				<label><span><?php esc_html_e( 'Left item', 'tbt-matching-games' ); ?></span>
				<textarea rows="3" maxlength="500" name="tbtmg_pairs[<?php echo esc_attr( $index ); ?>][left]" data-tbtmg-left><?php echo esc_textarea( $pair['left'] ?? '' ); ?></textarea></label>
			</p>
			<p class="tbtmg-field">
				<label><span><?php esc_html_e( 'Right item', 'tbt-matching-games' ); ?></span>
				<textarea rows="3" maxlength="500" name="tbtmg_pairs[<?php echo esc_attr( $index ); ?>][right]" data-tbtmg-right><?php echo esc_textarea( $pair['right'] ?? '' ); ?></textarea></label>
			</p>
			<div class="tbtmg-pair-actions">
				<button type="button" class="button" data-tbtmg-up aria-label="<?php esc_attr_e( 'Move pair up', 'tbt-matching-games' ); ?>">↑</button>
				<button type="button" class="button" data-tbtmg-down aria-label="<?php esc_attr_e( 'Move pair down', 'tbt-matching-games' ); ?>">↓</button>
				<button type="button" class="button-link-delete" data-tbtmg-delete><?php esc_html_e( 'Delete', 'tbt-matching-games' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a text field.
	 *
	 * @param string $key Data key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param int    $max Max length.
	 * @return void
	 */
	private function text_field( string $key, string $label, string $value, int $max ): void {
		printf(
			'<p class="tbtmg-field"><label for="tbtmg-%1$s"><strong>%2$s</strong></label><input class="widefat" type="text" id="tbtmg-%1$s" name="tbtmg_data[%1$s]" maxlength="%3$d" value="%4$s"></p>',
			esc_attr( $key ),
			esc_html( $label ),
			absint( $max ),
			esc_attr( $value )
		);
	}

	/**
	 * Render a textarea field.
	 *
	 * @param string $key Data key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param int    $rows Rows.
	 * @param int    $max Max length.
	 * @return void
	 */
	private function textarea_field( string $key, string $label, string $value, int $rows, int $max ): void {
		printf(
			'<p class="tbtmg-field"><label for="tbtmg-%1$s"><strong>%2$s</strong></label><textarea class="widefat" id="tbtmg-%1$s" name="tbtmg_data[%1$s]" rows="%3$d" maxlength="%4$d">%5$s</textarea></p>',
			esc_attr( $key ),
			esc_html( $label ),
			absint( $rows ),
			absint( $max ),
			esc_textarea( $value )
		);
	}

	/**
	 * Check save permissions and nonce.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function can_save( int $post_id ): bool {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! isset( $_POST['tbtmg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbtmg_nonce'] ) ), 'tbtmg_save_game' ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Build canonical payload from the editor request.
	 *
	 * @param \WP_Post   $post Current post.
	 * @param string|null $title_override Optional title.
	 * @return array
	 */
	private function payload_from_request_values( int $post_id, string $fallback_title ): array {
		$details    = isset( $_POST['tbtmg_data'] ) && is_array( $_POST['tbtmg_data'] ) ? wp_unslash( $_POST['tbtmg_data'] ) : array();
		$pairs      = isset( $_POST['tbtmg_pairs'] ) && is_array( $_POST['tbtmg_pairs'] ) ? wp_unslash( $_POST['tbtmg_pairs'] ) : array();
		$generation = isset( $_POST['tbtmg_generation'] ) && is_array( $_POST['tbtmg_generation'] ) ? wp_unslash( $_POST['tbtmg_generation'] ) : array();
		$existing   = $post_id ? $this->repository->get( $post_id ) : Game_Repository::default_data();
		$title      = isset( $_POST['post_title'] ) ? wp_unslash( $_POST['post_title'] ) : $fallback_title;

		return array_merge(
			$existing,
			$details,
			array(
				'title'      => $title,
				'pairs'      => array_values( $pairs ),
				'generation' => array_merge( $existing['generation'], $generation ),
			)
		);
	}

	/**
	 * Store a one-time admin notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type Notice type.
	 * @return void
	 */
	private function set_notice( string $message, string $type ): void {
		set_transient(
			'tbtmg_notice_' . get_current_user_id(),
			array( 'message' => $message, 'type' => $type ),
			60
		);
	}
}
