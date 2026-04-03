#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Apple-Freiheit
# SPDX-License-Identifier: AGPL-3.0-or-later
"""
Patch Nextcloud Files dist JS:

- files-main.js — usePreviewImage:
  - DAV has-preview: accept boolean true and string "true"; honor hasPreview (camelCase).
  - Preview URL: use node id when .fileid is undefined (snowflake / string file ids).

- All dist chunks that reference /core/preview: same fileId fallback for patterns like
  {fileid:e.fileid} (lazy-loaded bundles do not always use the same minified var as files-main).
"""

from __future__ import annotations

import os
import re
import sys
from glob import glob

_ID = r"[a-zA-Z_$][\w$]*(?:\.[a-zA-Z_$][\w$]*)*"

RE_FILEID_STRING = re.compile(rf"\{{fileid:String\((?!void 0)({_ID})\.fileid\)\}}")
REPL_FILEID_STRING = r"{fileid:String(void 0!==\1.fileid?\1.fileid:\1.id)}"

RE_FILEID_RAW = re.compile(rf"\{{fileid:(?!void 0)({_ID})\.fileid\}}")
REPL_FILEID_RAW = r"{fileid:void 0!==\1.fileid?\1.fileid:\1.id}"

TARGET = (
    '!(s.attributes["has-preview"]===!0||s.attributes["has-preview"]==="true")'
    '&&!(s.attributes.hasPreview===!0||s.attributes.hasPreview==="true")'
    '&&void 0!==s.mime&&"application/octet-stream"!==s.mime'
)
V2 = (
    '!0!==s.attributes["has-preview"]&&!0!==s.attributes.hasPreview'
    '&&void 0!==s.mime&&"application/octet-stream"!==s.mime'
)
V0 = (
    '!0!==s.attributes["has-preview"]&&void 0!==s.mime'
    '&&"application/octet-stream"!==s.mime'
)


def patch_fileid_expressions(content: str) -> tuple[str, list[str]]:
    actions: list[str] = []
    s, n = RE_FILEID_STRING.subn(REPL_FILEID_STRING, content)
    if n:
        actions.append(f"fileid:String-fallback×{n}")
    content = s
    s, n = RE_FILEID_RAW.subn(REPL_FILEID_RAW, content)
    if n:
        actions.append(f"fileid:raw-fallback×{n}")
    return s, actions


def files_main_still_broken(content: str) -> bool:
    return bool(RE_FILEID_STRING.search(content) or RE_FILEID_RAW.search(content))


def patch_files_main(path: str) -> list[str]:
    with open(path, encoding="utf-8", errors="replace") as f:
        s = f.read()
    actions: list[str] = []

    if TARGET not in s:
        if V2 in s:
            s = s.replace(V2, TARGET, 1)
            actions.append("has-preview:v2")
        elif V0 in s:
            s = s.replace(V0, TARGET, 1)
            actions.append("has-preview:v0")
        else:
            raise SystemExit(f"patch_files_dist_haspreview: has-preview pattern not found in {path}")

    s, fid_actions = patch_fileid_expressions(s)
    actions.extend(fid_actions)

    if files_main_still_broken(s):
        raise SystemExit(f"patch_files_dist_haspreview: unresolved fileid pattern in {path}")

    if actions:
        with open(path, "w", encoding="utf-8") as f:
            f.write(s)

    return actions


def patch_dist_chunk(path: str) -> list[str]:
    with open(path, encoding="utf-8", errors="replace") as f:
        s = f.read()
    if "{fileid:" not in s or "preview" not in s.lower():
        return []
    s2, actions = patch_fileid_expressions(s)
    if not actions:
        return []
    with open(path, "w", encoding="utf-8") as f:
        f.write(s2)
    return actions


def main() -> None:
    bases = ["/usr/src/nextcloud", "/var/www/html"]
    if len(sys.argv) > 1:
        bases = sys.argv[1:]

    main_ok = False
    for base in bases:
        root = base.rstrip("/")
        main_path = f"{root}/dist/files-main.js"
        if os.path.isfile(main_path):
            main_ok = True
            actions = patch_files_main(main_path)
            if actions:
                print(f"patched {main_path}: {', '.join(actions)}")
            else:
                print(f"already patched: {main_path}")

        for path in sorted(glob(f"{root}/dist/*.js")):
            if os.path.basename(path) == "files-main.js":
                continue
            actions = patch_dist_chunk(path)
            if actions:
                print(f"patched {path}: {', '.join(actions)}")

    if not main_ok:
        sys.exit("error: files-main.js not found under " + ", ".join(bases))


if __name__ == "__main__":
    main()

