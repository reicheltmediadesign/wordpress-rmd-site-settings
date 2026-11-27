# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Application passwords tab: switch off application passwords per role (`wp_is_application_passwords_available_for_user`). Existing passwords of a switched-off role stop working without being deleted; users with several roles keep them as long as one role may use them. A table lists all existing application passwords with user, role, name, creation date, last use and status.

## [0.5.0] – 2026-11-14

### Added

- Admin access tab: give roles access to post types and to admin pages of plugins and the theme (e.g. editors to a theme options page that needs `manage_options`). A post type comes with all its capabilities. An admin page gets the capabilities it needs only while the admin menu is built, on its own screens and when its Settings API form is saved via `options.php`; menu items that would become visible otherwise are removed. Capabilities for users, roles, plugins, themes, updates and the network are never granted, and the WordPress settings groups and this plugin's settings cannot be saved that way.

## [0.4.0] – 2026-11-04

### Added

- Menus tab: a badge next to each role shows how many users have it; hovering or focusing it lists their display and login names.

## [0.3.0] – 2026-11-04

### Added

- Menus: give roles without the `edit_theme_options` capability (e.g. editors) access to the navigation menus – *Edit* or *View only*, per role. They get an own "Menus" item in the admin menu. *Edit* opens the WordPress menu screen; the capability is granted only for that screen and its AJAX actions, so widgets, the Customizer and theme settings stay closed. *View only* lists menu locations and menus with their items.

## [0.2.2] – 2026-11-01

### Fixed

- Support widget: buttons without icons ("Write an email", "Call"); the icons were misaligned on some sites.

## [0.2.1] – 2026-11-01

### Changed

- Support widget: calmer design without the coloured bar, full logo with an info button that links to the website, email and phone as buttons side by side (email first). The postal address is no longer shown.

## [0.2.0] – 2026-11-01

### Added

- Admin area: dashboard widget "Need help or support?" with the contact details of reichelt media.design. On by default for all users, can be switched off in the settings or hidden per user under Screen Options. Logo and contact details ship with the plugin.

## [0.1.0] – 2026-10-31

### Added

- Comments: disable everywhere or for selected post types, optionally keep showing existing comments. Covers comment forms, trackbacks and pingbacks, the Comments menu and screens, discussion settings, comment feeds, the `X-Pingback` header, the comments REST endpoint, the comment XML-RPC methods, the Recent Comments widget, comment blocks and the dashboard. Notes (`note` comment type) keep working.
- Delete tool for existing comments: all, by post type or spam only; comment counts are recalculated per post.
- XML-RPC: switch off completely.
- Page head: remove emoji script, generator tag, shortlink, oEmbed discovery links, REST API link and RSD link.
- Admin area: hide the welcome panel and the "Events and News" and "Quick Draft" widgets, own admin footer text.
- Updates from GitHub releases.
