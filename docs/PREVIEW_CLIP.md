# Clip Studio Paint preview: technical reference

## File format overview

Clip Studio Paint `.clip` files use a proprietary container format called **CSFCHUNK**:

```
[8 bytes]  Magic: "CSFCHUNK"
[16 bytes] File header
[N×]       Chunks: CHNK blocks
```

Each **CHNK** block:
```
[4 bytes]  Magic: "CHNK"
[4 bytes]  Chunk name (4-char, e.g. "SQLi", "CHNf", "HISf")
[4 bytes]  Padding / flags
[4 bytes]  Data size (big-endian uint32)
[N bytes]  Data
```

The `SQLi` chunk contains a complete **SQLite database** with all layer data, metadata, and crucially the `CanvasPreview` table.

## Preview extraction strategy

### Primary: SQLite query

```
CanvasPreview.ImageData  →  PNG bytes
```

This table always holds a ready-made PNG of the canvas at a reasonable size. It's the most reliable source.

### Fallback: raw PNG scan

If the CSFCHUNK parse fails (unusual format variant, truncated file), the provider falls back to scanning the raw file bytes for a PNG signature (`\x89PNG\r\n\x1a\n`) and reassembling PNG chunks. The scanner:
- Validates each chunk header (`[len][type][data][crc]`)
- Accepts up to 8 MB chunk data
- Scans up to 256 KB ahead when a chunk boundary is misaligned
- Stops at `IEND`

### No external tools

Everything runs within PHP using:
- `substr` / `unpack` for binary parsing
- `PDO('sqlite:...')` for the SQLite query
- Nextcloud's `OCP\Image` for scaling

## MIME type

Nextcloud needs to know that `.clip` files have MIME type `application/x-clip-studio`. Provide this via `mimetypemapping.json`:

```json
{ "clip": ["application/x-clip-studio"] }
```

Then run:
```bash
php occ maintenance:mimetype:update-js
php occ maintenance:mimetype:update-db --repair-filecache
```

## Registration in PreviewManager

`patch_preview_manager_clip.php` injects one line after the Krita registration:

```php
$this->registerCoreProvider(Preview\ClipStudio::class, '/application\/x-clip-studio/');
```

The patch is idempotent: it checks for the full insertion string before modifying the file.

## enabledPreviewProviders

If `enabledPreviewProviders` is set in `config.php`, add:

```php
'OC\\Preview\\ClipStudio',
```

Without this entry, Nextcloud ignores the registered provider.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| No preview | `application/x-clip-studio` not in DB | Run `occ maintenance:mimetype:update-db --repair-filecache` |
| No preview | Provider not in `enabledPreviewProviders` | Add `OC\\Preview\\ClipStudio` to config |
| No preview | File exported without canvas preview | Re-save with **File → Save** (not layer export) |
| `pdo_sqlite` error | PHP SQLite extension missing | `php -m \| grep pdo_sqlite` in container |
| Patch fails after NC upgrade | Krita anchor line changed | Update `$anchor` in `patch_preview_manager_clip.php` |

## Nextcloud upgrade notes

The patch anchors on:
```php
$this->registerCoreProvider(Preview\Krita::class, '/application\/x-krita/');
```

Check after major Nextcloud upgrades that this line still exists verbatim in `lib/private/PreviewManager.php`.
