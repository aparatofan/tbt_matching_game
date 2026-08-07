<?php
/**
 * Canonical Tool Hero (Style Book v1.0 §6B).
 *
 * Available variables: $hero (eyebrow, title, support).
 *
 * @package TBT_Matching_Games
 */

namespace TBT\MatchingGames;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $hero ) || ! is_array( $hero ) ) {
	return;
}
?>
<header class="tbt-tool-hero">
	<?php if ( '' !== (string) $hero['eyebrow'] ) : ?>
		<p class="tbt-tool-hero__eyebrow"><?php echo esc_html( $hero['eyebrow'] ); ?></p>
	<?php endif; ?>
	<h1 class="tbt-tool-hero__title"><?php echo esc_html( $hero['title'] ); ?></h1>
	<?php if ( '' !== (string) $hero['support'] ) : ?>
		<p class="tbt-tool-hero__support"><?php echo esc_html( $hero['support'] ); ?></p>
	<?php endif; ?>
</header>
