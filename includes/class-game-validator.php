<?php
/**
 * Game data validation and sanitisation.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Game_Validator {
	public const MIN_PAIRS = 4;
	public const MAX_PAIRS = 12;

	/**
	 * Validate and sanitise generated game data.
	 *
	 * @param array $raw Raw game data.
	 * @param int   $expected_count Exact expected pair count.
	 * @return array|\WP_Error
	 */
	public function validate_generated( array $raw, int $expected_count ) {
		$result = $this->validate( $raw, $expected_count );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['generation']['generated_by_ai'] = true;
		return $result;
	}

	/**
	 * Validate and sanitise a complete game payload.
	 *
	 * @param array    $raw Raw game data.
	 * @param int|null $expected_count Optional exact pair count.
	 * @return array|\WP_Error
	 */
	public function validate( array $raw, ?int $expected_count = null ) {
		$title = $this->clean_text( $raw['title'] ?? '', 200 );
		if ( '' === $title ) {
			return new \WP_Error( 'tbtmg_missing_title', __( 'The game title cannot be empty.', 'tbt-matching-games' ) );
		}

		$topic = $this->clean_textarea( $raw['topic'] ?? '', 500 );
		$pairs = isset( $raw['pairs'] ) && is_array( $raw['pairs'] ) ? array_values( $raw['pairs'] ) : array();
		$count = count( $pairs );

		if ( null !== $expected_count && $count !== $expected_count ) {
			return new \WP_Error(
				'tbtmg_wrong_pair_count',
				sprintf(
					/* translators: 1: generated count, 2: expected count. */
					__( 'The game contains %1$d pairs instead of %2$d.', 'tbt-matching-games' ),
					$count,
					$expected_count
				)
			);
		}

		if ( $count < self::MIN_PAIRS || $count > self::MAX_PAIRS ) {
			return new \WP_Error(
				'tbtmg_pair_count_range',
				sprintf(
					/* translators: 1: minimum pairs, 2: maximum pairs. */
					__( 'A game must contain between %1$d and %2$d pairs.', 'tbt-matching-games' ),
					self::MIN_PAIRS,
					self::MAX_PAIRS
				)
			);
		}

		$clean_pairs = array();
		$left_seen   = array();
		$right_seen  = array();

		foreach ( $pairs as $index => $pair ) {
			$row_number = $index + 1;
			if ( ! is_array( $pair ) ) {
				return new \WP_Error(
					'tbtmg_invalid_pair',
					sprintf( /* translators: %d: pair row number. */ __( 'Pair %d is invalid.', 'tbt-matching-games' ), $row_number )
				);
			}

			$left  = $this->clean_textarea( $pair['left'] ?? '', 500 );
			$right = $this->clean_textarea( $pair['right'] ?? '', 500 );

			if ( '' === $left || '' === $right ) {
				return new \WP_Error(
					'tbtmg_empty_pair',
					sprintf( /* translators: %d: pair row number. */ __( 'Both sides of pair %d must contain text.', 'tbt-matching-games' ), $row_number )
				);
			}

			$left_key  = $this->normalise_duplicate_key( $left );
			$right_key = $this->normalise_duplicate_key( $right );

			if ( isset( $left_seen[ $left_key ] ) ) {
				return new \WP_Error(
					'tbtmg_duplicate_left',
					sprintf( /* translators: %d: pair row number. */ __( 'The left side of pair %d duplicates another item.', 'tbt-matching-games' ), $row_number )
				);
			}

			if ( isset( $right_seen[ $right_key ] ) ) {
				return new \WP_Error(
					'tbtmg_duplicate_right',
					sprintf( /* translators: %d: pair row number. */ __( 'The right side of pair %d duplicates another item.', 'tbt-matching-games' ), $row_number )
				);
			}

			$left_seen[ $left_key ]   = true;
			$right_seen[ $right_key ] = true;
			$clean_pairs[]            = array(
				'id'    => 'pair_' . $row_number,
				'left'  => $left,
				'right' => $right,
			);
		}

		$defaults = Game_Repository::default_data();
		$settings = isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array();
		$settings = array_merge( $defaults['settings'], array_intersect_key( $settings, $defaults['settings'] ) );
		foreach ( $settings as $key => $value ) {
			$settings[ $key ] = (bool) $value;
		}

		$generation = isset( $raw['generation'] ) && is_array( $raw['generation'] ) ? $raw['generation'] : array();

		return array(
			'schema_version'    => 1,
			'title'             => $title,
			'topic'             => $topic,
			'eyebrow'           => $this->clean_text( $raw['eyebrow'] ?? $defaults['eyebrow'], 100 ),
			'instructions'      => $this->clean_textarea( $raw['instructions'] ?? $defaults['instructions'], 1000 ),
			'left_column_title' => $this->clean_text( $raw['left_column_title'] ?? $defaults['left_column_title'], 100 ),
			'right_column_title'=> $this->clean_text( $raw['right_column_title'] ?? $defaults['right_column_title'], 100 ),
			'completion_title'  => $this->clean_text( $raw['completion_title'] ?? $defaults['completion_title'], 300 ),
			'completion_message'=> $this->clean_textarea( $raw['completion_message'] ?? $defaults['completion_message'], 300 ),
			'pairs'             => $clean_pairs,
			'settings'          => $settings,
			'generation'        => array(
				'generated_by_ai' => ! empty( $generation['generated_by_ai'] ),
				'model'           => $this->clean_text( $generation['model'] ?? '', 100 ),
				'prompt_version'  => absint( $generation['prompt_version'] ?? 1 ),
				'generated_at'    => $this->clean_text( $generation['generated_at'] ?? '', 60 ),
				'request_id'      => $this->clean_text( $generation['request_id'] ?? '', 200 ),
			),
		);
	}

	/**
	 * Validate a pair count.
	 *
	 * @param mixed $value Pair count.
	 * @return bool
	 */
	public static function valid_pair_count( $value ): bool {
		$count = absint( $value );
		return $count >= self::MIN_PAIRS && $count <= self::MAX_PAIRS;
	}

	/**
	 * Sanitize one-line text with a length limit.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Character limit.
	 * @return string
	 */
	private function clean_text( $value, int $limit ): string {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return $this->truncate( $value, $limit );
	}

	/**
	 * Sanitize multi-line text with a length limit.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Character limit.
	 * @return string
	 */
	private function clean_textarea( $value, int $limit ): string {
		$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
		return $this->truncate( $value, $limit );
	}

	/**
	 * Truncate safely when mbstring is available.
	 *
	 * @param string $value Text.
	 * @param int    $limit Character limit.
	 * @return string
	 */
	private function truncate( string $value, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}

	/**
	 * Normalise a string for duplicate detection.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	private function normalise_duplicate_key( string $value ): string {
		$value = preg_replace( '/\s+/u', ' ', trim( $value ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}
}
