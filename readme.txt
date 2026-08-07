=== TBT Matching Games ===
Contributors: aparatofan
Tags: education, matching game, openai, shortcode, quiz
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create editable AI-assisted matching games, publish them at standalone URLs, and embed them with shortcodes.

== Description ==

TBT Matching Games adds a Matching Games post type to WordPress. Administrators can generate 4–12 matching pairs from a topic through the OpenAI API, edit all content, save drafts, publish standalone games, and embed games with a shortcode.

Teachers can also build games entirely on the front end with the [tbt_matching_generator] and [tbt_matching_games] shortcodes, share them with a QR code, and never open wp-admin.

The front-end interaction supports drag-and-drop in either direction, click matching, keyboard access, attempts, restart, completion feedback, mobile layouts, and multiple games on one page.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin.
2. Activate TBT Matching Games.
3. Configure the OpenAI API key as a PHP constant or through the documented filter.
4. Open Matching Games > Add New.

== Changelog ==

= 0.3.1 =
* Fixed teachers with the TBT Swipe capability being denied access to the matching tools.
* Buttons follow the Swipe pill style; the library row spine uses the Learn English colour.

= 0.3.0 =
* Aligned the front-end tool pages with The Blue Tree Style Book v1.0.
* Fixed delete buttons and error notices using the Learn English colour instead of error red.
* Added the canonical Tool Hero, the tbt_matching_games_hero filter and a hero="no" attribute.

= 0.2.0 =
* Added the [tbt_matching_generator] and [tbt_matching_games] front-end teaching tools.
* Added an owner-scoped REST CRUD API and the tbt_use_teaching_tools access gate.
* Added QR code sharing, duplicate, and trash from the front-end library.
* Replaced the generation throttle with a per-user daily counter.

= 0.1.1 =
* Fixed a drag ghost and card highlight that could stay on screen after a drag ended.

= 0.1.0 =
* Initial MVP.
