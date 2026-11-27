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
- **Menus for other roles** – let roles such as editors edit or view the navigation menus. They get an own *Menus* item in the admin menu; editing uses the WordPress menu screen without opening widgets, the Customizer or theme settings.
- **Admin access for other roles** – give roles access to post types and to admin pages of plugins and the theme they cannot open otherwise, for example a theme options page for editors.
- **Application passwords** – switch them off per role, see which users have application passwords (with role, creation date and last use) and delete selected ones or all of them.
- **Update overview** – all installed plugins and themes with version, pending update, automatic updates and the date of their latest release, so abandoned plugins stand out.

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

If a must-use plugin or another plugin already switches XML-RPC off, the checkbox is shown ticked and greyed out with a note; the stored setting is kept.

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

### Menus

One setting per role that cannot manage menus anyway (administrators always can). Users with several roles get the highest level of their roles. The number next to each role shows how many users have it; hover over it to see their names.

| Setting | Default | Description |
| --- | --- | --- |
| No access | ✓ | WordPress default. |
| View only | | A *Menus* item in the admin menu lists the menu locations and all menus with their items, without editing. |
| Edit | | The *Menus* item opens the WordPress menu screen (*Appearance → Menus*): create, edit and delete menus and assign them to locations. Widgets, the Customizer and theme settings stay closed. |

Only works with themes that register menu locations (classic themes). Block themes manage navigation in the Site Editor instead.

### Admin access

One row per role (administrators always have full access) with two lists. Items a role can open anyway are shown ticked and greyed out. Users with several roles get everything their roles get.

| Setting | Default | Description |
| --- | --- | --- |
| Post types | none | Post types of plugins and the theme that have an own admin menu item. The role gets all capabilities of the post type: list, create, edit, publish and delete, also in the block editor. Post types marked *uses the permissions of posts* (or *pages*) share their capabilities with posts or pages, so the role gets access to those as well. |
| Admin pages | none | Top-level admin menu items of plugins and the theme (`admin.php?page=…`), including their subpages. The role sees the item in the admin menu and can open and save the page; the capabilities the page needs are granted only on that page and when its settings are saved, not elsewhere in the admin area. |

Admin pages are listed as the admin menu looked for an administrator on their last visit to the admin area. Pages that need capabilities for users, roles, plugins, themes, updates or the network cannot be shared (*not possible: needs protected permissions*), and the WordPress settings screens and this plugin's settings cannot be saved this way. Actions of a shared page that run via AJAX or `admin-post.php` may still need the capability itself.

### Security

The tab has two sections.

**Application passwords**

One checkbox per role, administrators included. Users with several roles keep their application passwords as long as one of their roles may use them; users without a role are not affected.

| Setting | Default | Description |
| --- | --- | --- |
| Disable application passwords | off | Users of the role cannot sign in to the REST API or XML-RPC with an application password and no longer see the section in their profile. Existing application passwords stop working but are not deleted; they work again when the role is enabled. |

Below the settings, *Existing application passwords* lists every application password on the site: user, role, name, creation date, last use and whether it is active or disabled (by the role setting or because application passwords are switched off for the whole site). Tick the ones to remove and click *Delete selected*, or click *Delete all*. Deleted application passwords cannot be restored; disabling alone keeps them in the database.

If application passwords are switched off for the whole site (no HTTPS, a must-use plugin or another plugin), a notice says so and the role checkboxes are shown ticked and greyed out; the stored role setting is kept.

**Updates**

Two tables, plugins and themes, with everything that is installed (inactive ones are marked):

| Column | Description |
| --- | --- |
| Version | Installed version. |
| Update | *Up to date*, *Update available* with the new version, or *Unknown: no update source* if neither WordPress.org nor an own updater reports on it. Taken from the last update check of WordPress. |
| Automatic updates | Whether automatic updates are switched on for it. |
| Last updated | Date of the latest release in the WordPress.org directory. Marked when it is more than two years old or the plugin was closed in the directory. *Not in the WordPress.org directory* for own and commercial plugins and themes. |

The dates in *Last updated* are not loaded automatically: click *Fetch release dates from WordPress.org*. They are stored until the next click.

## Privacy

The plugin stores its settings in the option `rmd_ss_settings` and the admin pages offered on the *Admin access* tab in `rmd_ss_admin_menu`; and the release dates shown on the *Security* tab in `rmd_ss_release_dates`. *Fetch release dates from WordPress.org* asks `api.wordpress.org` once per installed plugin and theme and sends its directory name, as the update check of WordPress does anyway; this happens only on that click. Apart from that the plugin sends no data anywhere. The support widget shows contact details and a logo that ship with the plugin; nothing is loaded from other servers. The only outgoing request is the update check, which reads `update.json` from the GitHub releases of this repository.

## Installation

1. Download `rmd-site-settings.zip` from the [latest release](https://github.com/reicheltmediadesign/wordpress-rmd-site-settings/releases/latest).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip and activate it.
3. Updates appear on the Plugins screen like any other plugin update.

Deleting the plugin removes its options on every site of a network.

## License

GPL-2.0-or-later, © Philipp Reichelt, [reichelt media.design](https://reicheltmedia.design)
