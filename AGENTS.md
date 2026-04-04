# Notes for AI assistants and maintainers

## Purpose

Nextcloud previews for **Clip Studio Paint** `.clip` files by extracting the embedded canvas preview PNG from the internal SQLite database.

## File map

| File | Role |
|------|------|
| `ClipStudio.php` | `OC\Preview\ClipStudio` — parses **CSFCHUNK** container, extracts `CHNK "SQLi"` blob, queries `CanvasPreview.ImageData` via PDO SQLite. Fallback: heuristic PNG scan in raw file body. |
| `patch_preview_manager_clip.php` | Inserts `registerCoreProvider(Preview\ClipStudio::class, …)` after the **Krita** line in **`PreviewManager.php`**. Run at **Docker build** against `/usr/src/nextcloud`. **Idempotent.** |
| `mimetypemapping.json` | Maps `.clip` → `application/x-clip-studio`. |
| `Dockerfile.example` | Copies provider, runs patch. No extra apt packages needed. |
| `docs/PREVIEW_CLIP.md` | Full install/ops reference. |

## Common pitfalls

1. **`enabledPreviewProviders`:** must include **`OC\Preview\ClipStudio`** if whitelisting.
2. **Nextcloud upgrade:** if the **Krita** line moves, the patch fails → update the anchor string in `patch_preview_manager_clip.php`.
3. **Dual maintenance:** the **Apple-Freiheit** monorepo keeps copies under `server-config/nextcloud/` and a **combined** patch (Affinity, Blender, ClipStudio, FontWeb). Keep **`ClipStudio.php` logic** in sync with this standalone repo when changing the algorithm.

## Not in this repo

Server-specific runbooks, Hetzner paths, Files `dist` JS patches (`has-preview` / `fileId`) — those live only in **Apple-Freiheit**.

For tasks scoped to **this repo only**, read **`README.md`**, **`docs/PREVIEW_CLIP.md`**, and this file.
