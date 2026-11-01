=== RMD Site Settings ===
Contributors: reicheltmediadesign
Tags: disable comments, xmlrpc, wp_head, dashboard, settings
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Basic site settings in one place: disable comments, switch off XML-RPC, clean up wp_head and tidy the dashboard.

== Description ==

RMD Site Settings collects basic switches that most business sites need, on one settings page for administrators. Every part is off until you switch it on, except the support widget.

* Disable comments everywhere or for selected post types: comment forms, trackbacks and pingbacks, the Comments menu, discussion settings, comment feeds, the comments REST endpoint, the Recent Comments widget and the comment blocks.
* Optionally keep showing existing comments while no new ones can be written.
* Delete existing comments: all, by post type or spam only. Notes in the block editor are kept.
* Disable XML-RPC completely.
* Remove the emoji script, generator tag, shortlink, oEmbed discovery links, REST API link and RSD link from the page head.
* Dashboard widget "Need help or support?" with the contact details of reichelt media.design (on by default, no external requests).
* Hide the welcome panel, the "WordPress Events and News" and "Quick Draft" widgets and set an own admin footer text.

== Installation ==

1. Upload the plugin zip under Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to Settings → Site settings.

== Frequently Asked Questions ==

= Are notes in the block editor affected? =

No. Notes (WordPress 6.9+) are stored as comments of the type "note"; they keep working and are never deleted.

= What happens when I deactivate the plugin? =

Everything returns to the WordPress defaults. Comments deleted with the delete tool stay deleted.

== Changelog ==

= 0.1.0 =
* Initial release.
