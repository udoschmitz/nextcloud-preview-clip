#!/bin/sh
# SPDX-FileCopyrightText: 2026 Apple-Freiheit
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu

log() {
	echo "[preview-init] $*"
}

src="/usr/src/nextcloud/lib/private/Preview/ClipStudio.php"
dst="/var/www/html/lib/private/Preview/ClipStudio.php"
if [ -f "$src" ] && [ -d "/var/www/html/lib/private/Preview" ]; then
	cp -f "$src" "$dst"
	log "synced provider ClipStudio.php"
fi

if [ -f "/usr/local/bin/patch_preview_manager.php" ]; then
	php /usr/local/bin/patch_preview_manager.php >/tmp/preview_manager_patch.log 2>&1 || {
		cat /tmp/preview_manager_patch.log >&2
		exit 1
	}
	log "$(tr '\n' ' ' </tmp/preview_manager_patch.log)"
fi

if [ -x "/usr/local/bin/patch_files_dist_haspreview.py" ]; then
	python3 /usr/local/bin/patch_files_dist_haspreview.py /var/www/html >/tmp/files_dist_patch.log 2>&1 || {
		cat /tmp/files_dist_patch.log >&2
		exit 1
	}
	log "$(tr '\n' ' ' </tmp/files_dist_patch.log)"
fi

if [ "$#" -eq 0 ]; then
	set -- apache2-foreground
fi

exec /entrypoint.sh "$@"

