#!/usr/bin/env bash
# Verifies that all version numbers agree. Pass a git tag (v1.2.3) to also
# compare against it.
set -euo pipefail

cd "$(dirname "$0")/.."

header=$(grep -E '^ \* Version:' rmd-site-settings.php | awk '{print $3}')
constant=$(grep -E "define\( 'RMD_SS_VERSION'" rmd-site-settings.php | sed -E "s/.*'([0-9.]+)'.*/\1/")
stable=$(grep -E '^Stable tag:' readme.txt | awk '{print $3}')

echo "Plugin header:   $header"
echo "RMD_SS_VERSION: $constant"
echo "readme.txt:      $stable"

status=0
for value in "$constant" "$stable"; do
	if [ "$value" != "$header" ]; then
		status=1
	fi
done

if [ "${1:-}" != "" ]; then
	tag="${1#v}"
	echo "Tag:             $tag"
	if [ "$tag" != "$header" ]; then
		status=1
	fi
fi

if [ $status -ne 0 ]; then
	echo "Version mismatch." >&2
	exit 1
fi
echo "All versions match."
