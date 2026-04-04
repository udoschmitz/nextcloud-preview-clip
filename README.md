# Nextcloud preview: Clip Studio Paint (.clip)

**Repository:** [github.com/udoschmitz/nextcloud-preview-clip](https://github.com/udoschmitz/nextcloud-preview-clip)

Generates thumbnails in Nextcloud for **Clip Studio Paint** `.clip` files by extracting the embedded canvas preview.

- **License:** [AGPL-3.0](LICENSE) (same integration path as Nextcloud core preview providers)
- **References:** [dobrokot/clip_to_psd](https://github.com/dobrokot/clip_to_psd/) (format understanding — see `THIRD_PARTY_NOTICES.md`)

## How it works

1. Parse the **CSFCHUNK** container and locate the `CHNK "SQLi"` block (an embedded SQLite database).
2. Query `CanvasPreview.ImageData` from that SQLite blob — this is a ready-made PNG.
3. Fallback: heuristic scan for an embedded PNG signature directly in the file body.

No external tools are required; PHP's built-in **PDO SQLite** extension handles the query.

## Requirements

1. Nextcloud Docker build (or equivalent)
2. PHP **PDO SQLite** extension — standard in the Nextcloud base image

## What this repo contains

| File | Purpose |
|------|---------|
| `ClipStudio.php` | Core preview provider (`OC\Preview\ClipStudio`) |
| `patch_preview_manager_clip.php` | Registers the provider in `lib/private/PreviewManager.php` (idempotent) |
| `mimetypemapping.json` | MIME mapping: `.clip` → `application/x-clip-studio` |
| `Dockerfile.example` | Minimal image extension |
| `docs/PREVIEW_CLIP.md` | Full technical reference |
| `AGENTS.md` | Orientation for AI / maintainers |

## Important caveat

Nextcloud does not expose a stable public API to register **core** preview providers without editing `PreviewManager.php`. This project patches that file **at image build time** against `/usr/src/nextcloud`, like other custom Docker layers. After a **major** Nextcloud upgrade, re-check that the **Krita** anchor line in `PreviewManager.php` still matches.

## Installation (Docker)

1. Copy `ClipStudio.php` and `patch_preview_manager_clip.php` into your build context.
2. Use `Dockerfile.example` as a starting point, or merge equivalent lines into your Dockerfile.
3. Merge `mimetypemapping.json` into your live `config/mimetypemapping.json` and run:

```bash
docker exec -u 33 nextcloud-app-1 php occ maintenance:mimetype:update-js
docker exec -u 33 nextcloud-app-1 php occ maintenance:mimetype:update-db --repair-filecache
```

4. Add the provider to `enabledPreviewProviders` in `config/config.php` (see below).
5. Rebuild and recreate the container.

## `config.php`: preview provider whitelist

If you use **`enabledPreviewProviders`**, add:

```php
'OC\\Preview\\ClipStudio',
```

## Non-Docker installs

1. Place `ClipStudio.php` under `lib/private/Preview/`.
2. Run `patch_preview_manager_clip.php` once against your tree, or add `registerCoreProvider(Preview\ClipStudio::class, …)` manually next to the Krita line.

## Troubleshooting

- **No preview:** ensure the `.clip` file was saved normally (not as a layer-only export) so the embedded canvas preview exists.
- **Wrong MIME:** check that `mimetypemapping.json` is merged and `occ maintenance:mimetype:*` was run.
- **SQLite not available:** verify `php -m | grep pdo_sqlite` inside the container.
- **Upgrade broke patch:** update the Krita anchor in `patch_preview_manager_clip.php` or open an issue with the NC version.

## Contributing

Issues and PRs welcome. Please keep licensing compatible with **AGPL-3.0**.
