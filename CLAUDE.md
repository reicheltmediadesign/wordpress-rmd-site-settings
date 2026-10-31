# RMD Site Settings – Projektregeln

WordPress-Plugin von reichelt media.design: Basis-Einstellungen für Kundenseiten an einer Stelle (Einstellungen → Site settings, nur `manage_options`). Ersetzt u. a. „Disable Comments“. Repo: `github.com/reicheltmediadesign/wordpress-rmd-site-settings`, Lizenz GPL-2.0-or-later, Autor Philipp Reichelt. Aufbau und Release wie `rmd-backup-wordpress` (ohne npm/Build-Schritt).

## Aufbau

- `rmd-site-settings.php` Bootstrap (muss PHP-7-parsebar bleiben), `includes/` PSR-4 `RMD\SiteSettings\`.
- `includes/Domain/` ist **WordPress-frei** und wird mit plain PHPUnit getestet: `Settings` (einzige Stelle für die Datenform der Option `rmd_ss_settings`, `defaults()`/`parse()`), `CommentScope` (welcher Post-Typ ist kommentarfrei).
- `Settings` (Settings API, Gruppe `rmd_ss_settings_group`): jeder Tab postet nur seine Sektion, `sanitize()` übernimmt die übrigen aus dem gespeicherten Wert. `footer_text` per `wp_kses` (a, strong, em).
- Module in `includes/Modules/`, je eine `final` Klasse mit `init()`, die sich nur bei aktivem Schalter anmeldet: `Comments`, `CommentCleanup` (Löschwerkzeug, `admin-post.php?action=rmd_ss_delete_comments`), `Xmlrpc`, `HeadCleanup`, `AdminArea`. Neue Module: Sektion in `Domain\Settings::defaults()`, Klasse in `Modules/`, Tab in `Admin\SettingsPage`, `Plugin::boot()`.
- Kommentare: `Comments::commentable_post_types()` merkt sich die Post-Typen **vor** `remove_post_type_support` (wp_loaded Prio 1), sonst fehlen sie in der Auswahl. Kommentartyp `note` (Block-Editor-Notizen, WP 6.9+) nie sperren oder löschen; Notes hängen an `editor`-Support `notes`, nicht an `comments`.
- Admin-Hooks aus `wp-admin/includes/admin-filters.php` erst ab `admin_init` entfernen (die Datei lädt nach `init`).
- Zugriff: `manage_options`; jede Schreibaktion mit Nonce. Multisite: Einstellungen pro Site, Uninstall über alle Sites.

## Code-Regeln

- PHP 8.1+, WP 6.8+. WordPress-Coding-Standards (`phpcs.xml.dist`), Methoden snake_case, Präfix `rmd_ss_` / `RMD_SS_` / Namespace `RMD\SiteSettings`, Textdomain `rmd-site-settings`.
- Quellstrings Englisch, Übersetzung in `languages/rmd-site-settings-de_DE.po` (Sie-Form) von Hand nachziehen; `de_DE_formal` erzeugt `bin/make-i18n.sh` daraus (braucht WP-CLI; lokal nicht installiert, im Release-Workflow vorhanden).
- Version an drei Stellen: Plugin-Header, `RMD_SS_VERSION`, `readme.txt` (Stable tag). `bash bin/check-version.sh` prüft das.
- Kopfkommentar in `assets/css/*.css` listet die Klassen.

## Build und Prüfung

```powershell
composer phpcs         # PHPCS
composer test          # PHPUnit (Domain)
bash bin/check-version.sh
bash bin/build-zip.sh  # dist/rmd-site-settings.zip (Git Bash: zip, composer nötig)
```

Lokal testen: `.\bin\link-local.ps1 -WordPressPath "C:\xampp\htdocs\testwordpress"` legt eine Junction in `wp-content\plugins` an. Kundenseiten bekommen nur das Release-Zip.

## Git und Release

- Committen nur auf Anfrage, auf Feature-Branches; nie direkt auf `main`.
- Release: Versionen an allen Stellen erhöhen, `CHANGELOG.md` und `readme.txt` (Changelog) ergänzen, committen, Tag `vX.Y.Z` pushen. Der Workflow lintet, testet, baut Übersetzungen, erzeugt `rmd-site-settings.zip` und `update.json` und legt ein **Draft-Release** an. Beide Assets müssen am Release bleiben (Update-Check liest `releases/latest/download/update.json`).
- Den vom Workflow angelegten Entwurf bearbeiten (Releases → Entwurf → Stift), nicht neu anlegen.
- Bei jedem Release Titel `vX.Y.Z – <Kernänderung>` und Release Notes (Englisch, Markdown in Codeblock) mit ausgeben; Grundlage `git log <letzter Tag>..HEAD`.
- `gh` ist installiert (ggf. mit vollem Pfad `C:\Program Files\GitHub CLI\gh.exe`); Workflow-Status alternativ über `https://api.github.com/repos/reicheltmediadesign/wordpress-rmd-site-settings/actions/runs`.
- Topics fürs Repo: `wordpress`, `wordpress-plugin`, `comments`, `xmlrpc`, `security`.

## README

Nutzerorientiert (Features, Usage, Settings, Privacy, License). Kein Development-Abschnitt in der README; Build/Release steht hier.
