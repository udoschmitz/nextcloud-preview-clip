#!/bin/sh
# SPDX-FileCopyrightText: 2026 Apple-Freiheit
# SPDX-License-Identifier: AGPL-3.0-or-later
set -e
HERE=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
exec python3 "$HERE/patch_files_dist_haspreview.py" "$@"

