#!/usr/bin/env bash
set -euo pipefail

version="${1:-local}"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
slug="development-translation-bridge"
package_dir="release/${slug}"
zip_file="${slug}-${version}.zip"

cd "${root}"

test -f LICENSE
test -f README.md
test -d admin/dist

rm -rf release "${zip_file}"
mkdir -p "${package_dir}/admin"

cp -R includes "${package_dir}/"
cp -R translation-bridge "${package_dir}/"
cp -R admin/dist "${package_dir}/admin/dist"
cp functions.php style.css index.php "${package_dir}/"
cp README.md LICENSE "${package_dir}/"

find release -name '.DS_Store' -delete

( cd release && zip -qr "../${zip_file}" "${slug}" -x '*/.DS_Store' )

test -s "${zip_file}"

if command -v unzip >/dev/null 2>&1; then
	zip_listing="$(unzip -Z1 "${zip_file}")"

	zip_has() {
		printf '%s\n' "${zip_listing}" | grep -Eq "$1"
	}

	zip_lacks() {
		! printf '%s\n' "${zip_listing}" | grep -Eq "$1"
	}

	zip_has "^${slug}/LICENSE$"
	zip_has "^${slug}/README.md$"
	zip_has "^${slug}/style.css$"
	zip_has "^${slug}/functions.php$"
	zip_has "^${slug}/index.php$"
	zip_has "^${slug}/includes/"
	zip_has "^${slug}/translation-bridge/"
	zip_has "^${slug}/admin/dist/\\.vite/manifest\\.json$"
	zip_has "^${slug}/admin/dist/assets/.+\\.(js|css)$"

	zip_lacks '(^|/)\.DS_Store$'
	zip_lacks "^${slug}/\\.git/"
	zip_lacks "^${slug}/tests/"
	zip_lacks "^${slug}/vendor/"
	zip_lacks "^${slug}/admin/src/"
	zip_lacks "^${slug}/admin/node_modules/"
fi

echo "Created ${zip_file}"
