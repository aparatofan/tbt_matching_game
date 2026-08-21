<?php
/**
 * CEFR level model for generation.
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Levels {
	public const BANDS        = array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' );
	public const DEFAULT_BAND = 'B1';
	public const LAST_META    = 'tbtmg_last_level';

	/**
	 * Band code => plain-English name.
	 *
	 * The names match what a TBT Swipe user already sees. Two TBT tools calling
	 * B2 different things is worse than either name being imperfect.
	 *
	 * @return array
	 */
	public static function band_names(): array {
		return array(
			'A1' => __( 'elementary', 'tbt-matching-games' ),
			'A2' => __( 'pre-intermediate', 'tbt-matching-games' ),
			'B1' => __( 'intermediate', 'tbt-matching-games' ),
			'B2' => __( 'upper-intermediate', 'tbt-matching-games' ),
			'C1' => __( 'advanced', 'tbt-matching-games' ),
			'C2' => __( 'proficient', 'tbt-matching-games' ),
		);
	}

	/**
	 * The same names, untranslated, for the prompt.
	 *
	 * prompt_block() is instruction text for the model, so it must not pick up
	 * a teacher's locale.
	 *
	 * @return array
	 */
	private static function band_names_raw(): array {
		return array(
			'A1' => 'elementary',
			'A2' => 'pre-intermediate',
			'B1' => 'intermediate',
			'B2' => 'upper-intermediate',
			'C1' => 'advanced',
			'C2' => 'proficient',
		);
	}

	/**
	 * Reduce anything level-shaped to one of the six bands, or ''.
	 *
	 * TBT Students stores a 25-step scale such as B1.5; a teacher choosing a
	 * generation difficulty needs six choices, not twenty-five, so everything
	 * from the first dot onwards is dropped.
	 *
	 * @param mixed $raw Raw level value.
	 * @return string
	 */
	public static function normalise( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$band = strtoupper( trim( $raw ) );

		$dot = strpos( $band, '.' );
		if ( false !== $dot ) {
			$band = substr( $band, 0, $dot );
		}

		$band = trim( $band );

		if ( 'A0' === $band ) {
			$band = 'A1';
		}

		return in_array( $band, self::BANDS, true ) ? $band : '';
	}

	/**
	 * Normalise, falling back to the default band.
	 *
	 * A bad level must never cost a teacher a generation, so this never errors.
	 *
	 * @param mixed $raw Raw level value.
	 * @return string
	 */
	public static function sanitize( $raw ): string {
		$band = self::normalise( $raw );

		return '' !== $band ? $band : self::DEFAULT_BAND;
	}

	/**
	 * The band this teacher generated at last, or ''.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function last_used( int $user_id ): string {
		return self::normalise( get_user_meta( $user_id, self::LAST_META, true ) );
	}

	/**
	 * Record the band a teacher generated at.
	 *
	 * @param int    $user_id User ID.
	 * @param string $band Band code.
	 * @return void
	 */
	public static function remember( int $user_id, string $band ): void {
		$clean = self::normalise( $band );
		if ( '' === $clean ) {
			return;
		}

		update_user_meta( $user_id, self::LAST_META, $clean );
	}

	/**
	 * The band the picker opens on for this teacher.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function initial_band( int $user_id ): string {
		$band = self::last_used( $user_id );

		return '' !== $band ? $band : self::DEFAULT_BAND;
	}

	/**
	 * Concrete per-band constraints.
	 *
	 * @param string $band Band code.
	 * @return array
	 */
	public static function rules( string $band ): array {
		// Re-sanitised here because this text is interpolated into the prompt;
		// a stray value must not reach the model as an instruction.
		$band = self::sanitize( $band );

		$rules = array(
			'A1' => array(
				'language' => 'the most frequent everyday words; present simple, "can", "there is / there are"',
				'length'   => '1–4 words per side',
				'pairing'  => 'direct and concrete — a word and its plain meaning, a word and its obvious opposite',
				'topics'   => 'home, family, food, the classroom, everyday objects',
			),
			'A2' => array(
				'language' => 'high-frequency everyday vocabulary; past simple, "going to", comparatives',
				'length'   => '2–6 words per side',
				'pairing'  => 'everyday collocations, simple definitions, common fixed phrases',
				'topics'   => 'routine, journeys, shopping, work and school days',
			),
			'B1' => array(
				'language' => 'common general vocabulary including frequent phrasal verbs; present perfect, first conditional, "used to"',
				'length'   => '3–8 words per side',
				'pairing'  => 'definitions, common collocations, two halves of one sentence',
				'topics'   => 'experience, plans and opinions',
			),
			'B2' => array(
				'language' => 'wider vocabulary including less common phrasal verbs and fixed expressions; passives, second and third conditional, relative clauses',
				'length'   => '4–10 words per side',
				'pairing'  => 'near-synonyms told apart by use, register or form; transformations such as direct to reported speech',
				'topics'   => 'abstract, social and hypothetical subject matter',
			),
			'C1' => array(
				'language' => 'specialised, idiomatic and evaluative vocabulary; subordination, hedging, inversion, nominalisation',
				'length'   => '4–12 words per side',
				'pairing'  => 'fine distinctions of nuance, connotation, collocation and register',
				'topics'   => 'professional, academic and critical subject matter',
			),
			'C2' => array(
				'language' => 'the full range, including marked, literary and idiomatic usage',
				'length'   => '4–14 words per side',
				'pairing'  => 'subtle distinctions that reward close reading',
				'topics'   => 'nuanced, allusive and register-sensitive subject matter',
			),
		);

		return isset( $rules[ $band ] ) ? $rules[ $band ] : $rules[ self::DEFAULT_BAND ];
	}

	/**
	 * The <LEVEL> instruction block for the user prompt.
	 *
	 * Deliberately not translated: this is instruction text for the model, not
	 * something a teacher reads.
	 *
	 * @param string $band Band code.
	 * @return string
	 */
	public static function prompt_block( string $band ): string {
		$band  = self::sanitize( $band );
		$rules = self::rules( $band );
		$names = self::band_names_raw();
		$name  = isset( $names[ $band ] ) ? $names[ $band ] : $band;

		$lines = array(
			"Level of the language — CEFR {$band} ({$name}):",
			"- Language ceiling: {$rules['language']}. Do not use vocabulary or structures above this ceiling.",
			"- Item length: {$rules['length']}. These are guardrails, not targets — a natural item a word or two outside the range beats a stilted one inside it.",
			"- What the pairing tests: {$rules['pairing']}.",
			"- Topic range: {$rules['topics']}. The subject matter moves with the level as much as the grammar does: if only the grammar changes and the topic stays generic, two levels become indistinguishable.",
			'- When the topic or the additional instructions name specific target items or a specific structure, keep them exactly as given. The level then shapes the other side of the pair — the definitions, examples and contexts written around those items — never the target items themselves. Do not substitute an easier word for an item the teacher asked for.',
			'- The one-correct-partner rule outranks every line above. If following the level would make two right-hand items plausible for the same left-hand item, simplify until exactly one partner is correct.',
		);

		return implode( "\n", $lines );
	}
}
