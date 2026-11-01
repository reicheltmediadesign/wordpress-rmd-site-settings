# RMD Site Settings

Basic WordPress site settings in one place, for administrators: disable comments globally or per post type, switch off XML-RPC, clean up `wp_head` and tidy the dashboard. Every switch is off until you turn it on (except the support widget), and deactivating the plugin brings back the WordPress defaults.

## Features

- **Comments** – disable everywhere or for selected post types. Removes comment forms, trackbacks and pingbacks; in "everywhere" mode also the Comments menu, the discussion settings, comment feeds, the `X-Pingback` header, the comments REST endpoint, the comment XML-RPC methods, the Recent Comments widget, comment blocks and the comment parts of the dashboard.
- **Keep existing comments** – optionally keep showing approved comments (and the Comments menu for moderation) while no new comments can be written.
- **Delete comments** – remove existing comments permanently: all, by post type or spam only. Comment counts are recalculated per post.
- **Notes stay intact** – notes in the block editor (WordPress 6.9+) are comments of the type `note`; they keep working and are never deleted.
- **XML-RPC** – switch off `xmlrpc.php` completely.
- **Page head** – remove the emoji script, generator tag, shortlink, oEmbed discovery links, REST API link and RSD link.
- **Support widget** – a dashboard widget "Need help or support?" with the contact details of reichelt media.design. On by default; logo and contact details ship with the plugin, nothing is loaded from outside.
- **Admin area** – hide the welcome panel and the "WordPress Events and News" and "Quick Draft" widgets, set an own admin footer text.

## Usage

Go to **Settings → Site settings**. Each tab is saved on its own. Only users with the `manage_options` capability (administrators) can open the page.

The delete tool is at the bottom of the **Comments** tab. Choose what to delete, tick the confirmation and click *Delete comments*. Make a backup first – deleted comments cannot be restored.

## Settings

### Comments

| Setting | Default | Description |
| --- | --- | --- |
| Comments | Enabled | *Enabled* (WordPress default), *Disabled everywhere* or *Disabled for selected post types*. |
| Post types | – | Post types without comments in the per-post-type mode. |
| Existing comments | off | Keep showing approved comments; the Comments menu stays for moderation. |

| | Everywhere | Selected post types |
| --- | --- | --- |
| Comment form, trackbacks, pingbacks | removed | removed for these types |
| Comment list and comment blocks | removed (unless existing comments are kept) | removed for these types (unless kept) |
| Comments menu and screens | removed (unless existing comments are kept) | stay |
| Discussion settings | removed | stay, with a notice |
| Comment feeds, `X-Pingback` header | removed | stay |
| REST `/wp/v2/comments` (except notes) | 403 | WordPress checks per post |
| XML-RPC `wp.newComment`, pingback methods | removed | stay |
| Recent Comments widget, dashboard comment parts | removed | stay |

### XML-RPC

| Setting | Default | Description |
| --- | --- | --- |
| Disable XML-RPC | off | No XML-RPC methods, pingbacks off, no `X-Pingback` header and RSD link. Leave off for Jetpack or apps that publish via XML-RPC. |

### Page head

| Setting | Default | Description |
| --- | --- | --- |
| Emoji script | keep | Removes the emoji detection script, its styles and the DNS prefetch to `s.w.org`. |
| Generator tag | keep | Hides the WordPress version in the page source and feeds. |
| Shortlink | keep | Removes the `?p=` shortlink from the head and the HTTP headers. |
| oEmbed discovery links | keep | Other sites can still embed posts by URL. |
| REST API link | keep | Removes the link from the head and the HTTP headers; the REST API keeps working. |
| RSD link | keep | Only used by XML-RPC clients. |

### Admin area

| Setting | Default | Description |
| --- | --- | --- |
| Support widget | on | Shows the "Need help or support?" widget with the contact details of reichelt media.design on the dashboard of every user. Each user can hide it under *Screen Options*. |
| Welcome panel | shown | Hides the welcome panel on the dashboard. |
| Events and news | shown | Hides the "WordPress Events and News" widget. |
| Quick draft | shown | Hides the "Quick Draft" widget. |
| Footer text | empty | Replaces "Thank you for creating with WordPress". Allowed HTML: `a`, `strong`, `em`. |

## Privacy

The plugin stores its settings in the option `rmd_ss_settings` and sends no data anywhere. The support widget shows contact details and a logo that ship with the plugin; nothing is loaded from other servers. The only outgoing request is the update check, which reads `update.json` from the GitHub releases of this repository.

## Installation

1. Download `rmd-site-settings.zip` from the [latest release](https://github.com/reicheltmediadesign/wordpress-rmd-site-settings/releases/latest).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip and activate it.
3. Updates appear on the Plugins screen like any other plugin update.

Deleting the plugin removes its options on every site of a network.

## License

GPL-2.0-or-later, © Philipp Reichelt, [reichelt media.design](https://reicheltmedia.design)
