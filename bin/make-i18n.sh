#!/usr/bin/env bash
# Regenerates the POT file, updates the German PO (and derives the formal
# German PO from it) and compiles MO/PHP files. Requires WP-CLI (wp) with the
# i18n command.
set -euo pipefail

cd "$(dirname "$0")/.."

wp_bin="${WP_CLI:-$(command -v wp || command -v wp.bat)}"   # Git Bash on Windows only finds wp.bat.
domain="rmd-site-settings"

"$wp_bin" i18n make-pot . "languages/$domain.pot" \
	--slug="$domain" \
	--domain="$domain" \
	--exclude="node_modules,vendor,tests,dist,bin,.github" \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/reicheltmediadesign/wordpress-rmd-site-settings/issues"}' \
	--package-name="RMD Site Settings"

if [ -f "languages/$domain-de_DE.po" ]; then
	"$wp_bin" i18n update-po "languages/$domain.pot" "languages/$domain-de_DE.po"

	# The German translation already uses the formal "Sie", so "Deutsch (Sie)"
	# (de_DE_formal) is derived from it instead of being maintained separately.
	sed 's/^"Language: de_DE/&_formal/' \
		"languages/$domain-de_DE.po" > "languages/$domain-de_DE_formal.po"
fi

"$wp_bin" i18n make-mo languages
"$wp_bin" i18n make-php languages

echo "Translation files updated."
ls -1 languages
