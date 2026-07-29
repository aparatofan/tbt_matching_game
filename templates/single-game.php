<?php
/**
 * Standalone matching game template.
 *
 * Themes may override this file at:
 * tbt-matching-games/single-game.php
 *
 * @package TBT_Matching_Games
 */

use TBT\MatchingGames\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main class="tbtmg-standalone" id="primary">
	<div class="tbtmg-standalone__inner">
		<?php echo Plugin::instance()->renderer()->render( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</main>
<?php
get_footer();
